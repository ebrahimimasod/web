<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Setting;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
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

        $result = [];

        try {
            switch ($currentStep) {
                case 'start':
                    $result = $this->stepStartRestore();
                    break;
                case 'unzip_backup':
                    $result = $this->stepUnzipBackup();
                    break;
//                case 'backup_files':
//                    $result = $this->stepBackupFiles();
//                    break;
//                case 'backup_database':
//                    $result = $this->stepBackupDatabase();
//                    break;
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
            Log::error('[Restore] ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->afterRestore();
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
        // اگر قبلاً در سشن ذخیره نشده بساز
        if (!session()->has('restore_process_key')) {
            session(['restore_process_key' => 'restore_' . Str::uuid()]);
        }
        return session('restore_process_key');
    }

    private function getUpdateProgressBar(string $step): float
    {
        $currentIndex = array_search($step, $this->steps, true);
        $totalSteps = count($this->steps);

        return round(($currentIndex / ($totalSteps - 1)) * 100, 2);
    }

    private function getTempPath(string $path = ''): string
    {
        return storage_path('app/tmp' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    private function cacheDelete(string $suffix): void
    {
        Cache::forget($this->getCacheKey() . '_' . $suffix);
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

    private function afterRestore(): void
    {
        try {

            File::deleteDirectory($this->getTempPath());
            $this->cacheDelete('tmp_dir');
            $this->cacheDelete('has_files');
            $this->cacheDelete('has_database');
            $this->cacheDelete('backup_file_path');
            session()->forget('restore_process_key');
            Cache::forget($this->getCacheKey());

        } catch (\Throwable $ex) {
            Log::critical('[AfterRestore] ' . $ex->getMessage());
        } finally {
            Setting::set('maintenance_mode', false);
            Setting::set('backup_running', false);
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
        $this->cachePut('original_user_id', auth()->id());
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
        /* مسیر فایل بکاپ و فولدر موقت استخراج */
        $zipPath = $this->getBackupFilePath();
        $extractTo = $this->getTempPath();          // ‎…/storage/app/tmp
        if (!is_dir($extractTo)) {
            mkdir($extractTo, 0755, true);
        }

        /* ۱) باز کردن و استخراج ZIP اصلى */
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('باز کردن فایل پشتیبان ناموفق بود.');
        }
        if (!$zip->extractTo($extractTo)) {
            $zip->close();
            throw new Exception('استخراج فایل پشتیبان ناموفق بود.');
        }
        $zip->close();

        /* ۲) باز کردن تمام ZIP پارت‌هاى داخلى (…‑part1.zip, …‑part2.zip, …) */
        foreach (glob($extractTo . '/*.zip') as $partZip) {
            $inner = new ZipArchive();
            if ($inner->open($partZip) === true) {
                $inner->extractTo($extractTo);       // پوشه‌هاى files/ و database/ را ادغام مى‌کند
                $inner->close();
            }
            @unlink($partZip);                       // حذف فایل پارت بعد از استخراج
        }

        /* ۳) بررسى وجود پوشه‌هاى files و database */
        $hasFiles = File::isDirectory($extractTo . '/files');
        $hasDatabase = File::isDirectory($extractTo . '/database');

        $this->cachePut('tmp_dir', $extractTo);
        $this->cachePut('has_files', $hasFiles);
        $this->cachePut('has_database', $hasDatabase);

        /* ۴) تعیین گام بعدى */
        if ($hasFiles) {
            $nextStep = 'restore_files';
            $msg = 'در حال بازگردانى فایل‌ها…';
        } elseif ($hasDatabase) {
            $nextStep = 'restore_database';
            $msg = 'در حال بازگردانى دیتابیس…';
        } else {
            throw new Exception('در فایل پشتیبان هیچ‌کدام از پوشه‌هاى files یا database یافت نشد.');
        }

        $this->setCurrentStep($nextStep);

        return [
            'success' => true,
            'message' => $msg,
            'step' => 'unzip_backup',
            'next_step' => $nextStep,
        ];
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
                'message' => 'در حال بازگردانى دیتابیس…',
                'step' => 'restore_files',
                'next_step' => 'restore_database',
            ];
        }

        $tmpDir = $this->cacheGet('tmp_dir');
        $unzippedFilesDir = $tmpDir . '/files';

        $projectRoot = realpath(base_path('..')) ?: base_path();
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);


        File::copyDirectory($unzippedFilesDir, $projectRoot);

        /* 3) پاک‌کردن کش‌ها و (اختیاری) بازسازی */
        try {
            // پاک‌سازی همه cacheها (config, route, view, events,…)
            Artisan::call('optimize:clear');
//            Artisan::call('optimize');
        } catch (\Throwable $e) {
//            Log::warning('[Restore] Artisan optimize failed: ' . $e->getMessage());
            throw new Exception('[Restore] Artisan optimize failed: ' . $e->getMessage());
            // شکست در این مرحله致ی حیاتی نیست، بنابراین ادامه می‌دهیم
        }

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
                'message' => 'در حال پاکسازى فایل‌هاى موقت…',
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

        if ($userId = $this->cacheGet('original_user_id')) {
            try {
                auth()->loginUsingId($userId, remember: false);
            } catch (\Throwable $e) {
                Log::warning('[Restore] Relogin failed: ' . $e->getMessage());
            }
        }

        $this->setCurrentStep('clean');
        return [
            'success' => true,
            'message' => 'دیتابیس با موفقیت بازگردانى شد. در حال پاکسازى فایل‌هاى موقت…',
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
        $this->afterRestore();

        return [
            'success' => true,
            'message' => 'بازگردانى فایل پشتیبان با موفقیت پایان یافت.',
            'step' => 'finished',
            'next_step' => null,
        ];
    }
}
