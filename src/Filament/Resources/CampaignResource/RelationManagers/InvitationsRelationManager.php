<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\Concerns\ManagesCampaignRoster;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;

/**
 * The pending invitations of a campaign. Inviting, resending and cancelling go through the
 * invitation service (its limits, its rules and the emails), never through the row.
 */
final class InvitationsRelationManager extends RelationManager
{
    use ManagesCampaignRoster;

    protected static string $relationship = 'invitations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('game-tables::messages.relation_managers.invitations.title');
    }

    public function table(Table $table): Table
    {
        return $table
            // An expired invitation the daily cleanup has not removed yet is not shown, as on the web
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('expires_at', '>', now()))
            ->defaultSort('created_at', 'desc')
            // No seat is reserved: whoever accepts first enters, so the direction is told (as on the web)
            ->description(fn (): ?string => $this->moreInvitationsThanSeats()
                ? (string) __('game-tables::messages.relation_managers.invitations.more_than_seats')
                : null)
            ->columns([
                TextColumn::make('email')
                    ->label(__('game-tables::messages.relation_managers.invitations.email'))
                    ->searchable(),

                TextColumn::make('inviter_name')
                    ->label(__('game-tables::messages.relation_managers.invitations.invited_by')),

                TextColumn::make('send_count')
                    ->label(__('game-tables::messages.relation_managers.invitations.send_count')),

                TextColumn::make('expires_at')
                    ->label(__('game-tables::messages.relation_managers.invitations.expires_at'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('invite')
                    ->label(__('game-tables::messages.relation_managers.invitations.invite'))
                    ->icon('heroicon-o-user-plus')
                    ->authorize(fn (): bool => $this->canManage())
                    ->schema([
                        TextInput::make('email')
                            ->label(__('game-tables::messages.relation_managers.invitations.email'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Checkbox::make('consent')
                            ->label(__('game-tables::messages.relation_managers.invitations.consent'))
                            ->accepted()
                            ->validationMessages(['accepted' => __('game-tables::messages.validation.invitation_consent_required')]),
                    ])
                    ->action(function (array $data): void {
                        $email = mb_strtolower(trim((string) $data['email']));

                        $this->run(fn () => app(CampaignInvitationServiceInterface::class)->invite(
                            (string) $this->getOwnerRecord()->getKey(),
                            $email,
                            (string) auth()->id(),
                        ), 'invited', ['email' => $email]);
                    }),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label(__('game-tables::messages.relation_managers.invitations.resend'))
                    ->icon('heroicon-o-paper-airplane')
                    ->authorize(fn (): bool => $this->canManage())
                    ->action(function (CampaignInvitationModel $record): void {
                        $this->run(fn () => app(CampaignInvitationServiceInterface::class)->resend(
                            (string) $this->getOwnerRecord()->getKey(),
                            $record->id,
                            (string) auth()->id(),
                        ), 'invitation_resent');
                    }),

                Action::make('cancel')
                    ->label(__('game-tables::messages.relation_managers.invitations.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->authorize(fn (): bool => $this->canManage())
                    ->requiresConfirmation()
                    ->modalDescription(__('game-tables::messages.relation_managers.invitations.cancel_description'))
                    ->action(function (CampaignInvitationModel $record): void {
                        $this->run(fn () => app(CampaignInvitationServiceInterface::class)->cancel(
                            (string) $this->getOwnerRecord()->getKey(),
                            $record->id,
                        ), 'invitation_cancelled');
                    }),
            ]);
    }

    private function moreInvitationsThanSeats(): bool
    {
        $campaign = $this->getOwnerRecord();
        $maxPlayers = $campaign->getAttribute('max_players');

        if ($maxPlayers === null) {
            return false;
        }

        $pending = CampaignInvitationModel::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('expires_at', '>', now())
            ->count();
        $rosterCount = app(CampaignRosterRepositoryInterface::class)->count(new CampaignId((string) $campaign->getKey()));

        return $pending > (int) $maxPlayers - $rosterCount;
    }
}
