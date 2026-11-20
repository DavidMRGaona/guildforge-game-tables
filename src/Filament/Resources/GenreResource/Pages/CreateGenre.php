<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GenreResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Resources\Pages\CreateRecord;
use Modules\GameTables\Filament\Resources\GenreResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

final class CreateGenre extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = GenreResource::class;

    public function getTitle(): string
    {
        return __('game-tables::messages.pages.create_genre');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction('game-tables.panel.catalog'),
        ]);
    }

    /**
     * A new genre goes last; dragging it in the list moves it
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $last = GenreModel::query()->max('sort_order');
        $data['sort_order'] = (is_numeric($last) ? (int) $last : 0) + 1;

        return $data;
    }
}
