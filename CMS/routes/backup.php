<?php

use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\RestoreController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'backup', 'middleware' => ['auth']], static function () {
    Route::get('/',               [BackupController::class, 'index'          ])->name('admin.backup.list');
    Route::get('/run',            [BackupController::class, 'runBackupPage'        ])->name('admin.backup.run.page');
    Route::post('/run',           [BackupController::class, 'runBackup'    ])->name('admin.backup.run');

    Route::post('/update-file-setting',     [BackupController::class, 'updateFileSetting'    ])->name('admin.backup.setting.file');
    Route::post('/update-schedule-setting', [BackupController::class, 'updateScheduleSetting'])->name('admin.backup.setting.schedule');
    Route::post('/update-storage-setting',  [BackupController::class, 'updateStorageSetting' ])->name('admin.backup.setting.storage');

    Route::get('/download',  [BackupController::class, 'downloadFile'])->name('admin.backup.download');
    Route::delete('/delete', [BackupController::class, 'deleteFile' ])->name('admin.backup.delete');
});
