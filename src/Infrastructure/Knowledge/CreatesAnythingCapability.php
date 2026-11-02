<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;

/**
 * The person can create tables or campaigns on the site: for the guides about what happens to
 * anything created there (drafts, review, publication). Guide conditions only combine with AND.
 */
final readonly class CreatesAnythingCapability implements KnowledgeCapabilityInterface
{
    public function __construct(
        private CreatesTablesCapability $tables,
        private CreatesCampaignsCapability $campaigns,
    ) {}

    public function key(): string
    {
        return 'game-tables.can_create';
    }

    public function passes(object $user): bool
    {
        return $this->campaigns->passes($user) || $this->tables->passes($user);
    }
}
