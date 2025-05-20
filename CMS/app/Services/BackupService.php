<?php

namespace App\Services;

use App\Facades\Setting;
use App\Models\Task;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BackupService
{

    const KEY_NAME = 'backup-key';
    const CACHE_LIFETIME = 5;//h
    const ZIP_CHUNK_SIZE = 50 * 1024 * 1024; //50MB

    private TaskService $taskService;
    private string $currentStep = 'start';
    private array $steps = [
        'start',                // آماده‌سازی اولیه
        'check_requirements',   // بررسی پیش‌نیازها
        'dumping_database',     // ایجاد dump دیتابیس
        'zipping_files',        // ساخت زیپ‌های چندگانه ≤ 50MB
        'packaging_parts',      // قراردادن تمام زیپ‌ها در یک فایل واحد
        'save_backup',          // آپلود روی دیسک انتخابی
        'clean',                // پاکسازی
        'finished',             // پایان
    ];


    public function __construct()
    {
        $keyValue = Cache::remember(self::KEY_NAME, now()->addHours(self::CACHE_LIFETIME), fn() => Str::uuid()->toString());
        $this->taskService = new TaskService($keyValue, [
            Task::COL_STEP => $this->steps[0],
            Task::COL_NAME => ' تسک فرایند پشتیبان‌گیری',
            Task::COL_TYPE => Task::TYPE_BACKUP,
            Task::COL_STATUS => Task::STATUS_PROCESSING
        ]);
        $this->currentStep = $this->taskService->task->{Task::COL_STEP};
    }

    private function getTempPath(string $rel = ''): string
    {
        return storage_path('app/tmp' . ($rel ? DIRECTORY_SEPARATOR . $rel : ''));
    }

    private function setCurrentStep(string $step): void
    {
        $this->taskService->updateTaskStep($step);
        $this->currentStep = $step;
    }

    private function rollback(?string $errorMessage = null): void
    {
        Setting::set('backup_running', false);
        Cache::forget(self::KEY_NAME);
        //TODO::clear tmp files

        $this->taskService->updateTask([
            Task::COL_ERRORS => $errorMessage,
            Task::COL_STATUS => Task::STATUS_FAILED,
        ]);
    }

    private function getBackupFileSetting(?string $key = null): mixed
    {
        $setting = Setting::get('backup_file_setting', [
            "type" => "all",
            "storage" => "local"
        ]);

        if (!empty($key)) {
            return data_get($setting, $key);
        }

        return $setting;
    }

    private function getBackupFileStorage(): array
    {
        $connections = collect(Setting::get('backup_storage_setting', []))
            ->filter(fn($p) => $p['enabled'] && $p['key'] != 'local')
            ->toArray();


        $disks = [];

        foreach ($connections as $i => $conn) {

            switch ($conn['key']) {

                case 'local':
                    $disks[$conn['key']] = [
                        'driver' => $conn['key'],
                    ];
                    break;

                case 'ftp':
                    $config = $conn['config'];
                    $disks['ftp'] = [
                        'driver' => 'ftp',
                        'host' => $config['host'],
                        'username' => $config['username'],
                        'password' => $config['password'],
                        'root' => $config['root'] ?? '/',
                        'port' => $config['port'] ?? 21,
                        'ssl' => (bool)($config['ssl'] ?? false),
                        'timeout' => 9999,
                    ];
                    break;

                case 'sftp':
                    $disks['sftp'] = [
                        'driver' => 'sftp',
                        'host' => $conn['host'],
                        'username' => $conn['username'],
                        'password' => $conn['password'] ?? null,
                        'privateKey' => $conn['private_key'] ?? null,
                        'root' => $conn['root'] ?? '/',
                        'port' => $conn['port'] ?? 22,
                        'timeout' => 9999,
                    ];
                    break;

                case 's3':
                    $disks['s3'] = [
                        'driver' => 's3',
                        'key' => $conn['key'],
                        'secret' => $conn['secret'],
                        'region' => $conn['region'],
                        'bucket' => $conn['bucket'],
                        'endpoint' => $conn['endpoint'] ?? null,
                        'use_path_style_endpoint' => (bool)($conn['path_style'] ?? false),
                    ];
                    break;

            }
        }

        return $disks;
    }

    private function isDatabaseInBackup(): bool
    {
        return in_array($this->getBackupFileSetting('type'), ['all', 'database'], true);
    }

    private function isFilesInBackup(): bool
    {
        return in_array($this->getBackupFileSetting('type'), ['all', 'files'], true);
    }


    public function run(): \Illuminate\Http\RedirectResponse
    {
        try {

            $result = match ($this->currentStep) {
                'start' => $this->stepStart(),
                'check_requirements' => $this->stepCheckRequirements(),
                'dumping_database' => $this->stepDumpingDatabase(),
                'zipping_files' => $this->stepZippingFiles(),
                'packaging_parts' => $this->stepPackagingParts(),
                'save_backup' => $this->stepSaveBackupFile(),
                'clean' => $this->stepCleanUpdate(),
                default => [
                    ''
                ],
            };

            return redirect()->back()->with('success', $result);

        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }


    /*
     *   Step 1 :
     * - Turn on backup status
     * - Create tmp folders
     * - Update next step
     */
    private function stepStart(): array
    {
        if (!is_dir($this->getTempPath())) mkdir($this->getTempPath(), 0755, true);
        Setting::set('backup_running', true);
        $this->setCurrentStep('check_requirements');
        return [
            'success' => true,
            'message' => 'در حال آماده‌سازی...',
            'step' => 'start',
            'next_step' => 'check_requirements'
        ];
    }


    /*
     *  Step 2 :
     * - Check maintenance mode
     * - TODO::Check Disk size (grater than 500 MB)
     */
    private function stepCheckRequirements(): array
    {
        if (Setting::get('maintenance_mode', false)) {
            $this->rollback('سایت در حالت به‌روزرسانی است');
            return [
                'success' => false,
                'message' => 'سایت در حالت به‌روزرسانی است',
                'step' => 'check_requirements'
            ];
        }

        $next = $this->isDatabaseInBackup() ? 'dumping_database' : 'zipping_files';
        $msg = $next === 'zipping_files' ? 'در حال تهیه پشتیبان از فایل‌ها...' : 'در حال تهیه‌ی بکاپ دیتابیس...';
        $this->setCurrentStep($next);
        return [
            'success' => true,
            'message' => $msg,
            'step' => 'check_requirements',
            'next_step' => $next
        ];
    }


    /*
     *  Step3 :
     * - dumping database
     */
    private function stepDumpingDatabase(): array
    {
        $dump = $this->getTempPath('dump.sql');
        try {
            $this->taskService->dbDumper($dump);
            $this->setCurrentStep('zipping_files');
            return [
                'success' => true,
                'message' => 'فشرده‌سازی فایل‌ها...',
                'step' => 'dumping_database',
                'next_step' => 'zipping_files'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطا در بکاپ دیتابیس: ' . $e->getMessage(),
                'step' => 'dumping_database'
            ];
        }
    }

    /*
     * Step4 :
     * - Sipping root files and dirs.
     */
    private function stepZippingFiles(): array
    {
        try {
            $this->taskService->zippingRootFiles([
                'filename' => Str::slug(config('app.name')) . '-backup-' . now()->format('Y-m-d_H-i-s'),
                'database_dump_path' => $this->isDatabaseInBackup() ?  $this->getTempPath('dump.sql') : null,
                'include_files' => $this->isFilesInBackup(),
                'zip_chunk_size' => self::ZIP_CHUNK_SIZE,
            ]);
            $this->setCurrentStep('packaging_parts');

            return [
                'success' => true,
                'message' => 'در حال بسته‌بندی پارت‌ها...',
                'step' => 'zipping_files',
                'next_step' => 'packaging_parts',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ایجاد فایل: ' . $e->getMessage(),
                'step' => 'zipping_files'
            ];
        }


    }


    /*
     * Step 5
     * - convert all zip files to one file
     */
    private function stepPackagingParts(): array
    {
        try {
            $this->taskService->packagingZipParts();
            $this->setCurrentStep('save_backup');
            return [
                'success' => true,
                'message' => 'در حال ذخیره پشتیبان...',
                'step' => 'packaging_parts',
                'next_step' => 'save_backup'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطا در بسته‌بندی: ' . $e->getMessage(),
                'step' => 'packaging_parts'
            ];
        }
    }


    /*
   * Step 6
   * - save backup files in storage
   */
    private function stepSaveBackupFile(): array
    {
        try {
            $this->taskService->saveFileOnStorages('backups', $this->getBackupFileStorage());

            $this->setCurrentStep('clean');
            return [
                'success' => true,
                'message' => 'پاکسازی فایل‌های موقت...',
                'step' => 'save_backup',
                'next_step' => 'clean'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطا در ذخیره: ' . $e->getMessage(),
                'step' => 'save_backup'
            ];
        }
    }

    /*
     * Step 7
     */
    private function stepCleanUpdate(): array
    {
        return [
            'success' => true,
            'message' => 'تمام',
            'step' => 'clean',
        ];
    }


}
