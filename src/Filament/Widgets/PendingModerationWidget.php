<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Widgets;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Tables and campaigns created from the web that wait for a moderator.
 */
final class PendingModerationWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return GameTableModel::query()->where('frontend_creation_status', FrontendCreationStatus::PendingReview->value)->exists()
            || CampaignModel::query()->where('frontend_creation_status', FrontendCreationStatus::PendingReview->value)->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('game-tables::messages.widgets.pending_moderation.title'))
            ->records(fn (int $page, int $recordsPerPage): LengthAwarePaginator => $this->pendingRecords($page, $recordsPerPage))
            ->columns([
                TextColumn::make('type')
                    ->label(__('game-tables::messages.widgets.pending_moderation.type'))
                    ->badge(),

                TextColumn::make('title')
                    ->label(__('game-tables::messages.fields.title'))
                    ->limit(40)
                    ->url(fn (array $record): string => $record['url']),

                TextColumn::make('game_system')
                    ->label(__('game-tables::messages.fields.game_system'))
                    ->limit(20),

                TextColumn::make('creator')
                    ->label(__('game-tables::messages.fields.user'))
                    ->limit(20),

                TextColumn::make('created_at')
                    ->label(__('game-tables::messages.fields.registered_at'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('game-tables::messages.actions.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (array $record): string => $record['url']),
            ])
            ->emptyStateHeading(__('game-tables::messages.widgets.pending_moderation.no_pending'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([$this->getConfiguredLimit()])
            ->defaultPaginationPageOption($this->getConfiguredLimit());
    }

    /**
     * Oldest first, tables and campaigns together.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function pendingRecords(int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $pending = FrontendCreationStatus::PendingReview->value;

        $tables = GameTableModel::query()
            ->where('frontend_creation_status', $pending)
            ->with(['gameSystem', 'creator'])
            ->get()
            ->map(fn (GameTableModel $table): array => [
                ArrayRecord::getKeyName() => $table->id,
                'type' => __('game-tables::messages.model.game_table.singular'),
                'title' => $table->title,
                'game_system' => $table->gameSystem?->name,
                'creator' => $table->creator?->name,
                'created_at' => $table->created_at,
                // The panel finds game tables by slug
                'url' => GameTableResource::getUrl('edit', ['record' => $table]),
            ]);

        $campaigns = CampaignModel::query()
            ->where('frontend_creation_status', $pending)
            ->with(['gameSystem', 'creator'])
            ->get()
            ->map(fn (CampaignModel $campaign): array => [
                ArrayRecord::getKeyName() => $campaign->id,
                'type' => __('game-tables::messages.model.campaign.singular'),
                'title' => $campaign->title,
                'game_system' => $campaign->gameSystem?->name,
                'creator' => $campaign->creator?->name,
                'created_at' => $campaign->created_at,
                'url' => CampaignResource::getUrl('edit', ['record' => $campaign]),
            ]);

        /** @var Collection<int, array<string, mixed>> $records */
        $records = $tables->toBase()->concat($campaigns->toBase())
            ->sortBy(static fn (array $record): int => $record['created_at'] instanceof DateTimeInterface ? $record['created_at']->getTimestamp() : 0)
            ->values();

        return new LengthAwarePaginator(
            $records->forPage($page, $recordsPerPage)->values(),
            $records->count(),
            $recordsPerPage,
            $page,
        );
    }

    private function getConfiguredLimit(): int
    {
        return app(DashboardWidgetConfigServiceInterface::class)
            ->getLimit(self::class, 5);
    }
}
