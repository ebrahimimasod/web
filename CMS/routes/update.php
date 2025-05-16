<?php

use App\Http\Controllers\Admin\UpdateController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'update', 'middleware' => ['auth']], function () {
    Route::get("/", [UpdateController::class, 'index'])->name('admin.update.versions');
    Route::get("/run", [UpdateController::class, 'runUpdate'])->name('admin.update.run.page');
    Route::post("/run", [UpdateController::class, 'performUpdateStep'])->name('admin.update.run');
});
