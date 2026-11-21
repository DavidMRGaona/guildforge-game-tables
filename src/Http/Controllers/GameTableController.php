<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\AddToCalendarLinksFactoryInterface;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\DTOs\SeoScheduleDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\SlugRedirectServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\Services\AssociationRoleNameInterface;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterQueryInterface;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Http\Concerns\ReadsListFilters;
use Modules\GameTables\Http\Resources\GameTableListResource;
use Modules\GameTables\Http\Resources\GameTableResource;
use Modules\GameTables\Http\Support\GenreFilter;
use Modules\GameTables\Http\Support\SubjectSurveyLinks;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\GuestConfirmationPolicy;
use Modules\GameTables\Infrastructure\Services\SeatCounter;
use Modules\GameTables\Infrastructure\Surveys\TableSurveySubject;

final class GameTableController extends Controller
{
    use BuildsPaginatedResponse;
    use ReadsListFilters;

    private const int PER_PAGE = 12;

    public function __construct(
        private readonly GameTableQueryServiceInterface $queryService,
        private readonly EligibilityServiceInterface $eligibilityService,
        private readonly RegistrationServiceInterface $registrationService,
        private readonly SlugRedirectServiceInterface $slugRedirectService,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly SeatCounter $seatCounter,
        private readonly GameTableRepositoryInterface $gameTableRepository,
        private readonly GuestConfirmationPolicy $guestConfirmationPolicy,
        private readonly CampaignRosterQueryInterface $rosterQuery,
        private readonly SubjectSurveyLinks $surveyLinks,
        private readonly CalendarAggregatorServiceInterface $calendar,
        private readonly AddToCalendarLinksFactoryInterface $calendarLinks,
        private readonly CampaignQueryServiceInterface $campaignQuery,
    ) {}

