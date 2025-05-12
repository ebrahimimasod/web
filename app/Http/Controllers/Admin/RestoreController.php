<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Setting;
use Exception;
use FilesystemIterator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RecursiveIteratorIterator;
use Spatie\DbDumper\Databases\MySql;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;
use ZipArchive;


class RestoreController
{
    /** @var array<string> */
    public array $steps = [
        'start',           // فعال سازی حالت تعمیر و ساخت پوشه موقت
        'unzip_backup',    // استخراج محتویات فایل zip
        'backup_files',    // کپی ایمن فایل‌هاى فعلی (rollback)
        'backup_database', // dump دیتابیس فعلی (rollback)
        'restore_files',   // جایگزینى / ادغام فایل‌هاى قدیم با جدید
        'restore_database',// ایمپورت dump داخل بکاپ
        'clean',           // حذف دایرکتورى موقت
        'finished',        // پایان عملیات
    ];

    /* ------------------------------------------------------------------
     |  صفحه اولیه (نمایش progress bar)
     |------------------------------------------------------------------*/
    public function runRestore(): \Inertia\Response
    {
        Setting::set('maintenance_mode', false);
        $backupFilePath = request('filePath');
        $this->cachePut('backup_file_path', Storage::disk('local')->path($backupFilePath));
        return Inertia::render('backup/restore');
    }

    /* ------------------------------------------------------------------
     |  فراخوانى توسط polling براى اجراى مرحله بعد
     |------------------------------------------------------------------*/
    public function performRestoreStep(): RedirectResponse
    {
        $currentStep = $this->getCurrentStep();

        info('currentStep : ' . $currentStep);
        $result = [];

        try {
            switch ($currentStep) {
                case 'start':
                    $result = $this->stepStartRestore();
                    break;
                case 'unzip_backup':
                    $result = $this->stepUnzipBackup();
                    break;
                case 'backup_files':
                    $result = $this->stepBackupFiles();
                    break;
                case 'backup_database':
                    $result = $this->stepBackupDatabase();
                    break;
                case 'restore_files':
                    $result = $this->stepRestoreFiles();
                    break;
                case 'restore_database':
                    $result = $this->stepRestoreDatabase();
                    break;
                case 'clean':
                    $result = $this->stepCleanUpdate();
                    break;
                case 'finished':
                    $result = $this->stepFinishedRestore();
                    break;
            }
        } catch (Exception $e) {
            Setting::set('maintenance_mode', false);
            Log::error('[Restore] ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $result = [
                'success' => false,
                'message' => 'خطا: ' . $e->getMessage(),
                'step' => $currentStep,
                'next_step' => null,
            ];
        }

        // پیشرفت
        $result = array_merge($result, [
            'percentage' => $this->getUpdateProgressBar($result['step'] ?? $currentStep),
        ]);

        return back()->with('back_response', $result);
    }

    /* ==================================================================
     |  Helpers
     |==================================================================*/
    private function getCurrentStep(): string
    {
        return Cache::get($this->getCacheKey(), $this->steps[0]);
    }

    private function setCurrentStep(string $step): void
    {
        Cache::put($this->getCacheKey(), $step, 3600);
    }

    private function getCacheKey(): string
    {
        return 'restore_for_user_' . auth()->id();
    }

    private function getUpdateProgressBar(string $step): float
    {
        $currentIndex = array_search($step, $this->steps, true);
        $totalSteps = count($this->steps);

        return round(($currentIndex / ($totalSteps - 1)) * 100, 2);
    }

    private function getTempPath(string $path = ''): string
    {
        return storage_path('app/temp/restore' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    private function cachePut(string $suffix, mixed $value): void
    {
        Cache::put($this->getCacheKey() . '_' . $suffix, $value, 3600);
    }

    private function cacheGet(string $suffix, mixed $default = null): mixed
    {
        return Cache::get($this->getCacheKey() . '_' . $suffix, $default);
    }

    private function getBackupFilePath(): string
    {
        return $this->cacheGet('backup_file_path');
    }


    /**
     * کپی دایرکتوری با امکان نادیده گرفتن پوشه‌ها/فایل‌ها
     *
     * @param string $source مسیر مبدا
     * @param string $destination مسیر مقصد
     * @param string[] $exclude مسیرهای مطلقی که نباید کپی شوند (بدون اسلش پایانی)
     */
    private function copyDirectoryFiltered(string $source, string $destination, array $exclude): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            // هر آیتمی که با یکی از مسیرهای exclude شروع شود، رد می‌شود
            foreach ($exclude as $skip) {
                if (str_starts_with($path, $skip)) {
                    // اگر دایرکتوری است، فرزندانش را هم رد کن
                    if ($item->isDir()) {
                        $iterator->next();
                    }
                    continue 2;   // به حلقهٔ بیرونی
                }
            }

            // مسیر مقصدِ این آیتم
            $target = $destination . DIRECTORY_SEPARATOR . substr($path, strlen($source));

            if ($item->isDir()) {
                File::ensureDirectoryExists($target, $item->getPerms());
            } else {
                File::ensureDirectoryExists(dirname($target));
                File::copy($path, $target);
                File::chmod($target, $item->getPerms());
            }
        }
    }

