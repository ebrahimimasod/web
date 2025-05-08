<?php

namespace App\Http\Controllers\Admin;


use App\Facades\Setting;
use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class BackupController extends Controller
{

    public array $steps = [
        'start',
        'check_version',
        'download',
        'extract',
        'migrate',
        'clean',
        'finished',
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
        Setting::set("backup_running", true);

        return Inertia::render('backup/run', [
            'backup_running' => Setting::get("backup_running", false),
        ]);

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

            // check_version
            case $this->steps[1]:

                $result = $this->stepCheckVersionUpdate();

                break;

            // download
            case $this->steps[2]:

                $result = $this->stepDownloadUpdate();

                break;

            //extract
            case $this->steps[3]:

                $result = $this->stepExtractUpdate();

                break;

            //migrate
            case $this->steps[4]:

                $result = $this->stepMigrateUpdate();

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
        return 'update_for_user_' . auth()->id();
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
        //TODO::if backup not exists (notify user to make a backup)
        Setting::set("maintenance_mode", 'on');
        $nextStep = $this->steps[1];
        $this->setCurrentStep($nextStep);

        return [
            'success' => true,
            'message' => 'در حال فعال‌کردن حالت ‌به‌روزرسانی سایت...',
            'step' => $this->steps[0],
            'next_step' => $nextStep,
        ];
    }

    private function stepCheckVersionUpdate(): array
    {
        sleep(3);
        $lastVersion = '3.0.0';
        $currentVersion = '2.0.0';

        if ($lastVersion == $currentVersion) {
            Setting::set("maintenance_mode", 'off');
            return [
                'success' => false,
                'message' => 'خطا: شما قبلا آخرین نسخه سایت را نصب کرده‌اید.',
                'step' => $this->steps[1],
                'next_step' => null,
            ];
        }

        $nextStep = $this->steps[2];
        $this->setCurrentStep($nextStep);
        return [
            'success' => true,
            'message' => 'در حال بررسی نسخه فعلی سایت...',
            'step' => $this->steps[1],
            'next_step' => $nextStep,
        ];
    }

    private function stepDownloadUpdate(): array
    {
        $fileUrl = 'http://localhost/update/update.zip';
        $destinationPath = storage_path('app/updates');
        $fileName = 'update.zip';


        try {

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }


            Http::timeout(300)->sink("{$destinationPath}/{$fileName}")->get($fileUrl);

            $nextStep = $this->steps[3];
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال دانلود فایل به‌روزرسانی...',
                'step' => $this->steps[2],
                'next_step' => $nextStep,
            ];

        } catch (\Exception $e) {
            Setting::set("maintenance_mode", 'off');
            return [
                'success' => false,
                'message' => 'خطا در دانلود فایل به‌روزرسانی: ' . $e->getMessage(),
                'step' => $this->steps[2],
                'next_step' => null,
            ];

        }
    }

    private function stepExtractUpdate(): array
    {
        $zipPath = storage_path('app/updates/update.zip');        // مسیر فایل زیپ
        $extractTo = storage_path('app/updates/unzipped');        // محل استخراج
        $targetPath = base_path();                                // مسیر ریشه پروژه

        try {
            if (!File::exists($zipPath)) {
                return [
                    'success' => false,
                    'message' => 'فایل به‌روزرسانی پیدا نشد.',
                    'step' => $this->steps[3],
                    'next_step' => null,
                ];
            }

            if (!File::exists($extractTo)) {
                File::makeDirectory($extractTo, 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($extractTo);
                $zip->close();
            } else {
                return [
                    'success' => false,
                    'message' => 'خطا در باز کردن فایل ZIP.',
                    'step' => $this->steps[3],
                    'next_step' => null,
                ];
            }


            $files = File::allFiles($extractTo);
            foreach ($files as $file) {
                $relativePath = str_replace($extractTo . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $destination = $targetPath . DIRECTORY_SEPARATOR . $relativePath;

                // ساخت مسیر مقصد اگر وجود ندارد
                $destDir = dirname($destination);
                if (!File::exists($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }


                File::copy($file->getPathname(), $destination);
            }

            $nextStep = $this->steps[4];
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال استخراج فایل به‌روزرسانی...',
                'step' => $this->steps[3],
                'next_step' => $nextStep,
            ];
        } catch (\Exception $e) {
            Setting::set("maintenance_mode", 'off');
            return [
                'success' => false,
                'message' => 'خطا در استخراج فایل‌ها: ' . $e->getMessage(),
                'step' => $this->steps[3],
                'next_step' => null,
            ];
        }
    }

    private function stepMigrateUpdate(): array
    {
        try {
            Artisan::call('migrate', ['--force' => true]);

            $nextStep = $this->steps[5];
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال اعمال تغییرات در دیتابیس...',
                'step' => $this->steps[4],
                'next_step' => $nextStep,
            ];
        } catch (\Exception $e) {
            Setting::set("maintenance_mode", 'off');
            return [
                'success' => false,
                'message' => 'خطا در اعمال تغییرات دیتابیس: ' . $e->getMessage(),
                'step' => $this->steps[4],
                'next_step' => null,
            ];
        }
    }

    private function stepCleanUpdate(): array
    {
        $updateZipPath = storage_path('app/updates/update.zip');
        $unzippedPath = storage_path('app/updates/unzipped');

        try {
            if (File::exists($updateZipPath)) {
                File::delete($updateZipPath);
            }

            if (File::exists($unzippedPath)) {
                File::deleteDirectory($unzippedPath);
            }

            $nextStep = $this->steps[6];
            $this->setCurrentStep($nextStep);

            return [
                'success' => true,
                'message' => 'در حال پاک کردن فایل های موقت ...',
                'step' => $this->steps[5],
                'next_step' => $nextStep,
            ];
        } catch (\Exception $e) {
            Setting::set("maintenance_mode", 'off');
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
        Cache::forget($this->getCacheKey());
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Setting::set('app_version', '3.0.0');
        Setting::set("maintenance_mode", 'off');

        return [
            'success' => true,
            'message' => 'فرایند به‌روزرسانی به اتمام رسید.',
            'step' => $this->steps[6],
            'next_step' => null,
        ];
    }

}
