<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use Modules\GameTables\Application\Services\ParticipantActorContextInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;
use Throwable;

/**
 * Writes the status history: every registration (created) and every change of status (updated),
 * whatever saved it, with the actor and reason of the current scope (ParticipantActorContextInterface),
 * and hands a guest's rows to the account that takes the registration over.
 * It runs inside the registration's transaction, so a rolled back change leaves no row.
 */
final readonly class ParticipantStatusObserver
{
    public function __construct(
        private ParticipantActorContextInterface $actor,
    ) {}

    public function created(ParticipantModel $participant): void
    {
        $this->record($participant, null);
    }

    public function updated(ParticipantModel $participant): void
    {
        if ($this->wasTakenOverByAnAccount($participant)) {
            $this->linkHistoryToAccount($participant);
        }

        if (! $participant->wasChanged('status')) {
            return;
        }

        $from = $participant->getRawOriginal('status');

        $this->record($participant, is_string($from) ? $from : null);
    }

    /**
     * A guest row that an account takes over (attachToAccount, or the panel form).
     */
    private function wasTakenOverByAnAccount(ParticipantModel $participant): bool
    {
        return $participant->wasChanged('user_id')
            && $participant->getRawOriginal('user_id') === null
            && $participant->user_id !== null;
    }

    /**
     * The guest's earlier rows become the account's: the copied guest name goes, so anonymizing
     * the account later reaches them too.
     */
    private function linkHistoryToAccount(ParticipantModel $participant): void
    {
        try {
            $participant->getConnection()->transaction(fn (): int => ParticipantStatusLogModel::query()
                ->where('participant_id', $participant->id)
                ->update(['participant_user_id' => $participant->user_id, 'guest_name' => null]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function record(ParticipantModel $participant, ?string $fromStatus): void
    {
        try {
            // A savepoint: on PostgreSQL a failed insert would otherwise abort the registration's
            // own transaction
            $participant->getConnection()->transaction(fn (): ParticipantStatusLogModel => ParticipantStatusLogModel::query()->create([
                'game_table_id' => $participant->game_table_id,
                'participant_id' => $participant->id,
                'participant_user_id' => $participant->user_id,
                // Account names are read live from users, never copied
                'guest_name' => $participant->user_id === null
                    ? trim($participant->first_name.' '.$participant->last_name)
                    : null,
                'actor_user_id' => $this->actor->actorUserId(),
                'actor_kind' => $this->actor->actorKind(),
                'from_status' => $fromStatus,
                'to_status' => $participant->status->value,
                'reason' => $this->actor->reason(),
            ]));
        } catch (Throwable $e) {
            // The registration is what matters: a missing history row must never break it
            report($e);
        }
    }
}
