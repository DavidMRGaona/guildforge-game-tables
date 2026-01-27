<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameSystemResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\GameTables\Filament\Resources\GameSystemResource;

final class CreateGameSystem extends CreateRecord
{
    protected static string $resource = GameSystemResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.create_game_system');
    }
}
