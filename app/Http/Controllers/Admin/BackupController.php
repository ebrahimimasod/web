<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Spatie\DbDumper\Databases\MySql;
use ZipArchive;

class BackupController
{
    /**
     * ترتیب مراحل اجرای بکاپ
     */
    public array $steps = [
        'start',              // آماده‌سازی و ایجاد فولدر موقت
        'check_requirements', // بررسی شرایط اجرای بکاپ
        'dumping_database',   // تهیه dump از دیتابیس
        'zipping_files',      // فشرده‌سازی فایل‌ها
        'save_backup',        // ذخیره فایل بکاپ در لوکال / ریموت
        'clean',              // پاکسازی فایل ها و پوشه‌های موقت
        'finished',           // اتمام و اعلان به کاربر
    ];

    /* ----------------------------------------------------------------------------------------------
     |  صفحه فهرست بکاپ‌ها
     |-----------------------------------------------------------------------------------------------*/
    public function index(): \Inertia\Response
    {
        $backup_file_setting = app('setting')->get('backup_file_setting');
        $backup_schedule_setting = app('setting')->get('backup_schedule_setting');
        $backup_storage_setting = app('setting')->get('backup_storage_setting');
        $files = collect(Storage::disk('local')->files('backups'))
            ->map(function ($path) {
                return [
                    'name' => pathinfo($path, PATHINFO_FILENAME),      // فقط نام
                    'created_at' => Carbon::createFromTimestamp(
                        Storage::disk('local')->lastModified($path),
                        config('app.timezone')
                    )->format('Y-m-d H:i:s'),
                    'size_kb' => humanSize(Storage::disk('local')->size($path)),
                    'path' => $path,                                   // برای دانلود یا حذف
                ];
            })
            ->sortByDesc('created_at')   // جدیدها اول
            ->values()
            ->toArray();

        return Inertia::render('backup/index', [
            'files' => $files,
            'backup_file_setting' => $backup_file_setting,
            'backup_schedule_setting' => $backup_schedule_setting,
            'backup_storage_setting' => $backup_storage_setting,
        ]);
    }

    /* ----------------------------------------------------------------------------------------------
     |  تنظیمات زمان‌بندی
     |-----------------------------------------------------------------------------------------------*/
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

    /* ----------------------------------------------------------------------------------------------
     |  تنظیمات مقصد ذخیره‌سازی
     |-----------------------------------------------------------------------------------------------*/
    public function updateStorageSetting(): RedirectResponse
    {
        app('setting')->set('backup_storage_setting', request('connections'));

        return redirect()->back()->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }

    /* ----------------------------------------------------------------------------------------------
     |  تنظیمات فایل خروجی
     |-----------------------------------------------------------------------------------------------*/
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

    /* ----------------------------------------------------------------------------------------------
     |  صفحه اجرای بکاپ (مشاهده ProgressBar)
     |-----------------------------------------------------------------------------------------------*/
    public function runBackup(): \Inertia\Response
    {
        return Inertia::render('backup/run');
    }

    /* ----------------------------------------------------------------------------------------------
     |  Polling فرانت برای اجرای مرحله بعدی
     |-----------------------------------------------------------------------------------------------*/
    public function performBackupStep(): RedirectResponse
    {
        $result = [];
        $currentStep = $this->getCurrentStep();

        switch ($currentStep) {
            case 'start':
                $result = $this->stepStartBackup();
                break;

            case 'check_requirements':
                $result = $this->stepCheckRequirements();
                break;

            case 'dumping_database':
                $result = $this->stepDumpingDatabase();
                break;

            case 'zipping_files':
                $result = $this->stepZippingFiles();
                break;

            case 'save_backup':
                $result = $this->stepSaveBackupFile();
                break;

            case 'clean':
                $result = $this->stepCleanUpdate();
                break;

            case 'finished':
                $result = $this->stepFinishedUpdate();
                break;
        }

        /* درصد پیشرفت */
        $result = array_merge($result, ['percentage' => $this->getUpdateProgressBar($result['step'] ?? $currentStep)]);

        return back()->with('back_response', $result);
    }


    public function downloadFile(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filePath = request('filePath');
        if (!$filePath) abort(404);
        return Storage::disk('local')->download($filePath);
    }


    public function deleteFile(): RedirectResponse
    {
        $filePath = request('filePath');
        if (!$filePath) abort(404);
        try {
            Storage::disk('local')->delete($filePath);
            return back()->with('success', 'فایل با موفقیت حذف شد');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در حذف فایل: ' . $e->getMessage());
        }
    }


