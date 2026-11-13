<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Proposals;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;

/**
 * «¿Habrá mesas de rol?» in an event proposal (core#581). Approving it enables tables for the
 * event with the module's defaults; the administration sets slots and place afterwards, in the
 * event's tables tab (hidden while the event is an unapproved proposal).
 */
final readonly class TablesProposalRequest implements EventProposalRequestInterface
{
    public const string KEY = 'game-tables.tables';

    public function __construct(private EventGameTableConfigRepositoryInterface $configs) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('game-tables::messages.proposal_request.label');
    }

    public function question(): string
    {
        return __('game-tables::messages.proposal_request.question');
    }

    public function order(): int
    {
        return 20;
    }

    public function fields(): array
    {
        return [
            ProposalRequestFieldDTO::text(
                'note',
                __('game-tables::messages.proposal_request.note'),
                maxLength: 255,
                helperText: __('game-tables::messages.proposal_request.note_help'),
            ),
        ];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function apply(EventId $eventId, array $values): void
    {
        if ($this->configs->exists($eventId->value())) {
            throw ProposalRequestRefusedException::because(__('game-tables::messages.proposal_request.already_configured'));
        }

        // Everything but «enabled» stays at the module's defaults; the note is for the reviewer only
        $this->configs->save(new EventGameTableConfig(eventId: $eventId->value(), tablesEnabled: true));
    }

    public function describe(array $values): string
    {
        $note = $values['note'] ?? null;

        return is_string($note) && $note !== ''
            ? __('game-tables::messages.proposal_request.with_note', ['note' => $note])
            : __('game-tables::messages.proposal_request.label');
    }
}
