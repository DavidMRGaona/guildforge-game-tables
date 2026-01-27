<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\ContentWarningResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\GameTables\Filament\Resources\ContentWarningResource;

final class CreateContentWarning extends CreateRecord
{
    protected static string $resource = ContentWarningResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.create_content_warning');
    }
}