    /* ==============================================================================================*/
    /*                                 Helpers                                                       */
    /* ==============================================================================================*/

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
        return 'backup_for_user_' . auth()->id();
    }

    private function getUpdateProgressBar(string $step): float
    {
        $currentIndex = array_search($step, $this->steps, true);
        $totalSteps = count($this->steps);
        $percentage = ($currentIndex / ($totalSteps - 1)) * 100;

        return round($percentage, 2);
    }

    private function getTempPath(string $path = ''): string
    {
        return storage_path('app/tmp' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    /* ==============================================================================================*/
    /*                                 Steps                                                         */
    /* ==============================================================================================*/

    /**
     * مرحله ۱: آماده‌سازی اولیه
     */
    private function stepStartBackup(): array
    {
        // اگر پوشه tmp موجود نبود، ایجادش کن
        if (!is_dir($this->getTempPath())) {
            mkdir($this->getTempPath(), 0755, true);
        }

        Setting::set('backup_running', true);

        // گام بعد
        $nextStep = 'check_requirements';
        $this->setCurrentStep($nextStep);

        return [
            'success' => true,
            'message' => 'در حال آماده‌سازی برای ایجاد فایل پشتیبان...',
            'step' => 'start',
            'next_step' => $nextStep,
        ];
    }

    /**
     * مرحله ۲: بررسی پیش‌نیازها
     */
    private function stepCheckRequirements(): array
    {
        $isMaintenanceMode = Setting::get('maintenance_mode', false);


        $fileSetting = Setting::get('backup_file_setting', ['storage' => 'local', 'type' => 'all']);

        if ($isMaintenanceMode) {
            Setting::set('backup_running', false);

            return [
                'success' => false,
                'message' => 'خطا: سیستم در حالت به‌روزرسانی است. بعداً تلاش کنید.',
                'step' => 'check_requirements',
                'next_step' => null,
            ];
        }

        $nextStep = $fileSetting['type'] === 'files' ? 'zipping_files' : 'dumping_database';
        $nextStepMessage = $fileSetting['type'] === 'files'
            ? 'در حال فشرده‌سازی فایل‌ها...'
            : 'در حال پشتیبان‌گیری از دیتابیس...';

        $this->setCurrentStep($nextStep);

        return [
            'success' => true,
            'message' => $nextStepMessage,
            'step' => 'check_requirements',
            'next_step' => $nextStep,
        ];
    }

    /**
     * مرحله ۳: گرفتن Dump دیتابیس MySQL با spatie/db-dumper
     */
    private function stepDumpingDatabase(): array
    {
        $dumpPath = $this->getTempPath('dump.sql');
        $dbConfig = config('database.connections.mysql');

        try {
            MySql::create()
                ->setDbName($dbConfig['database'])
                ->setUserName($dbConfig['username'])
                ->setPassword($dbConfig['password'])
                ->setHost($dbConfig['host'])
                ->setPort($dbConfig['port'] ?? 3306)
                ->dumpToFile($dumpPath);


            $nextStep = 'zipping_files';
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال فشرده‌سازی فایل‌ها...',
                'step' => 'dumping_database',
                'next_step' => $nextStep,
            ];
        } catch (\Throwable $e) {
            Setting::set('backup_running', false);

            return [
                'success' => false,
                'message' => 'خطا در پشتیبان‌گیری از دیتابیس: ' . $e->getMessage(),
                'step' => 'dumping_database',
                'next_step' => null,
            ];
        }
    }

    /**
     * مرحله ۴: فشرده‌سازی با ZipArchive
     */
    private function stepZippingFiles(): array
    {
        $fileSetting = Setting::get('backup_file_setting', ['storage' => 'local', 'type' => 'all']);
        $zipName = config("app.name") . '-backup-' . now()->format('Y-m-d_H-i-s') . '.zip';
        $zipPath = $this->getTempPath($zipName);

        $zip = new ZipArchive();

        try {
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('قادر به ایجاد فایل زیپ نیست');
            }

            // 1) افزودن dump دیتابیس در صورت لزوم
            if (in_array($fileSetting['type'], ['all', 'database'])) {
                $dumpPath = $this->getTempPath('dump.sql');
                if (file_exists($dumpPath)) {
                    $zip->addFile($dumpPath, 'database/dump.sql');
                }
            }

            // 2) افزودن فایل‌های پروژه در صورت لزوم (غیر از node_modules و storage)
            if (in_array($fileSetting['type'], ['all', 'files'])) {
                $basePath = base_path();
                $basePathLen = strlen($basePath) + 1; // +1 for trailing slash

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $fileInfo) {
                    $filePath = $fileInfo->getPathname();

                    // فیلتر پوشه‌های حذف‌شده
                    if (str_contains($filePath, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) ||
                        str_contains($filePath, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
                        continue;
                    }

                    if ($fileInfo->isFile()) {
                        $localName = substr($filePath, $basePathLen);
                        $zip->addFile($filePath, 'files/' . $localName);
                    }
                }
            }

            // پایان
            $zip->close();

            // مسیر کامل فایل زیپ را فعلاً نگه می‌داریم در Cache برای مراحل بعدی
            Cache::put($this->getCacheKey() . '_zip_path', $zipPath, 3600);
            Cache::put($this->getCacheKey() . '_zip_name', $zipName, 3600);

            $nextStep = 'save_backup';
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال ذخیره فایل پشتیبان...',
                'step' => 'zipping_files',
                'next_step' => $nextStep,
            ];
        } catch (\Throwable $e) {
            $zip->close();
            Setting::set('backup_running', false);

            return [
                'success' => false,
                'message' => 'خطا در فشرده‌سازی فایل‌ها: ' . $e->getMessage(),
                'step' => 'zipping_files',
                'next_step' => null,
            ];
        }
    }

    /**
     * مرحله ۵: ذخیره فایل بکاپ بر روی دیسک انتخابی
     */
    private function stepSaveBackupFile(): array
    {
        $fileSetting = Setting::get('backup_file_setting', ['storage' => 'local', 'type' => 'all']);
        $diskName = $fileSetting['storage'] ?? 'local';

        $zipPath = Cache::get($this->getCacheKey() . '_zip_path');
        $zipName = Cache::get($this->getCacheKey() . '_zip_name');

        if (!$zipPath || !file_exists($zipPath)) {
            Setting::set('backup_running', false);

            return [
                'success' => false,
                'message' => 'فایل زیپ یافت نشد!',
                'step' => 'save_backup',
                'next_step' => null,
            ];
        }

        try {
            $stream = fopen($zipPath, 'r');
            $remoteKey = 'backups/' . $zipName;

            // ذخیره در دیسک انتخابی
            Storage::disk($diskName)->put($remoteKey, $stream);
            fclose($stream);

            // اگر مقصد لوکال نیست، یک نسخه در لوکال هم نگه داریم برای دسترسی سریع
            if ($diskName !== 'local') {
                Storage::disk('local')->putFileAs('backups', $zipPath, $zipName);
            }

            $nextStep = 'clean';
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال پاکسازی فایل‌های موقت...',
                'step' => 'save_backup',
                'next_step' => $nextStep,
            ];
        } catch (\Throwable $e) {
            Setting::set('backup_running', false);

            return [
                'success' => false,
                'message' => 'خطا در ذخیره‌سازی فایل پشتیبان: ' . $e->getMessage(),
                'step' => 'save_backup',
                'next_step' => null,
            ];
        }
    }

    /**
     * مرحله ۶: پاکسازی فایل‌ها و فولدر موقت
     */
    private function stepCleanUpdate(): array
    {
        try {
            // پاک کردن فولدر tmp
            if (is_dir($this->getTempPath())) {
                File::deleteDirectory($this->getTempPath());
            }

            $nextStep = 'finished';
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال اتمام فرآیند پشتیبان‌گیری...',
                'step' => 'clean',
                'next_step' => $nextStep,
            ];
        } catch (\Throwable $e) {
            Setting::set('backup_running', false);

            return [
                'success' => false,
                'message' => 'خطا در حذف فایل‌های موقت: ' . $e->getMessage(),
                'step' => 'clean',
                'next_step' => null,
            ];
        }
    }

    /**
     * مرحله ۷: اعلان پایان به کاربر و تنظیم فلگ‌ها
     */
    private function stepFinishedUpdate(): array
    {
        // پایان پروسه
        Setting::set('backup_running', false);

        // ایمیل به کاربر لاگین کرده (اگر ایمیل موجود است)
        try {
            $user = auth()->user();
            if ($user && $user->email) {
                $zipName = Cache::get($this->getCacheKey() . '_zip_name');
                // Mail::to($user->email)->send(new BackupFinishedMail($zipName));
            }
        } catch (\Throwable $e) {
            // خطای ایمیل را لاگ می‌کنیم ولی فرآیند را شکست‌خورده اعلام نمی‌کنیم
            Log::warning('Email send failed after backup: ' . $e->getMessage());
        }

        // پاکسازی مقادیر کمکی Cache
        Cache::forget($this->getCacheKey() . '_zip_path');
        Cache::forget($this->getCacheKey() . '_zip_name');
        Cache::forget($this->getCacheKey());

        return [
            'success' => true,
            'message' => 'پشتیبان‌گیری با موفقیت انجام شد.',
            'step' => 'finished',
            'next_step' => null,
        ];
    }
}
