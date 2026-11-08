<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final class ListCampaigns extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('game-tables::messages.pages.create_campaign')),
            $this->knowledgeGuideAction('game-tables.panel.overview'),
        ]);
    }

    /**
     * The same tabs as the table list: everything, or only what awaits review.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('game-tables::messages.table_tabs.all')),
            'pending_moderation' => Tab::make(__('game-tables::messages.table_tabs.pending_moderation'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('frontend_creation_status', FrontendCreationStatus::PendingReview->value))
                ->badge($this->getPendingModerationCount())
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),
        ];
    }

    private function getPendingModerationCount(): int
    {
        return CampaignModel::query()
            ->where('frontend_creation_status', FrontendCreationStatus::PendingReview->value)
            ->count();
    }
}
