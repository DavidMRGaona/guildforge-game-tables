<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\PublisherResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\GameTables\Filament\Resources\PublisherResource;

final class CreatePublisher extends CreateRecord
{
    protected static string $resource = PublisherResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.create_publisher');
    }
}
