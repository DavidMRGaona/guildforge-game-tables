<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Modules\GameTables\Application\DTOs\CreationEligibilityDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Infrastructure\Services\CampaignSessionCreationGate;

/**
 * The person can create tables on the site, now or from a known date: the same checks as the
 * create page (general rules, the sessions of the campaigns they direct, or an event open to
 * tables with its own rule).
 */
final readonly class CreatesTablesCapability implements KnowledgeCapabilityInterface
{
    use ReadsUserId;

    public function __construct(
        private CreationEligibilityServiceInterface $eligibility,
        private CampaignSessionCreationGate $sessionGate,
        private FrontendCreationServiceInterface $creation,
    ) {}

    public function key(): string
    {
        return 'game-tables.can_create_tables';
    }

    public function passes(object $user): bool
    {
        $userId = $this->userId($user);

        if ($userId === null) {
            return false;
        }

        if (self::opens($this->eligibility->canCreateTable($userId)) || $this->sessionGate->canCreateSessionsOnly($userId)) {
            return true;
        }

        return array_any($this->creation->selectableEventIds(), fn ($eventId) => self::opens($this->creation->eventCreationEligibility($eventId, $userId)));
    }

    /**
     * Eligible now, or on a date the create page announces.
     */
    public static function opens(CreationEligibilityDTO $eligibility): bool
    {
        return $eligibility->eligible || $eligibility->canCreateAt instanceof \DateTimeImmutable;
    }
}
