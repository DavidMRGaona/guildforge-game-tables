<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Enums\GuestConfirmationOutcome;

final readonly class GuestConfirmationResultDTO
{
    public function __construct(
        public GuestConfirmationOutcome $outcome,
        public ?ParticipantResponseDTO $participant = null,
        // The table of the registration, when the token was found
        public ?string $gameTableId = null,
    ) {}
}