    public function index(Request $request): Response
    {
        $page = $this->getCurrentPage();
        $filters = $this->readTableFilters($request);

        $tables = $this->queryService->getPublishedTablesPaginated(
            page: $page,
            perPage: self::PER_PAGE,
            gameSystemIds: $filters['systems'],
            format: $filters['format'],
            status: $filters['status'],
            eventId: $filters['eventId'],
            campaignId: $filters['campaign']?->id,
            genre: $filters['genre'],
        );

        $total = $this->queryService->getPublishedTablesTotal(
            gameSystemIds: $filters['systems'],
            format: $filters['format'],
            status: $filters['status'],
            eventId: $filters['eventId'],
            campaignId: $filters['campaign']?->id,
            genre: $filters['genre'],
        );

        return Inertia::render('GameTables/Index', [
            'tables' => $this->buildPaginatedResponse(
                items: $tables,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: GameTableListResource::class,
            ),
            ...$this->filterProps($filters),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.tables_title'),
                description: (string) __('game-tables::messages.seo.tables_description'),
            ))->toArray(),
        ]);
    }

    public function calendar(Request $request): Response
    {
        $timezone = app(TimezoneServiceInterface::class);
        $monthParam = $request->query('month');
        // The month of the site's calendar: a month typed in the URL (YYYY-MM) is read there, and
        // so is today; anything else shows the current month rather than failing
        $month = is_string($monthParam) && preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $monthParam) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $monthParam, $timezone->displayTimezone())
            : null;
        $month = $month instanceof CarbonImmutable ? $month : $timezone->today();

        [$from, $next] = $timezone->displayMonthRange($month);
        // The range is [start, end) and the query includes its bounds
        $to = $next->sub(new DateInterval('PT1S'));

        // The same filters as the list, within the month (#314)
        $filters = $this->readTableFilters($request);

        $tables = $this->queryService->getUpcomingTables(
            $from,
            $to,
            gameSystemIds: $filters['systems'],
            format: $filters['format'],
            status: $filters['status'],
            eventId: $filters['eventId'],
            campaignId: $filters['campaign']?->id,
            genre: $filters['genre'],
        );

        return Inertia::render('GameTables/Calendar', [
            'tables' => GameTableListResource::collection($tables)->resolve(),
            ...$this->filterProps($filters),
            'currentMonth' => $timezone->format($from, 'Y-m'),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.calendar_title'),
                description: (string) __('game-tables::messages.seo.tables_description'),
            ))->toArray(),
        ]);
    }

    /**
     * The filters of the tables list and calendar, read alike: a malformed or unknown value is no
     * filter, never an error.
     *
     * @return array{systems: array<int, string>|null, format: string|null, status: string|null, eventSlug: string|null, eventId: string|null, campaign: CampaignResponseDTO|null, genre: string|null}
     */
    private function readTableFilters(Request $request): array
    {
        $format = $request->query('format');
        $status = $request->query('status');

        // Resolve event by slug
        $eventSlug = $request->query('event');
        $eventSlug = is_string($eventSlug) && $eventSlug !== '' ? $eventSlug : null;
        $eventId = $eventSlug !== null ? EventModel::where('slug', $eventSlug)->first()?->id : null;

        // Only a campaign with a public page filters the tables, since the filter shows its title
        $campaignId = $this->readUuidFilter($request, 'campaign');

        return [
            'systems' => $this->readUuidListFilter($request, 'systems'),
            'format' => is_string($format) && $format !== '' ? $format : null,
            'status' => is_string($status) && $status !== '' ? $status : null,
            'eventSlug' => $eventSlug,
            'eventId' => is_string($eventId) ? $eventId : null,
            'campaign' => $campaignId !== null ? $this->campaignQuery->findPublished($campaignId) : null,
            'genre' => GenreFilter::chosen($request),
        ];
    }

    /**
     * What the filters of the tables list and calendar draw: the choices and the current values
     *
     * @param  array{systems: array<int, string>|null, format: string|null, status: string|null, eventSlug: string|null, eventId: string|null, campaign: CampaignResponseDTO|null, genre: string|null}  $filters
     * @return array<string, mixed>
     */
    private function filterProps(array $filters): array
    {
        $campaign = $filters['campaign'];

        return [
            'gameSystems' => $this->queryService->getGameSystemsWithTables(),
            'events' => $this->queryService->getEventsWithTables(),
            'genres' => GenreFilter::options(
                $this->queryService->getGenreKeysInList($filters['eventId'], $campaign?->id),
                $filters['genre'],
            ),
            'campaign' => $campaign instanceof CampaignResponseDTO ? ['id' => $campaign->id, 'title' => $campaign->title] : null,
            'currentFilters' => [
                'systems' => $filters['systems'] ?? [],
                'format' => $filters['format'],
                'status' => $filters['status'],
                'event' => $filters['eventSlug'],
                'campaign' => $campaign?->id,
                'genre' => $filters['genre'],
            ],
        ];
    }

    public function show(string $identifier): Response|RedirectResponse
    {
        // 1. Try to find by slug directly
        $table = $this->queryService->findPublishedBySlug($identifier);

        if ($table instanceof GameTableResponseDTO) {
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
            if ($table instanceof GameTableResponseDTO && $table->slug !== null) {
                return redirect()->route('gametables.show', $table->slug, 301);
            }
        }

        // 4. Not found
        abort(404);
    }

    /**
     * Render the show page for a game table.
     */
    /**
     * @return array<string, mixed>
     */
    private function tableSeo(GameTableResponseDTO $table): array
    {
        $schedule = null;

        if ($table->startsAt instanceof \DateTimeInterface) {
            $start = DateTimeImmutable::createFromInterface($table->startsAt);
            $schedule = new SeoScheduleDTO(
                start: $start,
                end: $start->add(new DateInterval("PT{$table->durationMinutes}M")),
                location: $table->location,
            );
        }

        return $this->seo->forPage(new SeoPageDTO(
            title: $table->title,
            description: $table->synopsis,
            imagePublicId: $table->imagePublicId,
            schedule: $schedule,
        ))->toArray();
    }

    private function renderShow(GameTableResponseDTO $table): Response
    {
        $user = auth()->user();
        $now = CarbonImmutable::now()->toDateTimeImmutable();
        $eligibility = null;
        $guestEligibility = null;
        $userRegistration = null;

        if ($user === null) {
            // What a visitor without an account can do: the same rules the guest registration applies
            $guestEligibility = [
                ...$this->eligibilityService->guestEligibility($table->id),
                // What the form says before it is sent: the place held until a deadline, or not guaranteed
                'confirmation' => $this->guestConfirmationNotice($table->id, $now),
            ];
        } else {
            // Get current user's registration
            $registration = $this->registrationService->findByTableAndUser($table->id, (string) $user->id);
            $userRegistration = $registration instanceof ParticipantResponseDTO ? $this->formatRegistrationForFrontend($registration) : null;

            $withdrawalRefusal = $registration instanceof ParticipantResponseDTO
                ? $this->registrationService->withdrawalRefusal($registration->id, $now)
                : 'not_allowed';

            $eligibility = [
                ...$this->eligibilityService->canRegisterById($table->id, (string) $user->id),
                // Whether the button may offer the cancellation: the rule the server applies on submit
                'canCancel' => $withdrawalRefusal === null,
                // Why not, so the disabled button says whether the table started or ended
                'withdrawalRefusal' => $withdrawalRefusal,
            ];
        }

        return Inertia::render('GameTables/Show', [
            // Seats counted by the server with the rule it applies on registration
            'table' => GameTableResource::make($table, $this->seatCounter->forTable($table))->resolve(),
            // The site's association member role, named by the early access line
            'associationRole' => app(AssociationRoleNameInterface::class)->displayName(),
            'eligibility' => $eligibility,
            'guestEligibility' => $guestEligibility,
            'userRegistration' => $userRegistration,
            // «Gestionar inscritos»: a director of a table that delegates its registrations
            'canManageRegistrations' => $user instanceof UserModel
                && $table->campaignId === null
                && Gate::forUser($user)->allows('manageRegistrations', GameTableModel::query()->findOrFail($table->id)),
            // A campaign session: whether it is closed to outsiders and where a roster member stands
            'campaignSession' => $this->rosterQuery->sessionBox($table->id, $user !== null ? (string) $user->id : null, $now)?->toArray(),
            // Its surveys, through the core: a surveys module answers, and without one nothing shows
            'surveyLinks' => $user instanceof UserModel
                ? $this->surveyLinks->for(TableSurveySubject::sourceType($table->campaignId !== null), $table->id, (string) $user->id)
                : null,
            // «Añadir al calendario»: the entry the public calendar shows, through the core
            'calendarLinks' => $this->calendarLinksFor($table->id),
            'seo' => $this->tableSeo($table),
        ]);
    }

    /**
     * @return array{google: string, outlook: string, ics: string|null}|null
     */
    private function calendarLinksFor(string $gameTableId): ?array
    {
        $entry = $this->calendar->findEntry('game-table', $gameTableId);

        return $entry instanceof CalendarEntryDTO ? $this->calendarLinks->for($entry)->toArray() : null;
    }

    /**
     * The terms a guest registration would get now, as the policy applies them on submit.
     *
     * @return array{mode: 'hold'|'wait', deadline: string|null}|null
     */
    private function guestConfirmationNotice(string $gameTableId, DateTimeImmutable $now): ?array
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($gameTableId));

        return $gameTable instanceof GameTable ? $this->guestConfirmationPolicy->noticeFor($gameTable, $now) : null;
    }

    /**
     * Format registration DTO for frontend (camelCase keys).
     *
     * @return array<string, mixed>
     */
    private function formatRegistrationForFrontend(ParticipantResponseDTO $dto): array
    {
        $timezone = app(TimezoneServiceInterface::class);

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
            // The place among those waiting, not the stored position (which may have gaps)
            'waitingListPosition' => $dto->waitingListRank,
            'notes' => $dto->notes,
            'confirmedAt' => $timezone->iso($dto->confirmedAt),
            'createdAt' => $timezone->iso($dto->createdAt),
            'fromRoster' => $dto->fromRoster,
        ];
    }
}
