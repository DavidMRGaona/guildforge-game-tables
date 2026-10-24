<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Closure;
use Modules\GameTables\Domain\Enums\StatusLogActorKind;

/**
 * Who is changing registrations right now, and why, for the status history. A scope names the
 * actor (an account, the participant themselves or the system) for everything its callback does;
 * scopes nest, and each one gives the outer values back when it ends, also when it throws. Without
 * a scope the actor is the signed-in user's account, and the system when nobody is signed in.
 */
interface ParticipantActorContextInterface
{
    /**
     * A scope in the name of an account, or of the system when $actorUserId is null.
     *
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

    /**
     * A scope with no reason for the participant acting on their own registration: their account,
     * or null for a guest, who is still told apart from the system.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function asParticipant(?string $userId, Closure $callback): mixed;

    public function actorUserId(): ?string;

    public function actorKind(): StatusLogActorKind;

    public function reason(): ?string;
}
