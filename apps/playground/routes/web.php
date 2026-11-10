<?php

use App\Vanda\Screens\DashboardScreen;
use App\Vanda\Screens\FileBrowserScreen;
use App\Vanda\Screens\PageEditScreen;
use App\Vanda\Screens\PageListScreen;
use App\Vanda\Screens\RoleEditScreen;
use App\Vanda\Screens\RoleListScreen;
use App\Vanda\Screens\ShowcaseScreen;
use App\Vanda\Screens\UserEditScreen;
use App\Vanda\Screens\UserListScreen;
use Illuminate\Support\Facades\Route;
use Vanda\Activity\ActivityListScreen;
use Vanda\Activity\ActivityScreen;
use Vanda\Settings\SettingsExtension;

Route::redirect('/', '/admin');

Route::vanda('admin', function () {
    Route::screen('/', DashboardScreen::class)->name('admin.dashboard');
    Route::screen('users', UserListScreen::class)->name('admin.users');
    Route::screen('users/create', UserEditScreen::class)->name('admin.users.create');
    Route::screen('users/{user}/edit', UserEditScreen::class)->name('admin.users.edit');
    Route::screen('roles', RoleListScreen::class)->name('admin.roles');
    Route::screen('roles/create', RoleEditScreen::class)->name('admin.roles.create');
    Route::screen('roles/{role}/edit', RoleEditScreen::class)->name('admin.roles.edit');
    Route::screen('pages', PageListScreen::class)->name('admin.pages');
    Route::screen('pages/create', PageEditScreen::class)->name('admin.pages.create');
    Route::screen('pages/{page}/edit', PageEditScreen::class)->name('admin.pages.edit');
    Route::screen('showcase', ShowcaseScreen::class)->name('admin.showcase');
    Route::screen('files', FileBrowserScreen::class)->name('admin.files');
    Route::screen('files/{folder}', FileBrowserScreen::class)->name('admin.files.folder');
    Route::screen('activity', ActivityListScreen::class)->name('admin.activity');
    Route::screen('activity/{activity}', ActivityScreen::class)->name('admin.activity.show');
    Route::extension(SettingsExtension::class);   // /admin/settings, /admin/settings/site, /admin/settings/mail
});
