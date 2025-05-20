<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Ifsnop\Mysqldump as IMysqldump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class _BackupController extends Controller
{
    /* -------- تنظیمات عمومی -------- */
    private const TICK_TIME     = 15;               // ثانیه برای هر تیک
    private const ZIP_CHUNK     = 300;              // چند فایل در هر تیک به ZIP افزوده شود
    private const ZIP_MAX_BYTES = 50 * 1024 * 1024; // سقف هر فایل ZIP: ۵۰ MB
    private const IGNORE_DIRS   = [                 // پوشه‌هایی که نباید بکاپ شوند
        'vendor', 'node_modules', 'storage/app/backups', 'storage/logs',
        'storage/framework', 'storage/app/tmp', '.git',
    ];

    /* -------- endpoint ها -------- */

    /** صفحهٔ Vue */
    public function runPage(): \Inertia\Response
    {
        return \Inertia\Inertia::render('Admin/Backup/run');
    }

    /** هر ۵ ثانیه صدا زده می‌شود؛ یک اسلایس کار + متن ساده می‌دهد */
    public function performTick(Request $request): Response
    {
        $this->tick();
        return response($this->progressString(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /* -------- حلقهٔ گام‌به‌گام -------- */

    private function tick(): void
    {
        $deadline = microtime(true) + self::TICK_TIME;

        while (microtime(true) < $deadline) {
            $step = Cache::get('backup.step', 'start');
            match ($step) {
                'start'            => $this->stepStart(),
                'dump_db'          => $this->stepDumpDb(),
                'scan_files'       => $this->stepScanFiles(),
                'zip_next_batch'   => $this->stepZipNextBatch(),
                'upload_next_part' => $this->stepUploadNextPart(),
                'cleanup'          => $this->stepCleanup(),
                default            => break 2,
            };
        }
    }

    private function progressString(): string
    {
        return Cache::get('backup.percent', 0).'|'.Cache::get('backup.message', '…');
    }

    /* -------- مراحل اجرایی -------- */

    /** STEP 0: مقداردهی اولیه */
    private function stepStart(): void
    {
        $ts       = now()->format('Ymd_His');
        $baseName = "backup_{$ts}";
        $workDir  = storage_path("app/tmp/{$baseName}");
        File::ensureDirectoryExists($workDir);

        Cache::putMany([
            'backup.step'          => 'dump_db',
            'backup.percent'       => 1,
            'backup.message'       => 'شروع فرایند پشتیبان‌گیری …',
            'backup.work_dir'      => $workDir,
            'backup.zip_part'      => 1,
            'backup.zip_part_size' => 0,
            'backup.file_index'    => 0,
            'backup.parts'         => [],
            'backup.part_upload'   => 0,
            'backup.final_dir'     => storage_path("app/backups/{$baseName}"),
            'backup.files_scanned' => false,
        ]);
    }

    /** STEP 1: دامپ پایگاه‌داده با Mysqldump-PHP */
    private function stepDumpDb(): void
    {
        $workDir = Cache::get('backup.work_dir');
        $sqlPath = "{$workDir}/database.sql.gz";

        if (!File::exists($sqlPath)) {
            $conn = config('database.connections.'.config('database.default'));
            $dsn  = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $conn['host'] ?? 'localhost',
                $conn['database'],
                $conn['charset'] ?? 'utf8'
            );
            (new IMysqldump\Mysqldump($dsn, $conn['username'], $conn['password'], [
                'compress' => IMysqldump\Mysqldump::GZIP,
            ]))->start($sqlPath);
        }

        Cache::putMany([
            'backup.step'    => 'scan_files',
            'backup.percent' => 5,
            'backup.message' => 'دیتابیس ذخیره شد؛ در حال اسکن فایل‌ها …',
        ]);
    }

    /** STEP 2: اسکن درخت فایل‌ها (فقط یک‌بار) */
    private function stepScanFiles(): void
    {
        if (Cache::get('backup.files_scanned')) {
            Cache::put('backup.step', 'zip_next_batch');
            return;
        }

        $files    = [];
        $basePath = base_path();
        $iter     = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $path = $file->getPathname();
            foreach (self::IGNORE_DIRS as $dir) {
                if (Str::contains($path, DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR)) {
                    continue 2; // به مرحلهٔ بعدى این حلقه (فایل بعدی) برو
                }
            }
            $files[] = $path;
        }

        $files[] = Cache::get('backup.work_dir').'/database.sql.gz';  // فایل DB

        Cache::putMany([
            'backup.file_list'     => $files,
            'backup.total_files'   => count($files),
            'backup.files_scanned' => true,
            'backup.step'          => 'zip_next_batch',
            'backup.percent'       => 8,
            'backup.message'       => 'اسکن تمام شد؛ در حال فشرده‌سازی …',
        ]);
    }

    /** STEP 3: افزودن دسته‌ای فایل‌ها به ZIP جاری */
    private function stepZipNextBatch(): void
    {
        $workDir   = Cache::get('backup.work_dir');
        $files     = Cache::get('backup.file_list', []);
        $i         = Cache::get('backup.file_index', 0);
        $part      = Cache::get('backup.zip_part', 1);
        $partSize  = Cache::get('backup.zip_part_size', 0);
        $processed = 0;

        $zipPath = "{$workDir}/part_{$part}.zip";
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);

        while ($i < count($files) && $processed < self::ZIP_CHUNK) {
            $f = $files[$i];
            $s = filesize($f);

            // اگر اندازهٔ فایل فعلی سقف را می‌شکند، ZIP فعلی بسته شود
            if ($partSize + $s > self::ZIP_MAX_BYTES && $partSize > 0) {
                $zip->close();
                $parts   = Cache::get('backup.parts', []);
                $parts[] = $zipPath;
                Cache::putMany([
                    'backup.parts'         => $parts,
                    'backup.zip_part'      => ++$part,
                    'backup.zip_part_size' => 0,
                ]);
                return; // در تیک بعد ZIP جدید می‌سازیم
            }

            $zip->addFile($f, Str::after($f, base_path().DIRECTORY_SEPARATOR));
            $partSize += $s;
            ++$i;
            ++$processed;
        }
        $zip->close();

        Cache::putMany([
            'backup.file_index'    => $i,
            'backup.zip_part_size' => $partSize,
            'backup.percent'       => 8 + ($i / Cache::get('backup.total_files', 1)) * 70,
            'backup.message'       => 'در حال فشرده‌سازی فایل‌ها …',
        ]);

        // تمام شد؟
        if ($i >= Cache::get('backup.total_files')) {
            $parts   = Cache::get('backup.parts', []);
            $parts[] = $zipPath;
            Cache::putMany([
                'backup.parts'   => $parts,
                'backup.step'    => 'upload_next_part',
                'backup.percent' => 80,
                'backup.message' => 'فشرده‌سازی تمام شد؛ در حال ارسال …',
            ]);
        }
    }

    /** STEP 4: ارسال هر Part به FTP و کپی به خروجی محلى */
    private function stepUploadNextPart(): void
    {
        $parts = Cache::get('backup.parts', []);
        $idx   = Cache::get('backup.part_upload', 0);

        // همهٔ بخش‌ها فرستاده شده؟
        if ($idx >= count($parts)) {
            Cache::putMany([
                'backup.step'    => 'cleanup',
                'backup.percent' => 95,
                'backup.message' => 'ارسال کامل شد؛ پاک‌سازی …',
            ]);
            return;
        }

        $src    = $parts[$idx];
        $dstDir = Cache::get('backup.final_dir');
        File::ensureDirectoryExists($dstDir);
        File::copy($src, $dstDir.'/'.basename($src));     // کپی در سرور محلى

        // اگر دیسک FTP تعریف شده باشد
        if (config('filesystems.disks.ftp')) {
            Storage::disk('ftp')->putFileAs('backups', $src, basename($src));
        }

        Cache::putMany([
            'backup.part_upload' => $idx + 1,
            'backup.percent'     => 80 + (($idx + 1) / count($parts) * 15),
            'backup.message'     => 'در حال ارسال فایل‌ها …',
        ]);
    }

    /** STEP 5: پاک‌سازی موقت‌ها و پایان */
    private function stepCleanup(): void
    {
        File::deleteDirectory(storage_path('app/tmp'));

        Cache::putMany([
            'backup.step'    => 'done',
            'backup.percent' => 100,
            'backup.message' => '✅ پشتیبان‌گیری با موفقیت پایان یافت.',
        ]);
    }
}
