<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use App\Application\Services\SlugRedirectServiceInterface;
use Modules\GameTables\Http\Resources\CampaignResource;

final class CampaignController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly CampaignQueryServiceInterface $queryService,
        private readonly GameTableQueryServiceInterface $tableQueryService,
        private readonly SlugRedirectServiceInterface $slugRedirectService,
    ) {}

    public function index(Request $request): Response
    {
        $page = $this->getCurrentPage();

        $gameSystemsParam = $request->query('systems');
        $gameSystemIds = null;
        if (is_string($gameSystemsParam) && $gameSystemsParam !== '') {
            $gameSystemIds = array_filter(explode(',', $gameSystemsParam));
        }

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

        $gameSystems = $this->tableQueryService->getGameSystemsWithTables();

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
        ]);
    }

    public function show(string $identifier): Response|RedirectResponse
    {
        // 1. Try to find by slug directly
        $campaign = $this->queryService->findPublishedBySlug($identifier);

        if ($campaign !== null) {
            return Inertia::render('Campaigns/Show', [
                'campaign' => CampaignResource::make($campaign)->resolve(),
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
            if ($campaign !== null && $campaign->slug !== null) {
                return redirect()->route('campaigns.show', $campaign->slug, 301);
            }
        }

        // 4. Not found
        abort(404);
    }
}
