<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;


class UpdateController extends Controller
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
        //TODO: fetch all version and current version of cms core from my server.
        $currentVersion = '2.0.0';
        $versions = [
            [
                'version' => '3.0.0',
                'title' => 'سریعتر از همیشه',
                'released_at' => now(),
                'logs' => [
                    '<span>تغییر <b class="text-primary">سرعت</b> و <b class="text-primary">امنیت</b> بیشتر پنل ادمین مدیریت محتوا</span>',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                ]
            ],
            [
                'version' => '2.0.0',
                'title' => 'قدرتمندتر از همیشه',
                'released_at' => now()->addDays(-15),
                'logs' => [
                    '<span>تغییر <b class="text-primary">سرعت</b> و <b class="text-primary">امنیت</b> بیشتر پنل ادمین مدیریت محتوا</span>',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                ]
            ],
            [
                'version' => '1.0.0',
                'title' => 'اولین نسخه پایدار',
                'released_at' => now()->addDays(-15),
                'logs' => [
                    '<span>تغییر <b class="text-primary">سرعت</b> و <b class="text-primary">امنیت</b> بیشتر پنل ادمین مدیریت محتوا</span>',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                    'رفع باگ های گزارش شده',
                ]
            ],
        ];
        $isUpdated = $currentVersion == $versions[0]['version'];


        return Inertia::render('update/versions', [
            'versions' => $versions,
            'currentVersion' => $currentVersion,
            'isUpdated' => $isUpdated,
        ]);
    }

    public function runUpdate(): \Inertia\Response
    {
        $currentVersion = "1.0.0";
        $lastVersion = '3.0.0';

        return Inertia::render('update/run', [
            'currentVersion' => $currentVersion,
            'lastVersion' => $lastVersion,
        ]);

    }

    public function performUpdateStep(): \Illuminate\Http\RedirectResponse
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
        $nextStep = $this->steps[1];
        $this->setCurrentStep($nextStep);

        return [
            'success' => true,
            'message' => 'در حال شروع به‌روزرسانی نرم‌افزار...',
            'step' => $this->steps[0],
            'next_step' => $nextStep,
        ];
    }

    private function stepCheckVersionUpdate(): array
    {
        $lastVersion = '3.0.0';
        $currentVersion = '3.0.0';

        if ($lastVersion == $currentVersion) {
            return [
                'success' => false,
                'message' => 'شما قبلا آخرین نسخه نرم‌افزار را نصب کرده‌اید.',
                'step' => $this->steps[1],
                'next_step' => null,
            ];
        }

        $nextStep = $this->steps[2];
        $this->setCurrentStep($nextStep);
        return [
            'success' => true,
            'message' => 'در حال بررسی  نسخه فعلی نرم‌افزار...',
            'step' => $this->steps[1],
            'next_step' => $nextStep,
        ];
    }


    private function stepDownloadUpdate(): array
    {
        $nextStep = $this->steps[3];
        $this->setCurrentStep($nextStep);
        return [
            'success' => true,
            'message' => 'در حال دانلود فایل به‌روزرسانی...',
            'step' => $this->steps[2],
            'next_step' => $nextStep,
        ];
    }

    private function stepExtractUpdate(): array
    {
        $nextStep = $this->steps[4];
        $this->setCurrentStep($nextStep);
        return [
            'success' => true,
            'message' => 'در حال بازگشایی فایل به‌روزرسانی...',
            'step' => $this->steps[3],
            'next_step' => $nextStep,
        ];
    }

    private function stepMigrateUpdate(): array
    {
        $nextStep = $this->steps[5];
        $this->setCurrentStep($nextStep);
        return [
            'success' => true,
            'message' => 'در حال اعمال تغییرات دیتابیس...',
            'step' => $this->steps[4],
            'next_step' => $nextStep,
        ];
    }

    private function stepCleanUpdate(): array
    {
        $nextStep = $this->steps[6];
        $this->setCurrentStep($nextStep);
        return [
            'success' => true,
            'message' => 'در حال پاک سازی فایل های موقت ...',
            'step' => $this->steps[5],
            'next_step' => $nextStep,
        ];
    }

    private function stepFinishedUpdate(): array
    {

        Cache::forget($this->getCacheKey());

        return [
            'success' => true,
            'message' => 'فرایند به‌روزرسانی به اتمام رسید.',
            'step' => $this->steps[6],
            'next_step' => null,
        ];
    }

}
