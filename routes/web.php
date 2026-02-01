<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GameTables\Http\Controllers\CampaignController;
use Modules\GameTables\Http\Controllers\FrontendCampaignController;
use Modules\GameTables\Http\Controllers\FrontendGameTableController;
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

    // Frontend creation routes (authenticated) - MUST be before {slug} route
    Route::middleware('auth')->group(function (): void {
        Route::get('/crear', [FrontendGameTableController::class, 'create'])->name('create');
        Route::post('/crear', [FrontendGameTableController::class, 'store'])->name('store');
        Route::get('/mis-mesas', [FrontendGameTableController::class, 'myTables'])->name('my-tables');
        Route::get('/mis-mesas/{gameTable}/editar', [FrontendGameTableController::class, 'edit'])->name('edit');
        Route::put('/mis-mesas/{gameTable}', [FrontendGameTableController::class, 'update'])->name('update');
        Route::post('/mis-mesas/{gameTable}/enviar-revision', [FrontendGameTableController::class, 'submitForReview'])->name('submit-review');
        Route::delete('/mis-mesas/{gameTable}', [FrontendGameTableController::class, 'destroy'])->name('destroy');
    });

    // Guest registration (no auth required)
    Route::post('/{gameTable}/inscripcion-invitado', [RegistrationController::class, 'registerGuest'])
        ->name('register-guest');

    // Registration routes (authenticated)
    Route::middleware('auth')->group(function (): void {
        Route::post('/{gameTable}/inscripcion', [RegistrationController::class, 'register'])->name('register');
        Route::delete('/{gameTable}/inscripcion', [RegistrationController::class, 'cancel'])->name('cancel');
    });

    // Show route with slug - MUST be last to avoid matching specific routes like /crear
    Route::get('/{slug}', [GameTableController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('show');
});

// Campaigns routes (separate from mesas - a campaign can have multiple mesas)
Route::prefix('campanas')->name('campaigns.')->group(function (): void {
    Route::get('/', [CampaignController::class, 'index'])->name('index');

    // Frontend creation routes (authenticated)
    Route::middleware('auth')->group(function (): void {
        Route::get('/crear', [FrontendCampaignController::class, 'create'])->name('frontend-create');
        Route::post('/crear', [FrontendCampaignController::class, 'store'])->name('frontend-store');
        Route::get('/mis-campanas', [FrontendCampaignController::class, 'myCampaigns'])->name('my-campaigns');
        Route::get('/mis-campanas/{id}/editar', [FrontendCampaignController::class, 'edit'])->name('frontend-edit');
        Route::put('/mis-campanas/{id}', [FrontendCampaignController::class, 'update'])->name('frontend-update');
        Route::post('/mis-campanas/{id}/enviar-revision', [FrontendCampaignController::class, 'submitForReview'])->name('submit-review');
        Route::delete('/mis-campanas/{id}', [FrontendCampaignController::class, 'destroy'])->name('frontend-destroy');
    });

    Route::get('/{slug}', [CampaignController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('show');
});
