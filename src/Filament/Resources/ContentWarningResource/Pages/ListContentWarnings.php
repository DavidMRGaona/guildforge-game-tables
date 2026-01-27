<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\ContentWarningResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Modules\GameTables\Filament\Resources\ContentWarningResource;

final class ListContentWarnings extends ListRecords
{
    protected static string $resource = ContentWarningResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.model.content_warning.plural');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_content_warning')),
        ];
    }
}
