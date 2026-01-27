<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
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

        $eventId = $request->query('event');
        $eventId = is_string($eventId) && $eventId !== '' ? $eventId : null;

        $tables = $this->queryService->getPublishedTablesPaginated(
            page: $page,
            perPage: self::PER_PAGE,
            gameSystemIds: $gameSystemIds,
            format: $format,
            status: $status,
            eventId: $eventId,
        );

        $total = $this->queryService->getPublishedTablesTotal(
            gameSystemIds: $gameSystemIds,
            format: $format,
            status: $status,
            eventId: $eventId,
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
                'event' => $eventId,
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

    public function show(string $id): Response
    {
        $table = $this->queryService->findPublished($id);

        if ($table === null) {
            abort(404);
        }

        $user = auth()->user();
        $eligibility = null;
        $userRegistration = null;

        if ($user !== null) {
            // Get user eligibility
            $eligibility = $this->eligibilityService->canRegisterById($id, (string) $user->id);

            // Get current user's registration
            $registration = $this->registrationService->findByTableAndUser($id, (string) $user->id);
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
