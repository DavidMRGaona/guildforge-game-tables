<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use App\Application\Services\SlugRedirectServiceInterface;
use Modules\GameTables\Http\Resources\GameTableListResource;
use Modules\GameTables\Http\Resources\GameTableResource;

final class GameTableController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly GameTableQueryServiceInterface $queryService,
        private readonly EligibilityServiceInterface $eligibilityService,
        private readonly RegistrationServiceInterface $registrationService,
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

        $format = $request->query('format');
        $format = is_string($format) && $format !== '' ? $format : null;

        $status = $request->query('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        // Resolve event by slug
        $eventSlug = $request->query('event');
        $eventId = null;
        if (is_string($eventSlug) && $eventSlug !== '') {
            $event = EventModel::where('slug', $eventSlug)->first();
            $eventId = $event?->id;
        }

        $campaignId = $request->query('campaign');
        $campaignId = is_string($campaignId) && $campaignId !== '' ? $campaignId : null;

        $tables = $this->queryService->getPublishedTablesPaginated(
            page: $page,
            perPage: self::PER_PAGE,
            gameSystemIds: $gameSystemIds,
            format: $format,
            status: $status,
            eventId: $eventId,
            campaignId: $campaignId,
        );

        $total = $this->queryService->getPublishedTablesTotal(
            gameSystemIds: $gameSystemIds,
            format: $format,
            status: $status,
            eventId: $eventId,
            campaignId: $campaignId,
        );

        $gameSystems = $this->queryService->getGameSystemsWithTables();
        $events = $this->queryService->getEventsWithTables();

        return Inertia::render('GameTables/Index', [
            'tables' => $this->buildPaginatedResponse(
                items: $tables,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: GameTableListResource::class,
            ),
            'gameSystems' => $gameSystems,
            'events' => $events,
            'currentFilters' => [
                'systems' => $gameSystemIds ?? [],
                'format' => $format,
                'status' => $status,
                'event' => $eventSlug ?? null,
                'campaign' => $campaignId,
            ],
        ]);
    }

    public function calendar(Request $request): Response
    {
        $monthParam = $request->query('month');
        $month = is_string($monthParam) ? Carbon::parse($monthParam)->startOfMonth() : Carbon::now()->startOfMonth();

        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $tables = $this->queryService->getUpcomingTables($from, $to);

        $gameSystems = $this->queryService->getGameSystemsWithTables();

        return Inertia::render('GameTables/Calendar', [
            'tables' => GameTableListResource::collection($tables)->resolve(),
            'gameSystems' => $gameSystems,
            'currentMonth' => $month->format('Y-m'),
        ]);
    }

    public function show(string $identifier): Response|RedirectResponse
    {
        // 1. Try to find by slug directly
        $table = $this->queryService->findPublishedBySlug($identifier);

        if ($table !== null) {
            return $this->renderShow($table);
        }

        // 2. Check if this is an old slug that redirects to a new one
        $currentSlug = $this->slugRedirectService->resolveCurrentSlug($identifier, 'game_table');
        if ($currentSlug !== null) {
            return redirect()->route('gametables.show', $currentSlug, 301);
        }

        // 3. If it looks like a UUID, try finding by ID and redirect to slug
        if (Str::isUuid($identifier)) {
            $table = $this->queryService->findPublished($identifier);
            if ($table !== null && $table->slug !== null) {
                return redirect()->route('gametables.show', $table->slug, 301);
            }
        }

        // 4. Not found
        abort(404);
    }

    /**
     * Render the show page for a game table.
     */
    private function renderShow(\Modules\GameTables\Application\DTOs\GameTableResponseDTO $table): Response
    {
        $user = auth()->user();
        $eligibility = null;
        $userRegistration = null;

        if ($user !== null) {
            // Get user eligibility
            $eligibility = $this->eligibilityService->canRegisterById($table->id, (string) $user->id);

            // Get current user's registration
            $registration = $this->registrationService->findByTableAndUser($table->id, (string) $user->id);
            $userRegistration = $registration !== null ? $this->formatRegistrationForFrontend($registration) : null;
        }

        return Inertia::render('GameTables/Show', [
            'table' => GameTableResource::make($table)->resolve(),
            'eligibility' => $eligibility,
            'userRegistration' => $userRegistration,
        ]);
    }

    /**
     * Format registration DTO for frontend (camelCase keys).
     *
     * @return array<string, mixed>
     */
    private function formatRegistrationForFrontend(\Modules\GameTables\Application\DTOs\ParticipantResponseDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'gameTableId' => $dto->gameTableId,
            'userId' => $dto->userId,
            'userName' => $dto->userName,
            'role' => $dto->role->value,
            'roleLabel' => $dto->role->label(),
            'roleColor' => $dto->role->color(),
            'status' => $dto->status->value,
            'statusLabel' => $dto->status->label(),
            'statusColor' => $dto->status->color(),
            'waitingListPosition' => $dto->waitingListPosition,
            'notes' => $dto->notes,
            'confirmedAt' => $dto->confirmedAt?->format('c'),
            'createdAt' => $dto->createdAt?->format('c'),
        ];
    }
}
