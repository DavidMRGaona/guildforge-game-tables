<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\ContentWarningResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\GameTables\Filament\Resources\ContentWarningResource;

final class EditContentWarning extends EditRecord
{
    protected static string $resource = ContentWarningResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('game-tables::messages.pages.edit_content_warning');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
