<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Closure;
use Modules\GameTables\Application\Services\ParticipantActorContextInterface;
use Modules\GameTables\Domain\Enums\StatusLogActorKind;

/**
 * Bound scoped: one per request or queue job, so a scope never outlives its own.
 */
final class ParticipantActorContext implements ParticipantActorContextInterface
{
    // Null outside any scope
    private ?StatusLogActorKind $kind = null;

    private ?string $actorUserId = null;

    private ?string $reason = null;

    public function run(?string $actorUserId, ?string $reason, Closure $callback): mixed
    {
        $kind = $actorUserId === null ? StatusLogActorKind::System : StatusLogActorKind::Account;

        return $this->scope($kind, $actorUserId, $reason, $callback);
    }

    public function system(Closure $callback): mixed
    {
        return $this->run(null, null, $callback);
    }

    public function asParticipant(?string $userId, Closure $callback): mixed
    {
        return $this->scope(StatusLogActorKind::Participant, $userId, null, $callback);
    }

    public function actorUserId(): ?string
    {
        if ($this->kind !== null) {
            return $this->actorUserId;
        }

        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }

    public function actorKind(): StatusLogActorKind
    {
        if ($this->kind !== null) {
            return $this->kind;
        }

        return $this->actorUserId() === null ? StatusLogActorKind::System : StatusLogActorKind::Account;
    }

    public function reason(): ?string
    {
        return $this->kind !== null ? $this->reason : null;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function scope(StatusLogActorKind $kind, ?string $actorUserId, ?string $reason, Closure $callback): mixed
    {
        $outer = [$this->kind, $this->actorUserId, $this->reason];
        [$this->kind, $this->actorUserId, $this->reason] = [$kind, $actorUserId, $reason];

        try {
            return $callback();
        } finally {
            [$this->kind, $this->actorUserId, $this->reason] = $outer;
        }
    }
}
