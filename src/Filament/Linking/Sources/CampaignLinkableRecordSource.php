<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Linking\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Linking\Contracts\FindsLinkableRecordsInBatches;
use App\Application\Linking\Contracts\FindsLinkableRecordsOfOrganizer;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * Campaigns as linkable records, so other modules can link their data to a campaign without
 * depending on this module.
 *
 * Published campaigns are what their page shows, so every viewer finds them; unpublished ones
 * only for who views any campaign in the panel (gametables:campaigns.view) and for who created
 * them, as CampaignPolicy::view() says. The panel URL is the edit page, only for who may edit
 * the campaign. A campaign has no single date: lists go from the latest created.
 */
final readonly class CampaignLinkableRecordSource implements FindsLinkableRecordsInBatches, FindsLinkableRecordsOfOrganizer
{
    use ReadsLinkableRecordQueries;

    public const string TYPE = 'campaign';

    private const string VIEW_PERMISSION = 'gametables:campaigns.view';

    public function __construct(private AuthorizationServiceInterface $authorization) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('game-tables::messages.linking.campaign');
    }

    public function search(string $query, string $viewerId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $viewer = $this->viewer($viewerId);

        return $this->latestFirst($this->matchingTitle($this->visibleTo($viewer), $query), $limit, $viewer);
    }

    /**
     * The id is read in lower case, as the keys are stored; the record answers with the id asked,
     * as the contract wants.
     */
    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        $record = $this->findMany([strtolower($id)], $viewerId)[0] ?? null;

        return $record instanceof LinkableRecordDTO && $record->id !== $id
            ? new LinkableRecordDTO(type: $record->type, id: $id, title: $record->title, panelUrl: $record->panelUrl)
            : $record;
    }

    public function findMany(array $ids, string $viewerId): array
    {
        $ids = $this->uuids($ids);

        if ($ids === []) {
            return [];
        }

        $viewer = $this->viewer($viewerId);

        return $this->toRecords($this->visibleTo($viewer)->whereKey($ids)->get(), $viewer);
    }

    /**
     * The campaigns the person created or directs (hidden names count, it is their own), among
     * those still running that the public listing shows: published, neither cancelled nor
     * completed.
     */
    public function organizedBy(string $userId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $campaigns = CampaignModel::query()
            ->publiclyListed()
            ->where('gametables_campaigns.status', '!=', CampaignStatus::Completed->value)
            ->where(fn (Builder $organized) => $organized
                ->where('gametables_campaigns.created_by', $userId)
                ->orWhereHas('gameMasters', fn (Builder $gm) => $gm->linkedTo($userId)));

        return $this->latestFirst($campaigns, $limit, null);
    }

    /**
     * @return Builder<CampaignModel>
     */
    private function visibleTo(?UserModel $viewer): Builder
    {
        $campaigns = CampaignModel::query();

        if ($viewer instanceof UserModel && $this->authorization->can($viewer, self::VIEW_PERMISSION)) {
            return $campaigns;
        }

        return $campaigns->where(fn (Builder $visible) => $visible
            ->shownOnItsPage()
            ->when($viewer instanceof UserModel, fn (Builder $own) => $own->orWhere('gametables_campaigns.created_by', $viewer?->id)));
    }

    /**
     * @param  Builder<CampaignModel>  $campaigns
     * @return list<LinkableRecordDTO>
     */
    private function latestFirst(Builder $campaigns, int $limit, ?UserModel $viewer): array
    {
        return $this->toRecords($campaigns
            ->orderByDesc('gametables_campaigns.created_at')
            ->orderBy('gametables_campaigns.id')
            ->limit($limit)
            ->get(), $viewer);
    }

    /**
     * Panel access is asked once for the whole answer, and only for an account.
     *
     * @param  Collection<int, CampaignModel>  $campaigns
     * @return list<LinkableRecordDTO>
     */
    private function toRecords(Collection $campaigns, ?UserModel $viewer): array
    {
        $editor = $viewer instanceof UserModel && $this->authorization->can($viewer, 'admin.access') ? $viewer : null;

        return array_values($campaigns->map(fn (CampaignModel $campaign): LinkableRecordDTO => new LinkableRecordDTO(
            type: self::TYPE,
            id: (string) $campaign->getKey(),
            title: $campaign->title,
            panelUrl: $editor instanceof UserModel ? $this->panelUrl($campaign, $editor) : null,
        ))->all());
    }

    private function panelUrl(CampaignModel $campaign, UserModel $editor): ?string
    {
        if (! Gate::forUser($editor)->allows('update', $campaign)) {
            return null;
        }

        try {
            return CampaignResource::getUrl('edit', ['record' => $campaign]);
        } catch (\Throwable) {
            // The panel is not built here (a queued job, a request outside it): no link, the record still shows
            return null;
        }
    }
}
