<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Setting;
use App\Models\User;
use Carbon\Carbon;
use Ifsnop\Mysqldump as Mysqldump;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use ZipArchive;

class BackupController
{
    /* ------------------------------------------------------------------------------------------------
     |  مراحل اجرای بکاپ
     |-------------------------------------------------------------------------------------------------*/
    public array $steps = [
        'start',                // آماده‌سازی اولیه
        'check_requirements',   // بررسی پیش‌نیازها
        'dumping_database',     // ایجاد dump دیتابیس
        'zipping_files',        // ساخت زیپ‌های چندگانه ≤ 50MB
        'packaging_parts',      // قراردادن تمام زیپ‌ها در یک فایل واحد
        'save_backup',          // آپلود روی دیسک انتخابی
        'clean',                // پاکسازی
        'finished',             // پایان
    ];

    /** حداکثر اندازهٔ هر پارت (بایت) – 50MB */
    public const CHUNK_SIZE = 50 * 1024 * 1024;

    public function index(): \Inertia\Response
    {
        $backup_file_setting = app('setting')->get('backup_file_setting');
        $backup_schedule_setting = app('setting')->get('backup_schedule_setting');
        $backup_storage_setting = app('setting')->get('backup_storage_setting');

        $files = collect(Storage::disk('local')->files('backups'))
            ->map(fn($p) => [
                'name' => pathinfo($p, PATHINFO_FILENAME),
                'created_at' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($p), config('app.timezone')),
                'size_kb' => humanSize(Storage::disk('local')->size($p)),
                'path' => $p,
            ])
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('backup/index', [
            'files' => $files,
            'backup_file_setting' => $backup_file_setting,
            'backup_schedule_setting' => $backup_schedule_setting,
            'backup_storage_setting' => $backup_storage_setting,
        ]);
    }

    public function runBackup(): \Inertia\Response
    {
        return Inertia::render('backup/run');
    }

    public function updateScheduleSetting(): RedirectResponse
    {
        $validator = Validator::make(request()->all(), [
            'enabled' => ['required', 'boolean'],
            'schedule' => ['required', 'string', 'in:12_hours,daily,weekly,fortnightly,monthly'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        app('setting')->set('backup_schedule_setting', $validator->validated());

        return redirect()->back()->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }

    public function updateStorageSetting(): RedirectResponse
    {
        app('setting')->set('backup_storage_setting', request('connections'));

        return redirect()->back()->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }

    public function updateFileSetting(): RedirectResponse
    {
        $validator = Validator::make(request()->all(), [
            'storage' => ['required', 'string', 'in:local,ftp,sftp,s3'],
            'type' => ['required', 'string', 'in:all,files,database'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        app('setting')->set('backup_file_setting', $validator->validated());

        return redirect()->back()->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }

    public function performBackupStep(): RedirectResponse
    {
        $step = $this->getCurrentStep();
        $result = match ($step) {
            'start' => $this->stepStartBackup(),
            'check_requirements' => $this->stepCheckRequirements(),
            'dumping_database' => $this->stepDumpingDatabase(),
            'zipping_files' => $this->stepZippingFiles(),
            'packaging_parts' => $this->stepPackagingParts(),
            'save_backup' => $this->stepSaveBackupFile(),
            'clean' => $this->stepCleanUpdate(),
            default => $this->stepFinishedUpdate(),
        };
        $result['percentage'] = $this->getUpdateProgressBar($result['step'] ?? $step);
        return back()->with('back_response', $result);
    }

    public function downloadFile(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $p = request('filePath');
        abort_if(!$p, 404);
        return Storage::disk('local')->download($p);
    }

    public function deleteFile(): RedirectResponse
    {
        $p = request('filePath');
        abort_if(!$p, 404);
        Storage::disk('local')->delete($p);
        return back()->with('success', 'حذف شد');
    }

    /* ==============================================================================================*/
    /*                                     Helper methods                                            */
    /* ==============================================================================================*/
    private function getCacheKey(): string
    {
        return 'backup_for_user_' . auth()->id();
    }

    private function getCurrentStep(): string
    {
        return Cache::get($this->getCacheKey(), $this->steps[0]);
    }

    private function setCurrentStep(string $s): void
    {
        Cache::put($this->getCacheKey(), $s, 3600);
    }

    private function getTempPath(string $rel = ''): string
    {
        return storage_path('app/tmp' . ($rel ? DIRECTORY_SEPARATOR . $rel : ''));
    }

    private function getUpdateProgressBar(string $step): float
    {
        return round(array_search($step, $this->steps, true) / (count($this->steps) - 1) * 100, 2);
    }

    /* ==============================================================================================*/
    /*                                        Steps                                                   */
    /* ==============================================================================================*/

    /** Step 1: create tmp folder */
    private function stepStartBackup(): array
    {
        if (!is_dir($this->getTempPath())) mkdir($this->getTempPath(), 0755, true);
        Setting::set('backup_running', true);
        $this->setCurrentStep('check_requirements');
        return ['success' => true, 'message' => 'در حال آماده‌سازی...', 'step' => 'start', 'next_step' => 'check_requirements'];
    }

    /** Step 2: check maintenance mode & decide next */
    private function stepCheckRequirements(): array
    {
        if (Setting::get('maintenance_mode', false)) {
            Setting::set('backup_running', false);
            return ['success' => false, 'message' => 'سایت در حالت به‌روزرسانی است', 'step' => 'check_requirements'];
        }
        $type = Setting::get('backup_file_setting', ["type" => "all", "storage" => "local"])['type'] ?? 'all';
        $next = $type === 'files' ? 'zipping_files' : 'dumping_database';
        $msg = $next === 'zipping_files' ? 'در حال تهیه پشتیبان از فایل‌ها...' : 'در حال تهیه‌ی بکاپ دیتابیس...';
        $this->setCurrentStep($next);
        return ['success' => true, 'message' => $msg, 'step' => 'check_requirements', 'next_step' => $next];
    }

    /** Step 3: dump DB with mysqldump‑php */
    private function stepDumpingDatabase(): array
    {
        $dump = $this->getTempPath('dump.sql');
        $db = config('database.connections.mysql');
        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']}";
            $d = new Mysqldump\Mysqldump($dsn, $db['username'], $db['password'], [
                'single-transaction' => true,
                'add-drop-table' => true,
                'lock-tables' => false,
                'hex-blob' => true,
            ]);
            $d->start($dump);
            $this->setCurrentStep('zipping_files');
            return ['success' => true, 'message' => 'فشرده‌سازی فایل‌ها...', 'step' => 'dumping_database', 'next_step' => 'zipping_files'];
        } catch (\Throwable $e) {
            Setting::set('backup_running', false);
            return ['success' => false, 'message' => 'خطا در بکاپ دیتابیس: ' . $e->getMessage(), 'step' => 'dumping_database'];
        }
    }

    /** Step 4: zipping files */
    private function stepZippingFiles(): array
    {
        $CHUNK_SIZE = defined('self::CHUNK_SIZE') ? self::CHUNK_SIZE : 50 * 1024 * 1024;   // 50MB

        $setting = Setting::get('backup_file_setting', ['type' => 'all']);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $baseName = config('app.name') . '-backup-' . $timestamp;

        $files = [];

        /* ------------------------------------------------------------------------
         | ۱) افزودن dump دیتابیس (در صورت نیاز)
         |------------------------------------------------------------------------*/
        if (in_array($setting['type'], ['all', 'database'], true)) {
            $dump = $this->getTempPath('dump.sql');
            if (is_file($dump)) {
                $files[] = ['path' => $dump, 'dest' => 'database/dump.sql'];
            }
        }

        /* ------------------------------------------------------------------------
         | ۲) جمع‌آوری فایل‌های پروژه (ایمن در برابر open_basedir)
         |------------------------------------------------------------------------*/
        if (in_array($setting['type'], ['all', 'files'], true)) {

            // پوشه‌ها / فایل‌های حذف‌شده (به‌صورت مسیر کامل نرمال‌شده)
            $exclude = array_map(
                fn($rel) => rtrim(str_replace('\\', '/', base_path($rel)), '/'),
                [
                    'node_modules', 'tests', '.git', '.github', '.cagefs',
                    'logs', 'access-logs',                           // مسیرهای مشکل‌ساز معمول
                    'storage/logs', 'storage/framework/cache',
                    'storage/framework/sessions', 'storage/framework/views',
                    'storage/app/private/backups', 'public/.well-known',
                ]
            );

            $baseDir = rtrim(str_replace('\\', '/', base_path()), '/') . '/';
            $baseLen = strlen($baseDir);

            /**
             * گردآورندهٔ بازگشتی ساده با scandir
             * - هیچ symlinkـی را دنبال نمی‌کند
             * - خطاهای open_basedir را با @ خاموش می‌کند
             */
            $collect = function (string $dir) use (&$collect, &$files, $exclude, $baseLen) {

                $items = @scandir($dir);
                if ($items === false) {
                    return;                              // احتمالاً محدودیت open_basedir
                }

                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $path = $dir . '/' . $item;
                    $n = str_replace('\\', '/', $path);

                    // حذف مسیرهای exclude
                    foreach ($exclude as $ex) {
                        if (strpos($n, $ex) === 0) {
                            continue 2;
                        }
                    }

                    // حذف symlink‌ها
                    if (@is_link($n)) {
                        continue;
                    }

                    if (is_dir($n)) {
                        $collect($n);                    // بازگشت برای زیرشاخه
                    } elseif (is_file($n)) {
                        $files[] = [
                            'path' => $n,
                            'dest' => 'files/' . substr($n, $baseLen),
                        ];
                    }
                }
            };

            $collect(rtrim($baseDir, '/'));
        }

        /* ------------------------------------------------------------------------
         | ۳) ایجاد پارت‌های ZIP حداکثر ۵۰ MB
         |------------------------------------------------------------------------*/
        $partIdx = 1;
        $currentSize = 0;
        $partPaths = [];
        $zip = new ZipArchive();

        $openPart = function () use (&$zip, &$partIdx, &$partPaths, $baseName, $CHUNK_SIZE) {
            $zipName = "{$baseName}-part{$partIdx}.zip";
            $zipPath = $this->getTempPath($zipName);

            if (file_exists($zipPath)) unlink($zipPath);
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Cannot create {$zipName}");
            }

            $partPaths[] = $zipPath;
        };

        $openPart();   // پارت اوّل

        foreach ($files as $f) {
            $size = filesize($f['path']);

            if ($currentSize > 0 && $currentSize + $size > $CHUNK_SIZE) {
                $zip->close();
                ++$partIdx;
                $currentSize = 0;
                $openPart();
            }

            $zip->addFile($f['path'], $f['dest']);
            $currentSize += $size;
        }
        $zip->close();

        /* ------------------------------------------------------------------------
         | ۴) ذخیرهٔ مسیر پارت‌ها برای مرحلهٔ بعدی
         |------------------------------------------------------------------------*/
        Cache::put($this->getCacheKey() . '_part_paths', $partPaths, 3600);
        Cache::put($this->getCacheKey() . '_base_name', $baseName, 3600);

        $this->setCurrentStep('packaging_parts');

        return [
            'success' => true,
            'message' => 'در حال بسته‌بندی پارت‌ها...',
            'step' => 'zipping_files',
            'next_step' => 'packaging_parts',
        ];
    }

    /** Step 5: package all zip parts into one zip */
    private function stepPackagingParts(): array
    {
        $partPaths = Cache::get($this->getCacheKey() . '_part_paths', []);
        $baseName = Cache::get($this->getCacheKey() . '_base_name');
        if (!$partPaths || !$baseName) {
            Setting::set('backup_running', false);
            return ['success' => false, 'message' => 'پارت‌ها جهت بسته‌بندی پیدا نشد', 'step' => 'packaging_parts'];
        }
        $containerName = "$baseName.zip"; // فایل نهایی برای دانلود
        $containerPath = $this->getTempPath($containerName);
        try {
            $zip = new ZipArchive();
            if ($zip->open($containerPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Cannot create container');
            foreach ($partPaths as $pp) $zip->addFile($pp, basename($pp));
//            $zip->addFromString('README.txt', "برای بازیابی:\n1. محتوای این زیپ را استخراج کنید.\n2. فایل‌های part را در یک پوشه قرار داده و طبق ترتیب نام از part1 شروع به استخراج نمایید یا با اسکریپت بازگردانی همراه فایل اجرا کنید.");
            $zip->close();
            foreach ($partPaths as $pp) @unlink($pp); // حذف پارت‌های موقت
            Cache::put($this->getCacheKey() . '_zip_path', $containerPath, 3600);
            Cache::put($this->getCacheKey() . '_zip_name', $containerName, 3600);
            Cache::forget($this->getCacheKey() . '_part_paths');
            $this->setCurrentStep('save_backup');
            return ['success' => true, 'message' => 'در حال ذخیره پشتیبان...', 'step' => 'packaging_parts', 'next_step' => 'save_backup'];
        } catch (\Throwable $e) {
            Setting::set('backup_running', false);
            return ['success' => false, 'message' => 'خطا در بسته‌بندی: ' . $e->getMessage(), 'step' => 'packaging_parts'];
        }
    }

    /** Step 6: save final container on chosen disk */
    private function stepSaveBackupFile(): array
    {
        $zipPath = Cache::get($this->getCacheKey() . '_zip_path');
        $zipName = Cache::get($this->getCacheKey() . '_zip_name');
        if (!$zipPath || !file_exists($zipPath)) {
            Setting::set('backup_running', false);
            return ['success' => false, 'message' => 'فایل نهایی یافت نشد', 'step' => 'save_backup'];
        }
        $disk = Setting::get('backup_file_setting.storage', 'local');
        try {

            $stream = fopen($zipPath, 'r');
            Storage::disk($disk)->put('backups/' . $zipName, $stream);
            fclose($stream);


            if ($disk !== 'local') Storage::disk('local')->putFileAs('backups', $zipPath, $zipName);
            $this->setCurrentStep('clean');
            return ['success' => true, 'message' => 'پاکسازی فایل‌های موقت...', 'step' => 'save_backup', 'next_step' => 'clean'];
        } catch (\Throwable $e) {
            Setting::set('backup_running', false);
            return ['success' => false, 'message' => 'خطا در ذخیره: ' . $e->getMessage(), 'step' => 'save_backup'];
        }
    }

    /** Step 7: cleanup tmp */
    private function stepCleanUpdate(): array
    {
        try {
            if (is_dir($this->getTempPath())) File::deleteDirectory($this->getTempPath());
        } catch (\Throwable $e) {
        }
        $this->setCurrentStep('finished');
        return ['success' => true, 'message' => 'فرآیند تکمیل شد', 'step' => 'clean', 'next_step' => 'finished'];
    }

    /** Step 8: finished */
    private function stepFinishedUpdate(): array
    {
        Setting::set('backup_running', false);
        Cache::forget($this->getCacheKey() . '_zip_path');
        Cache::forget($this->getCacheKey() . '_zip_name');
        Cache::forget($this->getCacheKey() . '_base_name');
        Cache::forget($this->getCacheKey());
        return ['success' => true, 'message' => 'پشتیبان‌گیری با موفقیت انجام شد.', 'step' => 'finished'];
    }


    public function test()
    {
        for ($i = 0; $i < 10000; $i++) {
            User::query()->create([
                User::COL_FIRST_NAME => "کاربر$i",
                User::COL_LAST_NAME => "کاربری $i",
                User::COL_EMAIL => "masoud$i@gmail.com",
                User::COL_PASSWORD => null,
                User::COL_STATUS => true,
                User::COL_IS_ADMIN => false,
                User::COL_PHONE_NUMBER => "092231739$i",
                User::COL_EMAIL_VERIFIED_AT => now(),
                User::COL_PHONE_NUMBER_VERIFIED_AT => now(),
            ]);
        }
        return 'ok';
    }
}
