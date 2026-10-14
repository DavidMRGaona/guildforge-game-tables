<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Closure;

/**
 * Serialises the registrations, cancellations and promotions of one table: the callback runs
 * in a transaction that holds the lock of the table, so two requests never take the same seat,
 * the same waiting list position or the same promotion.
 */
interface RegistrationLockInterface
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function forTable(string $gameTableId, Closure $callback): mixed;

    /**
     * The lock of the participant's table.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function forParticipant(string $participantId, Closure $callback): mixed;

    /**
     * The lock of the table of the participant holding the guest cancellation token.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function forCancellationToken(string $token, Closure $callback): mixed;
}
