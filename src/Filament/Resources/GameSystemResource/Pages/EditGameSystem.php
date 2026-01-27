<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameSystemResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\GameTables\Filament\Resources\GameSystemResource;

final class EditGameSystem extends EditRecord
{
    protected static string $resource = GameSystemResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.edit_game_system');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
