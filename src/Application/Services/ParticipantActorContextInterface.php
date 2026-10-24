<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Closure;

/**
 * Who is changing registrations right now, and why, for the status history. A scope names the
 * actor (null for the system) for everything its callback does; scopes nest, and each one gives
 * the outer values back when it ends, also when it throws. Without a scope the actor is the
 * signed-in user, and the system when nobody is signed in.
 */
interface ParticipantActorContextInterface
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(?string $actorUserId, ?string $reason, Closure $callback): mixed;

    /**
     * A scope with no actor and no reason: what the module does by itself (promotions, expiries).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function system(Closure $callback): mixed;

    public function actorUserId(): ?string;

    public function isSystem(): bool;

    public function reason(): ?string;
}
