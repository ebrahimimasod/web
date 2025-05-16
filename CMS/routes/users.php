<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'users', 'middleware' => ['auth']], function () {
    Route::get("/", [UserController::class, 'index'])->name('admin.users.list');
    Route::get("/create", [UserController::class, 'create'])->name('admin.users.create');
    Route::post("/store", [UserController::class, 'store'])->name('admin.users.store');
    Route::get("/edit/{id}", [UserController::class, 'edit'])->name('admin.users.edit');
    Route::post("/update/{id}", [UserController::class, 'update'])->name('admin.users.update');
    Route::get("/show/{id}", [UserController::class, 'show'])->name('admin.users.show');
    Route::delete("/delete/{id}", [UserController::class, 'destroy'])->name('admin.users.destroy');
});
