<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\SlugRedirectServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterQueryInterface;
use Modules\GameTables\Http\Concerns\ReadsListFilters;
use Modules\GameTables\Http\Resources\CampaignResource;

final class CampaignController extends Controller
{
    use BuildsPaginatedResponse;
    use ReadsListFilters;

    private const int PER_PAGE = 12;

    public function __construct(
        private readonly CampaignQueryServiceInterface $queryService,
        private readonly SlugRedirectServiceInterface $slugRedirectService,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly CampaignRosterQueryInterface $rosterQuery,
    ) {}

    public function index(Request $request): Response
    {
        $page = $this->getCurrentPage();

        $gameSystemIds = $this->readMultiValueFilter($request, 'systems');

        $status = $request->query('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        $campaigns = $this->queryService->getPublishedCampaignsPaginated(
            page: $page,
            perPage: self::PER_PAGE,
            gameSystemIds: $gameSystemIds,
            status: $status,
        );

        $total = $this->queryService->getPublishedCampaignsTotal(
            gameSystemIds: $gameSystemIds,
            status: $status,
        );

        $gameSystems = $this->queryService->getGameSystemsWithCampaigns();

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $this->buildPaginatedResponse(
                items: $campaigns,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: CampaignResource::class,
            ),
            'gameSystems' => $gameSystems,
            'currentFilters' => [
                'systems' => $gameSystemIds ?? [],
                'status' => $status,
            ],
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.campaigns_title'),
                description: (string) __('game-tables::messages.seo.campaigns_description'),
            ))->toArray(),
        ]);
    }

    public function show(string $identifier): Response|RedirectResponse
    {
        // 1. Try to find by slug directly
        $campaign = $this->queryService->findPublishedBySlug($identifier);

        if ($campaign instanceof CampaignResponseDTO) {
            return Inertia::render('Campaigns/Show', [
                'campaign' => CampaignResource::make($campaign)->resolve(),
                // What the viewer can do with the roster: ask to join, withdraw, leave or manage it
                'joinBox' => $this->rosterQuery->joinBox($campaign->id, auth()->id() !== null ? (string) auth()->id() : null)->toArray(),
                'seo' => $this->seo->forPage(new SeoPageDTO(
                    title: $campaign->title,
                    description: $campaign->description,
                    imagePublicId: $campaign->imagePublicId,
                ))->toArray(),
            ]);
        }

        // 2. Check if this is an old slug that redirects to a new one
        $currentSlug = $this->slugRedirectService->resolveCurrentSlug($identifier, 'campaign');
        if ($currentSlug !== null) {
            return redirect()->route('campaigns.show', $currentSlug, 301);
        }

        // 3. If it looks like a UUID, try finding by ID and redirect to slug
        if (Str::isUuid($identifier)) {
            $campaign = $this->queryService->findPublished($identifier);
            if ($campaign instanceof CampaignResponseDTO && $campaign->slug !== null) {
                return redirect()->route('campaigns.show', $campaign->slug, 301);
            }
        }

        // 4. Not found
        abort(404);
    }
}
