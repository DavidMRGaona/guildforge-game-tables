<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\Concerns\ManagesCampaignRoster;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;

/**
 * The players of a campaign. Adding and removing go through the roster service (its lock, its
 * rules, the sessions and the emails), never through the relation.
 */
final class RosterRelationManager extends RelationManager
{
    use ManagesCampaignRoster;

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
                        TextInput::make('email')
                            ->label(__('game-tables::messages.relation_managers.roster.account_email'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $this->run(fn () => app(CampaignRosterServiceInterface::class)->addByEmail(
                            (string) $this->getOwnerRecord()->getKey(),
                            (string) $data['email'],
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
}
