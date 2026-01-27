<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GameTables\Http\Controllers\CampaignController;
use Modules\GameTables\Http\Controllers\GameTableController;
use Modules\GameTables\Http\Controllers\RegistrationController;

/*
|--------------------------------------------------------------------------
| GameTables Module Web Routes
|--------------------------------------------------------------------------
*/

// Game tables routes
Route::prefix('mesas')->name('gametables.')->group(function (): void {
    Route::get('/', [GameTableController::class, 'index'])->name('index');
    Route::get('/calendario', [GameTableController::class, 'calendar'])->name('calendar');

    // Cancellation by token (public, no auth required)
    Route::get('/cancelar/{token}', [RegistrationController::class, 'showCancelConfirmation'])
        ->name('cancel-confirmation');
    Route::delete('/cancelar/{token}', [RegistrationController::class, 'cancelByToken'])
        ->name('cancel-by-token');

    Route::get('/{id}', [GameTableController::class, 'show'])->name('show');

    // Guest registration (no auth required)
    Route::post('/{id}/inscripcion-invitado', [RegistrationController::class, 'registerGuest'])
        ->name('register-guest');

    // Registration routes (authenticated)
    Route::middleware('auth')->group(function (): void {
        Route::post('/{id}/inscripcion', [RegistrationController::class, 'register'])->name('register');
        Route::delete('/{id}/inscripcion', [RegistrationController::class, 'cancel'])->name('cancel');
    });
});

// Campaigns routes (separate from mesas - a campaign can have multiple mesas)
Route::prefix('campanas')->name('campaigns.')->group(function (): void {
    Route::get('/', [CampaignController::class, 'index'])->name('index');
    Route::get('/{id}', [CampaignController::class, 'show'])->name('show');
});