    /* ==================================================================
     |  Steps implementations
     |==================================================================*/

    /**
     * STEP 1  ────────────────────────────────────────────────────────────
     */
    private function stepStartRestore(): array
    {
        if (Setting::get('backup_running', false)) {
            return [
                'success' => false,
                'message' => 'خطا: در حال حاضر فرایند پشتیبان‌گیری فعال است. بعداً تلاش کنید.',
                'step' => 'start',
            ];
        }
        if (Setting::get('maintenance_mode', false)) {
            return [
                'success' => false,
                'message' => 'خطا: سیستم اکنون در حالت به‌روزرسانی است.',
                'step' => 'start',
            ];
        }
        $zipFile = $this->getBackupFilePath();
        if (empty($zipFile) || !File::exists($zipFile)) {
            return [
                'success' => false,
                'message' => 'فایل پشتیبان یافت نشد.',
                'step' => 'start',
            ];
        }

        Setting::set('maintenance_mode', true);

        // پوشهٔ tmp را تمیز ایجاد می‌کنیم (اگر قبلاً وجود داشته باشد پاک می‌شود)
        File::deleteDirectory($this->getTempPath());
        File::makeDirectory($this->getTempPath(), 0755, true);

        $this->setCurrentStep('unzip_backup');
        return [
            'success' => true,
            'message' => 'در حال استخراج فایل پشتیبان…',
            'step' => 'start',
            'next_step' => 'unzip_backup',
        ];
    }

    /**
     * STEP 2  ────────────────────────────────────────────────────────────
     */
    private function stepUnzipBackup(): array
    {
        $zipPath = $this->getBackupFilePath();                       // مسیر فایل زیپ
        $extractTo = $this->getTempPath();                           // محل استخراج


        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('باز کردن فایل پشتیبان ناموفق بود.');
        }
        if (!$zip->extractTo($extractTo)) {
            $zip->close();
            throw new Exception('استخراج فایل پشتیبان ناموفق بود.');
        }
        $zip->close();

        // وجود فولدرهاى files و database
        $hasFiles = File::isDirectory($extractTo . '/files');
        $hasDatabase = File::isDirectory($extractTo . '/database');

        $this->cachePut('tmp_dir', $extractTo);
        $this->cachePut('has_files', $hasFiles);
        $this->cachePut('has_database', $hasDatabase);

        // تعیین مرحله بعدى بر اساس وجود فولدرها
        if ($hasFiles) {
            $next = 'backup_files';
            $msg = 'در حال تهیه نسخهٔ پشتیبان از فایل‌ها…';
        } elseif ($hasDatabase) {
            $next = 'backup_database';
            $msg = 'در حال تهیه نسخهٔ پشتیبان از دیتابیس…';
        } else {
            throw new Exception('در فایل پشتیبان هیچ‌کدام از فولدرهاى files یا database یافت نشد.');
        }

