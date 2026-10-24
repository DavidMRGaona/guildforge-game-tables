<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Closure;
use Modules\GameTables\Application\Services\ParticipantActorContextInterface;

/**
 * Bound scoped: one per request or queue job, so a scope never outlives its own.
 */
final class ParticipantActorContext implements ParticipantActorContextInterface
{
    private bool $inScope = false;

    private ?string $actorUserId = null;

    private ?string $reason = null;

    public function run(?string $actorUserId, ?string $reason, Closure $callback): mixed
    {
        [$inScope, $outerActor, $outerReason] = [$this->inScope, $this->actorUserId, $this->reason];
        [$this->inScope, $this->actorUserId, $this->reason] = [true, $actorUserId, $reason];

        try {
            return $callback();
        } finally {
            [$this->inScope, $this->actorUserId, $this->reason] = [$inScope, $outerActor, $outerReason];
        }
    }

    public function system(Closure $callback): mixed
    {
        return $this->run(null, null, $callback);
    }

    public function actorUserId(): ?string
    {
        if ($this->inScope) {
            return $this->actorUserId;
        }

        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }

    public function isSystem(): bool
    {
        return $this->actorUserId() === null;
    }

    public function reason(): ?string
    {
        return $this->inScope ? $this->reason : null;
    }
}
