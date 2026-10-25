<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use DateInterval;
use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;

/**
 * Turns the guest confirmation settings into the mode (hold or wait) and the deadline of a guest
 * registration at a table.
 */
final readonly class GuestConfirmationPolicy
{
    public function __construct(
        private ParticipantRepositoryInterface $participants,
        private GameTableSettingsReader $settings = new GameTableSettingsReader(),
    ) {
    }

    /**
     * The earlier of now plus the hold hours and the table's registration closing.
     */
    public function deadlineFor(GameTable $table, DateTimeImmutable $now): DateTimeImmutable
    {
        $deadline = $now->add(new DateInterval('PT'.$this->settings->guestConfirmationHoldHours().'H'));
        $closing = $table->effectiveRegistrationClosesAt();

        return $closing < $deadline ? $closing : $deadline;
    }

    /**
     * The configured mode; hold becomes wait once the table already has as many unconfirmed guests
     * holding a seat as the cap allows.
     */
    public function modeFor(GameTable $table): GuestConfirmationMode
    {
        $mode = $this->settings->guestConfirmationMode();

        if ($mode === GuestConfirmationMode::Hold
            && $this->participants->countUnconfirmedHoldingSeats($table->id) >= $this->settings->maxUnconfirmedHeldSeats()) {
            return GuestConfirmationMode::Wait;
        }

        return $mode;
    }

    /**
     * What the guest is told: the mode and, only when the seat is held, the ISO 8601 deadline.
     *
     * @return array{mode: 'hold'|'wait', deadline: string|null}
     */
    public function noticeFor(GameTable $table, DateTimeImmutable $now): array
    {
        if ($this->modeFor($table) === GuestConfirmationMode::Hold) {
            return ['mode' => 'hold', 'deadline' => $this->deadlineFor($table, $now)->format('c')];
        }

        return ['mode' => 'wait', 'deadline' => null];
    }
}