        $this->setCurrentStep($next);
        return [
            'success' => true,
            'message' => $msg,
            'step' => 'unzip_backup',
            'next_step' => $next,
        ];
    }

    /**
     * STEP 3  ────────────────────────────────────────────────────────────
     */
    private function stepBackupFiles(): array
    {
        if (!$this->cacheGet('has_files')) {
            $this->setCurrentStep('backup_database');
            return [
                'success' => true,
                'message' => 'مرحله فایل‌ها رد شد. در حال تهیه نسخهٔ پشتیبان از دیتابیس…',
                'step' => 'backup_files',
                'next_step' => 'backup_database',
            ];
        }

        $rollbackDir = $this->getTempPath('rollback_files');
        File::deleteDirectory($rollbackDir);
        File::makeDirectory($rollbackDir, 0755, true);

        $projectFilesPath = base_path();

        /* مسیرهایی که باید نادیده گرفته شوند */
        $exclude = [
            base_path('node_modules'),
            base_path('vendor'),
            base_path('tests'),
            base_path('.git'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('app/private/backups'),
        ];
        $this->copyDirectoryFiltered($projectFilesPath, $rollbackDir, $exclude);

        $this->setCurrentStep('backup_database');

        return [
            'success' => true,
            'message' => 'نسخهٔ پشتیبان فایل‌ها ذخیره شد. در حال تهیه نسخهٔ پشتیبان از دیتابیس…',
            'step' => 'backup_files',
            'next_step' => 'backup_database',
        ];
    }

    /**
     * STEP 4  ────────────────────────────────────────────────────────────
     */
    private function stepBackupDatabase(): array
    {
        if (!$this->cacheGet('has_database')) {
            $this->setCurrentStep('restore_files');
            return [
                'success' => true,
                'message' => 'مرحله دیتابیس رد شد. در حال بازگردانى فایل‌ها…',
                'step' => 'backup_database',
                'next_step' => 'restore_files',
            ];
        }

        $rollbackPath = $this->getTempPath('rollback_db.sql');
        $dbConfig = config('database.connections.mysql');
        try {
            MySql::create()
                ->setDbName($dbConfig['database'])
                ->setUserName($dbConfig['username'])
                ->setPassword($dbConfig['password'])
                ->setHost($dbConfig['host'])
                ->setPort($dbConfig['port'] ?? 3306)
                ->dumpToFile($rollbackPath);

            $this->setCurrentStep('restore_files');
            return [
                'success' => true,
                'message' => 'نسخهٔ پشتیبان دیتابیس ذخیره شد. در حال بازگردانى فایل‌ها…',
                'step' => 'backup_database',
                'next_step' => 'restore_files',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطا در پشتیبان‌گیری از دیتابیس: ' . $e->getMessage(),
                'step' => 'dumping_database',
                'next_step' => null,
            ];
        }
    }

    /**
     * STEP 5  ────────────────────────────────────────────────────────────
     */
    private function stepRestoreFiles(): array
    {
        if (!$this->cacheGet('has_files')) {
            $this->setCurrentStep('restore_database');
            return [
                'success' => true,
                'message' => 'مرحله بازگردانى فایل‌ها رد شد. در حال بازگردانى دیتابیس…',
                'step' => 'restore_files',
                'next_step' => 'restore_database',
            ];
        }

        $tmpDir = $this->cacheGet('tmp_dir');
        $unzippedFilesDir = $tmpDir . '/files';

        $projectFilesPath = base_path();


        File::copyDirectory($unzippedFilesDir, $projectFilesPath);//TODO::آیا فایل های تکراری را هندل می کند؟


        $this->setCurrentStep('restore_database');
        return [
            'success' => true,
            'message' => 'فایل‌ها بازگردانى شدند. در حال بازگردانى دیتابیس…',
            'step' => 'restore_files',
            'next_step' => 'restore_database',
        ];
    }

    /**
     * STEP 6  ────────────────────────────────────────────────────────────
     */
    private function stepRestoreDatabase(): array
    {

        if (!$this->cacheGet('has_database')) {
            $this->setCurrentStep('clean');
            return [
                'success' => true,
                'message' => 'مرحله بازگردانى دیتابیس رد شد. در حال پاکسازى…',
                'step' => 'restore_database',
                'next_step' => 'clean',
            ];
        }

        set_time_limit(0);
        ini_set('max_execution_time', 0);
        $tmpDir = $this->cacheGet('tmp_dir');
        $dbDir = $tmpDir . '/database';

        // یافتن فایلى با انتهاى .sql
        $dumpFile = collect(File::files($dbDir))->first(fn($f) => Str::endsWith($f->getFilename(), ['.sql']));

        if (!$dumpFile) {
            throw new Exception('هیچ فایل dump در پوشهٔ database پیدا نشد.');
        }

        // Disable FK checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $handle = fopen($dumpFile->getPathname(), 'r');
        $buffer = '';
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) break;
            // عبور از کامنت‌ها و خطوط خالی
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) continue;
            $buffer .= $line;
            if (str_ends_with(trim($line), ';')) {
                // اجراى هر Query به محض رسیدن به سمی‌کالن
                DB::unprepared($buffer);
                $buffer = '';
            }
        }
        fclose($handle);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');


        $this->setCurrentStep('clean');
        return [
            'success' => true,
            'message' => 'دیتابیس با موفقیت بازگردانى شد. در حال پاکسازى…',
            'step' => 'restore_database',
            'next_step' => 'clean',
        ];
    }

    /**
     * STEP 7  ────────────────────────────────────────────────────────────
     */
    private function stepCleanUpdate(): array
    {
        File::deleteDirectory($this->getTempPath());

        $this->setCurrentStep('finished');
        return [
            'success' => true,
            'message' => 'فایل‌هاى موقت حذف شدند. در حال پایان عملیات…',
            'step' => 'clean',
            'next_step' => 'finished',
        ];
    }

    /**
     * STEP 8  ────────────────────────────────────────────────────────────
     */
    private function stepFinishedRestore(): array
    {
        Setting::set('maintenance_mode', false);
        Cache::forget($this->getCacheKey());

        return [
            'success' => true,
            'message' => 'بازگردانى فایل پشتیبان با موفقیت پایان یافت.',
            'step' => 'finished',
            'next_step' => null,
        ];
    }
}
