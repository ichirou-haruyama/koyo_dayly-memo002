<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Middleware\EnsureAdminRole;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
                ? ['password.confirm']
                : []
        )
        ->name('two-factor.show');

    // Reports (Daily Reports)
    Volt::route('reports', 'reports.index')->name('reports.index');
    Volt::route('reports/create', 'reports.create')->name('reports.create');

    // Admin: Daily Reports
    Route::prefix('admin')->name('admin.')->middleware(EnsureAdminRole::class)->group(function () {
        Volt::route('reports', 'admin.reports.index')
            ->name('reports.index');

        Route::get('reports/export', [ReportExportController::class, 'export'])
            ->name('reports.export');
    });
});
