<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Setting;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class BackupController
{
    public ?BackupService $backupService = null;

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

    public function runBackupPage(): \Inertia\Response
    {
        return Inertia::render('backup/run');
    }


    public function runBackup(): RedirectResponse
    {
        if (!$this->backupService) {
            $this->backupService = new BackupService();
        }

       return $this->backupService->run();
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
        Setting::set('backup_storage_setting', request('connections'));

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
}
