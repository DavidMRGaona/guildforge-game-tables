<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\Concerns\ManagesCampaignRoster;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;

/**
 * The requests to join a campaign. Deciding goes through the roster service, never through the row.
 */
final class JoinRequestsRelationManager extends RelationManager
{
    use ManagesCampaignRoster;

    protected static string $relationship = 'joinRequests';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.relation_managers.join_requests.title');
    }

    public function table(Table $table): Table
    {
        return $table
            // A request of an anonymized account is never shown
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereHas('user', fn (Builder $user): Builder => $user->whereNull('anonymized_at'))
                ->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('game-tables::messages.relation_managers.join_requests.player')),

                TextColumn::make('message')
                    ->label(__('game-tables::messages.relation_managers.join_requests.message'))
                    ->wrap()
                    ->limit(120),

                TextColumn::make('status')
                    ->label(__('game-tables::messages.relation_managers.join_requests.status'))
                    ->badge()
                    ->formatStateUsing(fn (JoinRequestStatus $state): string => $state->label())
                    ->color(fn (JoinRequestStatus $state): string => $state->color()),

                TextColumn::make('created_at')
                    ->label(__('game-tables::messages.relation_managers.join_requests.requested_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('game-tables::messages.relation_managers.join_requests.status'))
                    ->options(JoinRequestStatus::options())
                    ->default(JoinRequestStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label(__('game-tables::messages.relation_managers.join_requests.accept'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CampaignJoinRequestModel $record): bool => $record->status === JoinRequestStatus::Pending)
                    ->authorize(fn (): bool => $this->canManage())
                    ->requiresConfirmation()
                    ->action(function (CampaignJoinRequestModel $record): void {
                        $this->run(fn () => app(CampaignRosterServiceInterface::class)->acceptRequest(
                            $record->id,
                            (string) auth()->id(),
                        ), 'accepted');
                    }),

                Action::make('reject')
                    ->label(__('game-tables::messages.relation_managers.join_requests.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (CampaignJoinRequestModel $record): bool => $record->status === JoinRequestStatus::Pending)
                    ->authorize(fn (): bool => $this->canManage())
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('game-tables::messages.relation_managers.join_requests.reason'))
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function (CampaignJoinRequestModel $record, array $data): void {
                        $reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';

                        $this->run(fn () => app(CampaignRosterServiceInterface::class)->rejectRequest(
                            $record->id,
                            (string) auth()->id(),
                            $reason === '' ? null : $reason,
                        ), 'rejected');
                    }),
            ]);
    }
}
