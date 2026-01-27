<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GameTables\Http\Controllers\GameTableCountController;

Route::middleware('web')
    ->prefix('api/mesas')
    ->name('gametables.api.')
    ->group(function (): void {
        Route::get('/count', [GameTableCountController::class, 'count'])
            ->name('count');
    });
