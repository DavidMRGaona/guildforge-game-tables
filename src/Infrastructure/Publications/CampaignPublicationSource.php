<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Publications;

use App\Application\Publications\Contracts\PublicationSourceInterface;
use App\Application\Publications\DTOs\PublicationDTO;
use App\Application\Publications\Enums\PublicationState;
use App\Infrastructure\Search\Support\PlainText;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * Published campaigns as publications (core contract). A campaign has no dates of its own (its
 * sessions do), so it carries none. Open means it takes new players; full means its roster
 * reached its limit.
 */
final readonly class CampaignPublicationSource implements PublicationSourceInterface
{
    /** Stored by whoever announces the campaigns: never changes. */
    public const string TYPE = 'campaign';

    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private CampaignRosterRepositoryInterface $roster,
        private GameSystemPublicationTopic $gameSystem,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function find(string $id): ?PublicationDTO
    {
        $campaign = $this->campaign($id);

        if (! $campaign instanceof Campaign) {
            return null;
        }

        return new PublicationDTO(
            type: self::TYPE,
            id: $campaign->id->value,
            title: $campaign->title,
            summary: PlainText::from((string) $campaign->description),
            url: route('campaigns.show', $campaign->slug),
            state: match ($campaign->status) {
                CampaignStatus::Completed => PublicationState::Ended,
                CampaignStatus::Cancelled => PublicationState::Cancelled,
                default => PublicationState::Active,
            },
            topics: $this->gameSystem->of($campaign->gameSystemId),
            isFull: ! $campaign->hasRoomFor($this->roster->count($campaign->id)),
            registrationOpen: $campaign->canAcceptNewPlayers(),
        );
    }

    /**
     * Published campaigns that are neither completed nor cancelled.
     */
    public function publishedIds(): iterable
    {
        $campaigns = CampaignModel::query()
            ->select('id')
            ->where('is_published', true)
            ->whereNotIn('status', [CampaignStatus::Completed->value, CampaignStatus::Cancelled->value])
            ->lazyById(200);

        foreach ($campaigns as $campaign) {
            yield (string) $campaign->getKey();
        }
    }

    private function campaign(string $id): ?Campaign
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $campaign = $this->campaigns->find(new CampaignId($id));

        return $campaign instanceof Campaign && $campaign->isPublished ? $campaign : null;
    }
}
