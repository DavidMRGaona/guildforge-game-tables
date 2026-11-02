<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\ContentWarningResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\GameTables\Filament\Resources\ContentWarningResource;

final class EditContentWarning extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = ContentWarningResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.edit_content_warning');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            DeleteAction::make(),
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }
}
