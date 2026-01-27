<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\PublisherResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\GameTables\Filament\Resources\PublisherResource;

final class EditPublisher extends EditRecord
{
    protected static string $resource = PublisherResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.edit_publisher');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
