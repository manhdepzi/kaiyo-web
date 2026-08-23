<?php

use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\RevokeAccountSessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/ready', ReadinessController::class)
    ->name('health.ready');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/account', 'account.index')->name('account');
    Route::get('/account/security', AccountSecurityController::class)->name('account.security');
    Route::delete('/account/security/sessions/{session}', RevokeAccountSessionController::class)
        ->name('account.security.sessions.destroy');
});
