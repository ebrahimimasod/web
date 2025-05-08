<?php
namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

class BackupService
{

    public function run(): string
    {
        $settingService    = app('setting');
        $backupFileSetting = $settingService->get('backup_file_setting');
        $content           = $backupFileSetting['type'] ?? 'files+database';
        $destination       = $backupFileSetting['storage'] ?? 'local';


        Config::set('backup.backup.destination.disks', [$destination]);

        // ۳. تعیین کنید که فقط فایل، فقط دیتابیس یا هر دو
        $options = [];
        if ($content === 'files') {
            $options['--only-files'] = true;
        } elseif ($content === 'database') {
            $options['--only-db'] = true;
        }

        // ۴. اجرای فرمان بک‌آپ
        Artisan::call('backup:run', $options);

        // ۵. خروجی را برگردانید تا نمایش دهید
        return Artisan::output();
    }
}
