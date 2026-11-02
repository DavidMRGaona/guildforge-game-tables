<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;

/**
 * The person can create campaigns on the site, now or from a known date: the same check as the
 * campaign create page.
 */
final readonly class CreatesCampaignsCapability implements KnowledgeCapabilityInterface
{
    use ReadsUserId;

    public function __construct(private CreationEligibilityServiceInterface $eligibility) {}

    public function key(): string
    {
        return 'game-tables.can_create_campaigns';
    }

    public function passes(object $user): bool
    {
        $userId = $this->userId($user);

        return $userId !== null && CreatesTablesCapability::opens($this->eligibility->canCreateCampaign($userId));
    }
}
