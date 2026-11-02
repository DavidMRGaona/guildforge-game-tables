<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * The person directs an approved or published campaign: the campaigns whose roster page they may
 * open (CampaignPolicy::manageRoster).
 */
final readonly class ManagesRosterCapability implements KnowledgeCapabilityInterface
{
    use ReadsUserId;

    public function __construct(private CampaignDirectionInterface $direction) {}

    public function key(): string
    {
        return 'game-tables.manages_roster';
    }

    public function passes(object $user): bool
    {
        $userId = $this->userId($user);

        if ($userId === null) {
            return false;
        }

        $directed = $this->direction->campaignIdsDirectedBy($userId);

        return $directed !== [] && CampaignModel::query()
            ->whereKey($directed)
            ->where(static function ($query): void {
                $query->where('is_published', true)
                    ->orWhere('frontend_creation_status', FrontendCreationStatus::Approved->value);
            })
            ->exists();
    }
}
