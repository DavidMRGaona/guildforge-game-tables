<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use Modules\GameTables\Application\Services\ParticipantActorContextInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;
use Throwable;

/**
 * Writes the status history: every registration (created) and every change of status (updated),
 * whatever saved it, with the actor and reason of the current scope (ParticipantActorContextInterface).
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
        if (! $participant->wasChanged('status')) {
            return;
        }

        $from = $participant->getRawOriginal('status');

        $this->record($participant, is_string($from) ? $from : null);
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
                'actor_is_system' => $this->actor->isSystem(),
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
