<?php

use App\Vanda\Screens\UserEditScreen;
use App\Vanda\Screens\UserListScreen;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/users');

Route::prefix('admin')->group(function () {
    Route::screen('users', UserListScreen::class)->name('admin.users');
    Route::screen('users/create', UserEditScreen::class)->name('admin.users.create');
    Route::screen('users/{user}/edit', UserEditScreen::class)->name('admin.users.edit');
});
