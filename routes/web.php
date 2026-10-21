<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GameTables\Http\Controllers\CampaignController;
use Modules\GameTables\Http\Controllers\CampaignJoinController;
use Modules\GameTables\Http\Controllers\CampaignRosterController;
use Modules\GameTables\Http\Controllers\FrontendCampaignController;
use Modules\GameTables\Http\Controllers\FrontendGameTableController;
use Modules\GameTables\Http\Controllers\GameTableController;
use Modules\GameTables\Http\Controllers\GuestConfirmationController;
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

    // Guest email confirmation (public): the page only shows, its button confirms
    Route::get('/confirmar/{token}', [GuestConfirmationController::class, 'show'])
        ->middleware('throttle:game-tables-guest-confirmation')
        ->name('confirm-guest');
    Route::post('/confirmar/{token}', [GuestConfirmationController::class, 'store'])
        ->middleware('throttle:game-tables-guest-confirmation')
        ->name('confirm-guest.store');

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

    // Guest registration (no auth required), limited per IP
    Route::post('/{gameTable}/inscripcion-invitado', [RegistrationController::class, 'registerGuest'])
        ->middleware('throttle:game-tables-guest-registration')
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
        // A malformed id is not found (the id column is a uuid: PostgreSQL refuses to compare it)
        Route::get('/mis-campanas/{id}/editar', [FrontendCampaignController::class, 'edit'])->whereUuid('id')->name('frontend-edit');
        Route::put('/mis-campanas/{id}', [FrontendCampaignController::class, 'update'])->whereUuid('id')->name('frontend-update');
        Route::post('/mis-campanas/{id}/enviar-revision', [FrontendCampaignController::class, 'submitForReview'])->whereUuid('id')->name('submit-review');
        Route::delete('/mis-campanas/{id}', [FrontendCampaignController::class, 'destroy'])->whereUuid('id')->name('frontend-destroy');

        // Roster page of the direction (the emails link to it)
        Route::get('/mis-campanas/{id}/plantilla', [CampaignRosterController::class, 'show'])->name('roster');
        Route::put('/mis-campanas/{id}/plantilla/ajustes', [CampaignRosterController::class, 'updateSettings'])->name('roster.settings');
        Route::post('/mis-campanas/{id}/plantilla/invitaciones', [CampaignRosterController::class, 'invite'])
            ->middleware('throttle:game-tables-campaign-roster-add')
            ->name('roster.invite');
        Route::post('/mis-campanas/{id}/plantilla/invitaciones/{invitationId}/reenviar', [CampaignRosterController::class, 'resendInvitation'])
            ->middleware('throttle:game-tables-campaign-roster-add')
            ->name('roster.invitation.resend');
        Route::delete('/mis-campanas/{id}/plantilla/invitaciones/{invitationId}', [CampaignRosterController::class, 'cancelInvitation'])->name('roster.invitation.cancel');
        Route::delete('/mis-campanas/{id}/plantilla/jugadores/{userId}', [CampaignRosterController::class, 'remove'])->name('roster.remove');
        Route::post('/mis-campanas/{id}/plantilla/solicitudes/{requestId}/aceptar', [CampaignRosterController::class, 'accept'])->name('roster.accept');
        Route::post('/mis-campanas/{id}/plantilla/solicitudes/{requestId}/rechazar', [CampaignRosterController::class, 'reject'])->name('roster.reject');
        Route::post('/mis-campanas/{id}/plantilla/ausencias', [CampaignRosterController::class, 'toggleAbsence'])->name('roster.absence');

        // The player's side, from the campaign page; requests are limited per user
        Route::post('/{slug}/solicitud', [CampaignJoinController::class, 'request'])
            ->where('slug', '[a-z0-9-]+')
            ->middleware('throttle:game-tables-campaign-join')
            ->name('join.request');
        Route::delete('/{slug}/solicitud', [CampaignJoinController::class, 'withdraw'])
            ->where('slug', '[a-z0-9-]+')
            ->name('join.withdraw');
        Route::delete('/{slug}/plantilla', [CampaignJoinController::class, 'leave'])
            ->where('slug', '[a-z0-9-]+')
            ->name('leave');
    });

    Route::get('/{slug}', [CampaignController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('show');
});
