<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Search;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * The campaigns of the public listing. A campaign has no dates of its own: a completed one is over by
 * its status. Moderation notes are private and never reach the index.
 */
final class CampaignSearchSource implements IndexedSearchSourceInterface
{
    public const string TYPE = 'gametables.campaigns';

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('game-tables::messages.search.campaigns.label');
    }

    public function itemLabel(): string
    {
        return __('game-tables::messages.search.campaigns.item');
    }

    public function pastLabel(): string
    {
        return __('game-tables::messages.search.campaigns.past');
    }

    public function document(string $id): ?SearchDocumentDTO
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        if (! Str::isUuid($id)) {
            return null;
        }

        $campaign = $this->listed()->whereKey($id)->first();

        return $campaign instanceof CampaignModel ? $this->toDocument($campaign) : null;
    }

    /**
     * @return iterable<SearchDocumentDTO>
     */
    public function documents(): iterable
    {
        foreach ($this->listed()->lazyById(200) as $campaign) {
            yield $this->toDocument($campaign);
        }
    }

    /**
     * @return Builder<CampaignModel>
     */
    private function listed(): Builder
    {
        return CampaignModel::query()->publiclyListed()->with('gameSystem');
    }

    private function toDocument(CampaignModel $campaign): SearchDocumentDTO
    {
        $system = SearchText::join([$campaign->gameSystem?->name], '');

        return new SearchDocumentDTO(
            id: $campaign->id,
            title: $campaign->title,
            body: SearchText::plain($campaign->description),
            url: route('campaigns.show', $campaign->slug ?? $campaign->id, false),
            keywords: $system,
            meta: $system,
            imagePublicId: $campaign->image_public_id,
            finished: $campaign->status === CampaignStatus::Completed,
        );
    }
}
