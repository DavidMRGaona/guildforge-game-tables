<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Entities\Event;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateCampaignDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventCreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\Language;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\Events\GameTableModerationApproved;
use Modules\GameTables\Domain\Events\GameTableModerationRejected;
use Modules\GameTables\Domain\Events\CampaignModerationApproved;
use Modules\GameTables\Domain\Events\CampaignModerationRejected;
use Modules\GameTables\Domain\Events\CampaignSubmittedForReview;
use Modules\GameTables\Domain\Events\GameTableSubmittedForReview;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\GameTableNotFoundException;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ContentWarningRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final readonly class FrontendCreationService implements FrontendCreationServiceInterface
{
    public function __construct(
        private CreationEligibilityServiceInterface $eligibilityService,
        private EventCreationEligibilityServiceInterface $eventEligibilityService,
        private GameTableServiceInterface $gameTableService,
        private GameTableSettingsReader $settingsReader,
        private GameTableRepositoryInterface $gameTableRepository,
        private CampaignRepositoryInterface $campaignRepository,
        private GameSystemRepositoryInterface $gameSystemRepository,
        private ContentWarningRepositoryInterface $contentWarningRepository,
        private AuthorizationServiceInterface $authorizationService,
        private EventRepositoryInterface $eventRepository,
        private GameMasterServiceInterface $gameMasterService,
        private EventGameTableConfigRepositoryInterface $configRepository,
        private CampaignSessionCreationGate $sessionGate,
        private CampaignRosterRepositoryInterface $roster,
    ) {}

    /**
     * Registrations close at the start of the table at the latest (the panel refuses a later date),
     * so the event start is only a valid default when the table does not start before it.
     */
    private function cappedToTableStart(\DateTimeImmutable $eventStart, \DateTimeImmutable $tableStart): \DateTimeImmutable
    {
        return $eventStart < $tableStart ? $eventStart : $tableStart;
    }

    public function createGameTable(FrontendCreateGameTableDTO $dto): GameTableResponseDTO
    {
        // 1. Check eligibility - use event-specific if event ID provided
        $this->ensureMayCreate($dto->createdBy, $dto->eventId, $dto->tableType, $dto->campaignId);

        // A table that would be published at once is refused before anything is saved when it has
        // fewer seats than its campaign roster; the transaction below covers a roster that grows
        // meanwhile (publish() checks again and nothing is kept)
        $creationStatus = $this->determineInitialStatus($dto->createdBy);

        if ($creationStatus === FrontendCreationStatus::Approved) {
            $this->ensureFitsRoster($dto);
        }

        return DB::transaction(fn (): GameTableResponseDTO => $this->persistNewTable($dto, $creationStatus));
    }

    /**
     * @throws SessionBelowRosterException when it is published at once with fewer seats than the roster
     */
    private function persistNewTable(FrontendCreateGameTableDTO $dto, FrontendCreationStatus $creationStatus): GameTableResponseDTO
    {
        // 2. Get registration closes date from event if applicable
        $registrationClosesAt = null;
        if ($dto->eventId !== null) {
            $event = $this->eventRepository->findById(new \App\Domain\ValueObjects\EventId($dto->eventId));
            if ($event !== null) {
                $registrationClosesAt = $this->cappedToTableStart($event->startDate(), $dto->startsAt);
            }
        }

        // 3. Convert to CreateGameTableDTO
        $createDto = $dto->toCreateGameTableDTO(
            $registrationClosesAt,
            $this->settingsReader->defaultRegistrationType(),
            $this->settingsReader->defaultAutoConfirm(),
            $this->settingsReader->defaultMembersEarlyAccessDays(),
        );

        // 4. Create the table (GameTableService handles the creation)
        $gameTable = $this->gameTableService->create($createDto);

        // 5. Retrieve the entity to update frontend creation status
        $tableEntity = $this->gameTableRepository->find(new GameTableId($gameTable->id));
        if ($tableEntity !== null) {
            $tableEntity->setFrontendCreationStatus($creationStatus);
            $this->gameTableRepository->save($tableEntity);
        }

        // 6. Sync game masters
        if ($dto->gameMasters !== null && count($dto->gameMasters) > 0) {
            $this->gameMasterService->syncTableGameMasters($gameTable->id, $dto->gameMasters);
        }

        // 7. Auto-publish for approved status
        if ($creationStatus === FrontendCreationStatus::Approved) {
            $gameTable = $this->gameTableService->publish($gameTable->id);
        }

        return $gameTable;
    }

    /**
     * Creating, editing and sending to review a table follow the usual creation rules (the event's
     * when it belongs to one), with one exception: directing an approved campaign is enough to
     * create its sessions outside an event (only the main creation switch applies).
     *
     * @throws NotEligibleToCreateException
     */
    private function ensureMayCreate(string $userId, ?string $eventId, TableType $tableType, ?string $campaignId): void
    {
        $eligibility = $eventId !== null
            ? $this->eventEligibilityService->canCreateTableForEvent($eventId, $userId)
            : $this->eligibilityService->canCreateTable($userId);

        if ($eligibility->eligible || $this->isSessionOfDirectedCampaign($userId, $eventId, $tableType, $campaignId)) {
            return;
        }

        throw NotEligibleToCreateException::withReason($eligibility->reason ?? 'unknown');
    }

    private function isSessionOfDirectedCampaign(string $userId, ?string $eventId, TableType $tableType, ?string $campaignId): bool
    {
        return $eventId === null
            && $tableType === TableType::CampaignSession
            && $campaignId !== null
            && $this->sessionGate->allows($userId, $campaignId);
    }

    /**
     * @throws SessionBelowRosterException
     */
    private function ensureFitsRoster(FrontendCreateGameTableDTO $dto): void
    {
        if ($dto->campaignId === null) {
            return;
        }

        $rosterSize = $this->roster->count(new CampaignId($dto->campaignId));

        if ($dto->maxPlayers < $rosterSize) {
            throw SessionBelowRosterException::forNewSession($dto->maxPlayers, $rosterSize);
        }
    }

    public function createCampaign(FrontendCreateCampaignDTO $dto): CampaignResponseDTO
    {
        // 1. Check eligibility
        $eligibility = $this->eligibilityService->canCreateCampaign($dto->createdBy);
        if (! $eligibility->eligible) {
            throw NotEligibleToCreateException::withReason($eligibility->reason ?? 'unknown');
        }

        // 2. Determine initial status based on publication mode
        $creationStatus = $this->determineInitialStatus($dto->createdBy);

        // 3. Create the campaign entity
        $campaignId = new CampaignId((string) \Illuminate\Support\Str::uuid());
        $campaign = new Campaign(
            id: $campaignId,
            gameSystemId: new GameSystemId($dto->gameSystemId),
            createdBy: $dto->createdBy,
            title: $dto->title,
            // Same unique slug as a campaign created from the panel (title plus the start of the id)
            slug: (new CampaignModel)->generateUniqueSlug($dto->title, $campaignId->value),
            status: CampaignStatus::Recruiting,
            description: $dto->synopsis,
            frequency: $dto->frequency,
            maxPlayers: $dto->maxPlayers,
            isPublished: $creationStatus === FrontendCreationStatus::Approved,
            frontendCreationStatus: $creationStatus,
        );

        // 4. Persist the campaign
        $this->campaignRepository->save($campaign);

        // 5. Get game system name for response
        $gameSystem = $this->gameSystemRepository->find(new GameSystemId($dto->gameSystemId));
        $gameSystemName = $gameSystem?->name ?? '';

        return CampaignResponseDTO::fromEntity($campaign, $gameSystemName);
    }

    /**
     * @return array{
     *     game_systems: array<array{id: string, name: string}>,
     *     table_types: array<string, string>,
     *     table_formats: array<string, string>,
     *     experience_levels: array<string, string>,
     *     tones: array<string, string>,
     *     character_creation: array<string, string>,
     *     safety_tools: array<string, string>,
     *     content_warnings: array<array{id: string, name: string, description: string|null, severity: string}>,
     *     genres: array<string, string>,
     *     languages: array<string, string>,
     *     campaign_frequencies: array<string, string>,
     *     events: array<array{id: string, name: string}>,
     *     campaigns: list<array{id: string, name: string, game_system_id: string, max_players: int|null, roster_count: int}>,
     *     defaults: array{duration_minutes: int, min_players: int, max_players: int, max_spectators: int},
     *     limits: array{max_players: int, max_spectators: int, max_duration_minutes: int}
     * }
     */
    public function getCreateFormData(?string $keepCampaignId = null): array
    {
        // Get game systems
        $gameSystems = $this->gameSystemRepository->getActive();

        // Get content warnings
        $contentWarnings = $this->contentWarningRepository->getActive();

        $events = $this->selectableEvents();

        // Campaigns the current user may create sessions of, plus the one an edited table keeps
        $userId = auth()->id();
        $campaigns = $userId !== null
            ? $this->sessionGate->sessionCampaigns((string) $userId)
            : [];

        if ($keepCampaignId !== null && ! in_array($keepCampaignId, array_column($campaigns, 'id'), true)) {
            $kept = $this->sessionGate->describe($keepCampaignId);

            if ($kept !== null) {
                $campaigns[] = $kept;
            }
        }

        return [
            'game_systems' => array_map(
                static fn ($gs): array => ['id' => $gs->id->value, 'name' => $gs->name],
                $gameSystems,
            ),
            'table_types' => TableType::options(),
            'table_formats' => TableFormat::options(),
            'experience_levels' => ExperienceLevel::options(),
            'tones' => Tone::options(),
            'character_creation' => CharacterCreation::options(),
            'safety_tools' => SafetyTool::options(),
            'content_warnings' => array_map(
                static fn ($cw): array => [
                    'id' => $cw->id->value,
                    'name' => $cw->name,
                    'description' => $cw->description,
                    'severity' => $cw->severity->value,
                ],
                $contentWarnings,
            ),
            'genres' => Genre::options(),
            'languages' => Language::options(),
            'campaign_frequencies' => CampaignFrequency::options(),
            'events' => $events->map(
                static fn ($event): array => [
                    'id' => $event->id()->value,
                    'name' => $event->title(),
                    'start_date' => $event->startDate()->format('Y-m-d\TH:i'),
                ],
            )->values()->all(),
            'campaigns' => $campaigns,
            'defaults' => [
                'duration_minutes' => $this->settingsReader->defaultDurationMinutes(),
                'min_players' => $this->settingsReader->defaultMinPlayers(),
                'max_players' => $this->settingsReader->defaultMaxPlayers(),
                'max_spectators' => $this->settingsReader->defaultMaxSpectators(),
            ],
            'limits' => [
                'max_players' => $this->settingsReader->maxPlayersLimit(),
                'max_spectators' => $this->settingsReader->maxSpectatorsLimit(),
                'max_duration_minutes' => $this->settingsReader->maxDurationMinutes(),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function selectableEventIds(): array
    {
        return $this->selectableEvents()
            ->map(static fn (Event $event): string => $event->id()->value)
            ->values()
            ->all();
    }

    /**
     * Current or future published events that have tables enabled, by start date: the events the
     * creation form offers and the only ones the server accepts for a new link.
     *
     * @return Collection<int, Event>
     */
    private function selectableEvents(): Collection
    {
        $now = new \DateTimeImmutable;
        $enabledEventIds = $this->configRepository->getEnabledEventIds();

        return $this->eventRepository->findPublished()
            ->filter(static fn (Event $event): bool => $event->endDate() >= $now)
            ->filter(static fn (Event $event): bool => in_array($event->id()->value, $enabledEventIds, true))
            ->sortBy(static fn (Event $event): \DateTimeImmutable => $event->startDate());
    }

    /**
     * @return array<GameTableResponseDTO>
     */
    public function getUserDrafts(string $userId): array
    {
        // Get all tables created by user in draft status, with game masters loaded
        $tables = \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel::query()
            ->with(['gameSystem', 'gameMasters.user'])
            ->where('created_by', $userId)
            ->where('is_published', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return $tables->map(function ($model): GameTableResponseDTO {
            $gameMasters = $model->gameMasters->map(
                fn ($gm) => GameMasterResponseDTO::fromModel($gm, $model->id)
            )->all();

            $table = $this->gameTableRepository->find(new GameTableId($model->id));
            if ($table === null) {
                throw GameTableNotFoundException::withId($model->id);
            }

            return GameTableResponseDTO::fromEntity($table, gameMasters: $gameMasters);
        })->all();
    }

    public function submitForReview(string $tableId, string $userId): void
    {
        $table = $this->gameTableRepository->find(new GameTableId($tableId));

        if ($table === null) {
            throw GameTableNotFoundException::withId($tableId);
        }

        if ($table->createdBy !== $userId) {
            throw NotEditableException::forStatus('not_owner');
        }

        // Only unpublished drafts (and legacy rejected tables) go to review: a repeated request sends nothing
        if (! $table->canBeChangedByAuthor()) {
            throw $this->notChangeableByAuthor($table->isPublished, $table->frontendCreationStatus);
        }

        // The same rules as creating it
        $this->ensureMayCreate($userId, $table->eventId, $table->tableType, $table->campaignId?->value);

        // Sent to review: clears the reason of an earlier rejection
        $table->submitForModeration();

        // In 'auto' mode, or for a user who may publish without review, it is approved and
        // published at once, as a campaign is
        $mode = $this->settingsReader->getPublicationMode();

        if ($mode === 'auto' || $this->checkAutoPublishEligibility($userId)) {
            // Refused before anything is saved: the table stays a draft
            $this->gameTableService->ensureFitsRoster($tableId);

            $table->approveFrontendCreation();
            $this->gameTableRepository->save($table);
            $this->gameTableService->publish($tableId);
        } else {
            $this->gameTableRepository->save($table);

            GameTableSubmittedForReview::dispatch(
                $table->id->value,
                $table->title,
                $table->createdBy ?? '',
            );
        }
    }

    public function updateDraft(string $tableId, FrontendCreateGameTableDTO $dto): GameTableResponseDTO
    {
        $table = $this->gameTableRepository->find(new GameTableId($tableId));

        if ($table === null) {
            throw GameTableNotFoundException::withId($tableId);
        }

        if ($table->createdBy !== $dto->createdBy) {
            throw NotEditableException::forStatus('not_owner');
        }

        // Only unpublished drafts (and legacy rejected tables) are edited by their author
        if (! $table->canBeChangedByAuthor()) {
            throw $this->notChangeableByAuthor($table->isPublished, $table->frontendCreationStatus);
        }

        // The same rules as creating it, for the table as it will be saved
        $this->ensureMayCreate($dto->createdBy, $dto->eventId, $dto->tableType, $dto->campaignId);

        // Default registration closes at to event start date if event is set and no existing value
        $registrationClosesAt = $table->registrationClosesAt;
        if ($dto->eventId !== null && $registrationClosesAt === null) {
            $event = $this->eventRepository->findById(new \App\Domain\ValueObjects\EventId($dto->eventId));
            if ($event !== null) {
                $registrationClosesAt = $this->cappedToTableStart($event->startDate(), $dto->startsAt);
            }
        }

        // A kept date must not end up after the (possibly earlier) start of the table
        if ($registrationClosesAt !== null) {
            $registrationClosesAt = $this->cappedToTableStart(
                \DateTimeImmutable::createFromInterface($registrationClosesAt),
                $dto->startsAt,
            );
        }

        // Update the table via the service
        $updateDto = new \Modules\GameTables\Application\DTOs\UpdateGameTableDTO(
            id: $tableId,
            title: $dto->title,
            startsAt: $dto->startsAt,
            durationMinutes: $dto->durationMinutes,
            tableType: $dto->tableType,
            tableFormat: $dto->tableFormat,
            minPlayers: $dto->minPlayers,
            maxPlayers: $dto->maxPlayers,
            campaignId: $dto->campaignId,
            eventId: $dto->eventId,
            maxSpectators: $dto->maxSpectators,
            synopsis: $dto->synopsis,
            location: $dto->location,
            onlineUrl: $dto->onlineUrl,
            minimumAge: $dto->minimumAge,
            language: $dto->language,
            genres: $dto->genres,
            tone: $dto->tone,
            experienceLevel: $dto->experienceLevel,
            characterCreation: $dto->characterCreation,
            safetyTools: $dto->safetyTools,
            contentWarningIds: $dto->contentWarningIds,
            customWarnings: $dto->customWarnings,
            registrationClosesAt: $registrationClosesAt,
            notes: $dto->notes,
        );

        $result = $this->gameTableService->update($updateDto);

        // Sync game masters
        if ($dto->gameMasters !== null && count($dto->gameMasters) > 0) {
            $this->gameMasterService->syncTableGameMasters($tableId, $dto->gameMasters);
        }

        return $result;
    }

    public function deleteDraft(string $tableId, string $userId): void
    {
        $table = $this->gameTableRepository->find(new GameTableId($tableId));

        if ($table === null) {
            throw GameTableNotFoundException::withId($tableId);
        }

        if ($table->createdBy !== $userId) {
            throw NotEditableException::forStatus('not_owner');
        }

        // Only unpublished drafts (and legacy rejected tables) are deleted by their author
        if (! $table->canBeChangedByAuthor()) {
            throw $this->notChangeableByAuthor($table->isPublished, $table->frontendCreationStatus);
        }

        $this->gameTableService->delete($tableId);
    }

    public function approveFrontendCreation(string $tableId, ?string $notes = null): void
    {
        $table = $this->gameTableRepository->find(new GameTableId($tableId));

        if ($table === null) {
            throw GameTableNotFoundException::withId($tableId);
        }

        // Only what the author sent to review is approved (and so published)
        $this->assertInReview($table->frontendCreationStatus);

        // Refused before anything is saved: the table stays in review
        $this->gameTableService->ensureFitsRoster($tableId);

        $table->approveFrontendCreation($notes);
        $this->gameTableRepository->save($table);

        // Publish the table when approved
        $this->gameTableService->publish($tableId);

        // Dispatch event to notify the creator
        GameTableModerationApproved::dispatch(
            $table->id->value,
            $table->title,
            $table->createdBy ?? '',
            $notes,
        );
    }

    public function rejectFrontendCreation(string $tableId, string $reason): void
    {
        $table = $this->gameTableRepository->find(new GameTableId($tableId));

        if ($table === null) {
            throw GameTableNotFoundException::withId($tableId);
        }

        $this->assertInReview($table->frontendCreationStatus);

        // Back to the author as a draft that keeps the reason
        $table->rejectFrontendCreation($reason);
        $this->gameTableRepository->save($table);

        // Dispatch event to notify the creator
        GameTableModerationRejected::dispatch(
            $table->id->value,
            $table->title,
            $table->createdBy ?? '',
            $reason,
        );
    }

    /**
     * @return array<CampaignResponseDTO>
     */
    public function getUserCampaignDrafts(string $userId): array
    {
        // Get all campaigns created by user
        $campaigns = $this->campaignRepository->getByCreator($userId);

        // Filter to only include drafts (frontend-created, unpublished campaigns)
        $draftCampaigns = array_filter(
            $campaigns,
            fn (Campaign $campaign): bool => $campaign->frontendCreationStatus !== null && ! $campaign->isPublished,
        );

        return array_map(
            function (Campaign $campaign): CampaignResponseDTO {
                $gameSystem = $this->gameSystemRepository->find($campaign->gameSystemId);

                return CampaignResponseDTO::fromEntity($campaign, $gameSystem?->name ?? '');
            },
            array_values($draftCampaigns),
        );
    }

    public function updateCampaignDraft(string $campaignId, FrontendCreateCampaignDTO $dto): CampaignResponseDTO
    {
        $campaign = $this->campaignRepository->find(new CampaignId($campaignId));

        if ($campaign === null) {
            throw CampaignNotFoundException::withId($campaignId);
        }

        if ($campaign->createdBy !== $dto->createdBy) {
            throw NotEditableException::forStatus('not_owner');
        }

        // Only unpublished drafts (and legacy rejected campaigns) are edited by their author
        if (! $campaign->canBeChangedByAuthor()) {
            throw $this->notChangeableByAuthor($campaign->isPublished, $campaign->frontendCreationStatus);
        }

        // Update the campaign
        // The slug is left as it is: the model renews it when the title changes and keeps the old one redirecting
        $campaign->title = $dto->title;
        $campaign->description = $dto->synopsis;
        $campaign->frequency = $dto->frequency;
        $campaign->maxPlayers = $dto->maxPlayers;

        // A campaign rejected before 2.10.0 (legacy status) becomes a draft on edit, keeping the reason
        if ($campaign->frontendCreationStatus === FrontendCreationStatus::Rejected) {
            $campaign->setFrontendCreationStatus(FrontendCreationStatus::Draft);
        }

        $this->campaignRepository->save($campaign);

        $gameSystem = $this->gameSystemRepository->find($campaign->gameSystemId);

        return CampaignResponseDTO::fromEntity($campaign, $gameSystem?->name ?? '');
    }

    public function submitCampaignForReview(string $campaignId, string $userId): void
    {
        $campaign = $this->campaignRepository->find(new CampaignId($campaignId));

        if ($campaign === null) {
            throw CampaignNotFoundException::withId($campaignId);
        }

        if ($campaign->createdBy !== $userId) {
            throw NotEditableException::forStatus('not_owner');
        }

        // Only unpublished drafts (and legacy rejected campaigns) go to review: a repeated request sends nothing
        if (! $campaign->canBeChangedByAuthor()) {
            throw $this->notChangeableByAuthor($campaign->isPublished, $campaign->frontendCreationStatus);
        }

        // Submit for moderation
        $campaign->submitForModeration();

        // Check if auto-publish eligible
        $mode = $this->settingsReader->getPublicationMode();

        $autoPublish = $mode === 'auto' || $this->checkAutoPublishEligibility($userId);

        if ($autoPublish) {
            $campaign->approveFrontendCreation();
            $campaign->publish();
        }

        $this->campaignRepository->save($campaign);

        if (! $autoPublish) {
            CampaignSubmittedForReview::dispatch(
                $campaign->id->value,
                $campaign->title,
                $campaign->createdBy,
            );
        }
    }

    public function approveCampaign(string $campaignId, ?string $notes = null): void
    {
        $campaign = $this->campaignRepository->find(new CampaignId($campaignId));

        if ($campaign === null) {
            throw CampaignNotFoundException::withId($campaignId);
        }

        $this->assertInReview($campaign->frontendCreationStatus);

        $campaign->approveFrontendCreation($notes);
        $campaign->publish();
        $this->campaignRepository->save($campaign);

        // Dispatch event to notify the creator
        CampaignModerationApproved::dispatch(
            $campaign->id->value,
            $campaign->title,
            $campaign->createdBy,
            $campaign->slug,
            $notes,
        );
    }

    public function rejectCampaign(string $campaignId, string $reason): void
    {
        $campaign = $this->campaignRepository->find(new CampaignId($campaignId));

        if ($campaign === null) {
            throw CampaignNotFoundException::withId($campaignId);
        }

        $this->assertInReview($campaign->frontendCreationStatus);

        // Back to the author as a draft that keeps the reason
        $campaign->rejectFrontendCreation($reason);
        $this->campaignRepository->save($campaign);

        // Dispatch event to notify the creator
        CampaignModerationRejected::dispatch(
            $campaign->id->value,
            $campaign->title,
            $campaign->createdBy,
            $reason,
        );
    }

    public function deleteCampaignDraft(string $campaignId, string $userId): void
    {
        $campaign = $this->campaignRepository->find(new CampaignId($campaignId));

        if ($campaign === null) {
            throw CampaignNotFoundException::withId($campaignId);
        }

        if ($campaign->createdBy !== $userId) {
            throw NotEditableException::forStatus('not_owner');
        }

        // Only unpublished drafts (and legacy rejected campaigns) are deleted by their author
        if (! $campaign->canBeChangedByAuthor()) {
            throw $this->notChangeableByAuthor($campaign->isPublished, $campaign->frontendCreationStatus);
        }

        $this->campaignRepository->delete($campaign->id);
    }

    /**
     * Approving or rejecting is only for an item its author sent to review.
     */
    private function assertInReview(?FrontendCreationStatus $status): void
    {
        if ($status !== FrontendCreationStatus::PendingReview) {
            throw NotEditableException::forStatus($status->value ?? 'not_frontend');
        }
    }

    /**
     * Refusal for an author changing a table or campaign outside an unpublished draft (or legacy rejected item).
     */
    private function notChangeableByAuthor(bool $isPublished, ?FrontendCreationStatus $status): NotEditableException
    {
        return NotEditableException::forStatus($isPublished ? 'published' : ($status->value ?? 'not_frontend'));
    }

    private function determineInitialStatus(string $userId): FrontendCreationStatus
    {
        $mode = $this->settingsReader->getPublicationMode();

        return match ($mode) {
            'auto' => FrontendCreationStatus::Approved,
            'approval' => FrontendCreationStatus::Draft,
            'role_based' => $this->checkAutoPublishEligibility($userId)
                ? FrontendCreationStatus::Approved
                : FrontendCreationStatus::Draft,
            default => FrontendCreationStatus::Draft,
        };
    }

    private function checkAutoPublishEligibility(string $userId): bool
    {
        $user = UserModel::find($userId);
        if ($user === null) {
            return false;
        }

        // Check if user has any of the auto-publish roles
        $roles = $this->settingsReader->getAutoPublishRoles();
        if (! empty($roles) && $this->authorizationService->hasAnyRole($user, $roles)) {
            return true;
        }

        // Check if user has any of the auto-publish permissions
        $permissions = $this->settingsReader->getAutoPublishPermissions();
        if (! empty($permissions) && $this->authorizationService->canAny($user, $permissions)) {
            return true;
        }

        return false;
    }
}
