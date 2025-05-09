<?php

namespace App\Http\Controllers\Admin;


use App\Facades\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class _BackupController extends Controller
{

    public array $steps = [
        'start', // update backup status and create temp folder if not exists
        'check_requirements', // check if update processing not running
        'dumping_database', // start dumping database
        'zipping_files', // start zipping files
        'save_backup', //save backup file in local or remote storage
        'clean', // clean temp files and folders
        'finished',// update backup status and notify user
    ];

    public function index(): \Inertia\Response
    {
        $backup_file_setting = app('setting')->get('backup_file_setting');
        $backup_schedule_setting = app('setting')->get('backup_schedule_setting');
        $backup_storage_setting = app('setting')->get('backup_storage_setting');
        $files = Storage::disk('local')->files('backups');


        return Inertia::render('backup/index', [
            'files' => $files,
            'backup_file_setting' => $backup_file_setting,
            'backup_schedule_setting' => $backup_schedule_setting,
            'backup_storage_setting' => $backup_storage_setting,
        ]);
    }

    public function updateScheduleSetting(): RedirectResponse
    {

        $validator = Validator::make(request()->all(), [
            'enabled' => [
                'required',
                'boolean',
            ],
            'schedule' => [
                'required',
                'string',
                "in:12_hours,daily,weekly,fortnightly,monthly"
            ],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        $data = $validator->validated();

        app('setting')->set('backup_schedule_setting', $data);

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
            'storage' => [
                'required',
                'string',
                "in:local,google_drive,drop_box,ftp,sftp"
            ],
            'type' => [
                'required',
                'string',
                'in:all,files,database'
            ],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        $data = $validator->validated();

        app('setting')->set('backup_file_setting', $data);
        return redirect()->back()->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }

    public function runBackup(): \Inertia\Response
    {
        return Inertia::render('backup/run');
    }

    public function performBackupStep(): \Illuminate\Http\RedirectResponse
    {
        $result = [];

        $currentStep = $this->getCurrentStep();

        switch ($currentStep) {

            //Start
            case $this->steps[0]:
                $result = $this->stepStartUpdate();
                break;

            // check_requirements
            case $this->steps[1]:

                $result = $this->stepCheckRequirements();

                break;

            // dumping_database
            case $this->steps[2]:

                $result = $this->stepDumpingDatabase();

                break;

            //zipping_files
            case $this->steps[3]:

                $result = $this->stepZippingFiles();

                break;

            //save_backup
            case $this->steps[4]:

                $result = $this->stepSaveBackupFile();

                break;

            //clean
            case $this->steps[5]:

                $result = $this->stepCleanUpdate();

                break;

            //finished
            case $this->steps[6]:
                $result = $this->stepFinishedUpdate();

                break;
        }

        $result = array_merge($result, ['percentage' => $this->getUpdateProgressBar($currentStep)]);
        return back()->with('back_response', $result);

    }

    private function getCurrentStep(): string
    {
        return Cache::get($this->getCacheKey(), $this->steps[0]);
    }

    private function setCurrentStep($step): void
    {
        Cache::set($this->getCacheKey(), $step, 3600);

    }

    private function getCacheKey(): string
    {
        return 'backup_for_user_' . auth()->id();
    }

    private function getUpdateProgressBar($step): float
    {
        $currentIndex = array_search($step, $this->steps);
        $totalSteps = count($this->steps);
        $percentage = ($currentIndex / ($totalSteps - 1)) * 100;
        return round($percentage, 2);
    }

    private function stepStartUpdate(): array
    {
        Setting::set("backup_running", true);
        $nextStep = $this->steps[1];
        $this->setCurrentStep($nextStep);

        return [
            'success' => true,
            'message' => 'در حال آماده سازی برای ایجاد فایل پشتیبان...',
            'step' => $this->steps[0],
            'next_step' => $nextStep,
        ];
    }

    private function stepCheckRequirements(): array
    {
        $isMaintenanceMode = Setting::get("maintenance_mode", false);
        $backupFileSetting = Setting::get('backup_file_setting',['storage' => 'local', 'type' => 'all']);

        if ($isMaintenanceMode) {
            Setting::set("backup_running", false);
            return [
                'success' => false,
                'message' => 'خطا: سیستم در حال به‌روزرسانی است لطفا بعدا تلاش کنید.',
                'step' => $this->steps[1],
                'next_step' => null,
            ];
        }

        $nextStep = $backupFileSetting['type'] == 'files' ?   $this->steps[3] : $this->steps[2];
        $nextStepMessage = $backupFileSetting['type'] == 'files' ? 'در حال فشرده‌سازی فایل‌ها...' : 'در حال پشتیبان‌گیری از دیتابیس...';
        $this->setCurrentStep($nextStep);
        return [
            'success' => true,
            'message' => $nextStepMessage,
            'step' => $this->steps[1],
            'next_step' => $nextStep,
        ];
    }

    private function stepDumpingDatabase(): array
    {
        try {
            //TODO::try to dump database

            $nextStep = $this->steps[3];
            $this->setCurrentStep($nextStep);
            return [
                'success' => true,
                'message' => 'در حال فشرده‌سازی فایل‌ها...',
                'step' => $this->steps[2],
                'next_step' => $nextStep,
            ];

        } catch (\Exception $e) {
            Setting::set("backup_running", false);
            return [
                'success' => false,
                'message' => 'خطا در پشتیبان‌گیری از دیتابیس' . $e->getMessage(),
                'step' => $this->steps[2],
                'next_step' => null,
            ];

        }
    }

    private function stepZippingFiles(): array
    {
        try {

            //TODO:: try to zip files

            $nextStep = $this->steps[4];
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال ذخیره فایل پشتیبان...',
                'step' => $this->steps[3],
                'next_step' => $nextStep,
            ];
        } catch (\Exception $e) {
            Setting::set("backup_running", false);
            return [
                'success' => false,
                'message' => 'خطا در فشرده سازی فایل ها ...: ' . $e->getMessage(),
                'step' => $this->steps[3],
                'next_step' => null,
            ];
        }
    }

    private function stepSaveBackupFile(): array
    {
        try {
            //TODO:: try to save backup file in local or remote storage

            $nextStep = $this->steps[5];
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال پاکسازی فایل های موقت...',
                'step' => $this->steps[4],
                'next_step' => $nextStep,
            ];
        } catch (\Exception $e) {
            Setting::set("backup_running", false);
            return [
                'success' => false,
                'message' => 'خطا در ذخیره سازی فایل پشتیبان: ' . $e->getMessage(),
                'step' => $this->steps[4],
                'next_step' => null,
            ];
        }
    }

    private function stepCleanUpdate(): array
    {
        try {
            //TODO:: try to clean temp files and folders

            $nextStep = $this->steps[6];
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال اتمام فرایند پشتیبان‌گیری...',
                'step' => $this->steps[5],
                'next_step' => $nextStep,
            ];
        } catch (\Exception $e) {
            Setting::set("backup_running", false);
            return [
                'success' => false,
                'message' => 'خطا در حذف فایل‌های موقت: ' . $e->getMessage(),
                'step' => $this->steps[5],
                'next_step' => null,
            ];
        }
    }

    private function stepFinishedUpdate(): array
    {
        //TODO:: try to notify user
        return [
            'success' => true,
            'message' => 'پشتیبان‌گیری با موفقیت انجام شد.',
            'step' => $this->steps[6],
            'next_step' => null,
        ];
    }

}
