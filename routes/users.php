<?php

use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'users', 'middleware' => ['auth']], function () {
    Route::get("/", [AdminUserController::class, 'index'])->name('admin.users.list');
    Route::get("/create", [AdminUserController::class, 'create'])->name('admin.users.create');
    Route::post("/store", [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::get("/edit/{id}", [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::post("/update/{id}", [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::get("/show/{id}", [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::delete("/delete/{id}", [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
});
