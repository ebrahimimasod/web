<?php

use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'users', 'middleware' => ['auth']], function () {
    Route::get("/", [AdminUserController::class, 'index'])->name('admin.users.list');
    Route::delete("/delete/{id}", [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
});
