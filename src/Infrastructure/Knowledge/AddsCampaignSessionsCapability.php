<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Modules\GameTables\Infrastructure\Services\CampaignSessionCreationGate;

/**
 * The person directs a campaign they may add sessions to from the site: the campaigns the create
 * form offers for a campaign session.
 */
final readonly class AddsCampaignSessionsCapability implements KnowledgeCapabilityInterface
{
    use ReadsUserId;

    public function __construct(private CampaignSessionCreationGate $sessionGate) {}

    public function key(): string
    {
        return 'game-tables.adds_campaign_sessions';
    }

    public function passes(object $user): bool
    {
        $userId = $this->userId($user);

        return $userId !== null && $this->sessionGate->canCreateSessionsOnly($userId);
    }
}
