<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\StatusLogActorKind;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;

/**
 * The registration history of a table, for whoever may edit it. Read-only: rows are only written
 * by ParticipantStatusObserver. Account names are read live, so an anonymized account reads as
 * anonymized and a deleted one as «Cuenta eliminada».
 */
final class StatusLogRelationManager extends RelationManager
{
    protected static string $relationship = 'statusLog';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.relation_managers.status_log.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('update', $ownerRecord);
    }

    /**
     * Seeing the tab follows the table's update permission (the log rows have no policy);
     * anything else is denied, so a stock action added later grants nothing.
     */
    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (in_array($action, ['viewAny', 'view'], true)) {
            return Gate::inspect('update', $this->getOwnerRecord());
        }

        return Response::deny();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['participantUser', 'actor']))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('game-tables::messages.relation_managers.status_log.when'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('participant')
                    ->label(__('game-tables::messages.relation_managers.status_log.participant'))
                    ->state(fn (ParticipantStatusLogModel $record): string => $record->participantUser->name
                        ?? $record->guest_name
                        ?? __('game-tables::messages.relation_managers.status_log.deleted_account')),

                TextColumn::make('change')
                    ->label(__('game-tables::messages.relation_managers.status_log.change'))
                    ->state(fn (ParticipantStatusLogModel $record): string => ($record->from_status === null
                        ? __('game-tables::messages.relation_managers.status_log.registered')
                        : self::statusLabel($record->from_status)).' → '.self::statusLabel($record->to_status)),

                TextColumn::make('actor')
                    ->label(__('game-tables::messages.relation_managers.status_log.actor'))
                    ->state(fn (ParticipantStatusLogModel $record): string => match ($record->actor_kind) {
                        StatusLogActorKind::System => __('game-tables::messages.relation_managers.status_log.system'),
                        // The participant column already names them; a guest has no account
                        StatusLogActorKind::Participant => __('game-tables::messages.relation_managers.status_log.by_participant'),
                        StatusLogActorKind::Account => $record->actor->name ?? __('game-tables::messages.relation_managers.status_log.deleted_account'),
                    }),

                TextColumn::make('reason')
                    ->label(__('game-tables::messages.relation_managers.status_log.reason'))
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function statusLabel(string $status): string
    {
        return ParticipantStatus::tryFrom($status)?->label() ?? $status;
    }
}
