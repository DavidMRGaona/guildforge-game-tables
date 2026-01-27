<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Http\Resources\CampaignResource;

final class CampaignController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly CampaignQueryServiceInterface $queryService,
        private readonly GameTableQueryServiceInterface $tableQueryService,
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

    public function show(string $id): Response
    {
        $campaign = $this->queryService->findPublished($id);

        if ($campaign === null) {
            abort(404);
        }

        return Inertia::render('Campaigns/Show', [
            'campaign' => CampaignResource::make($campaign)->resolve(),
        ]);
    }
}
