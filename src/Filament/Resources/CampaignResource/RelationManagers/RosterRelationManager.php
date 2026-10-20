<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;

/**
 * The players of a campaign. Adding and removing go through the roster service (its lock, its
 * rules, the sessions and the emails), never through the relation.
 */
final class RosterRelationManager extends RelationManager
{
    protected static string $relationship = 'players';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.relation_managers.roster.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('users.anonymized_at'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('game-tables::messages.relation_managers.roster.name'))
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('game-tables::messages.relation_managers.roster.email'))
                    ->searchable(),

                TextColumn::make('pivot.created_at')
                    ->label(__('game-tables::messages.relation_managers.roster.since'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('add')
                    ->label(__('game-tables::messages.relation_managers.roster.add'))
                    ->icon('heroicon-o-user-plus')
                    ->authorize(fn (): bool => $this->canManage())
                    ->schema([
                        Select::make('user_id')
                            ->label(__('game-tables::messages.relation_managers.roster.player'))
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->getSearchResultsUsing(fn (string $search): array => $this->searchUsers($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->userLabel($value)),
                    ])
                    ->action(function (array $data): void {
                        $this->run(fn () => app(CampaignRosterServiceInterface::class)->addUser(
                            (string) $this->getOwnerRecord()->getKey(),
                            (string) $data['user_id'],
                            (string) auth()->id(),
                        ), 'added');
                    }),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label(__('game-tables::messages.relation_managers.roster.remove'))
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->authorize(fn (): bool => $this->canManage())
                    ->requiresConfirmation()
                    ->modalDescription(__('game-tables::messages.relation_managers.roster.remove_description'))
                    ->action(function (UserModel $record): void {
                        $this->run(fn () => app(CampaignRosterServiceInterface::class)->removeMember(
                            (string) $this->getOwnerRecord()->getKey(),
                            $record->id,
                            (string) auth()->id(),
                        ), 'removed');
                    }),
            ]);
    }

    private function canManage(): bool
    {
        return Gate::allows('update', $this->getOwnerRecord());
    }

    /**
     * @return array<string, string>
     */
    private function searchUsers(string $search): array
    {
        $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';

        return UserModel::query()
            ->whereNull('anonymized_at')
            ->where(fn (Builder $query) => $query
                ->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(email) LIKE ?', [$term]))
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (UserModel $user): array => [$user->id => $user->name.' ('.$user->email.')'])
            ->all();
    }

    private function userLabel(mixed $value): ?string
    {
        $user = is_string($value) ? UserModel::query()->whereNull('anonymized_at')->find($value) : null;

        return $user instanceof UserModel ? $user->name.' ('.$user->email.')' : null;
    }

    private function run(callable $change, string $success): void
    {
        try {
            $change();
        } catch (RosterActionRefusedException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('game-tables::messages.roster.success.'.$success))->success()->send();
    }
}
