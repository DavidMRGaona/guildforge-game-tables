<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateCampaignDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\GameTableNotFoundException;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ContentWarningRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

final readonly class FrontendCreationService implements FrontendCreationServiceInterface
{
    public function __construct(
        private CreationEligibilityServiceInterface $eligibilityService,
        private GameTableServiceInterface $gameTableService,
        private GameTableSettingsReader $settingsReader,
        private GameTableRepositoryInterface $gameTableRepository,
        private CampaignRepositoryInterface $campaignRepository,
        private GameSystemRepositoryInterface $gameSystemRepository,
        private ContentWarningRepositoryInterface $contentWarningRepository,
        private AuthorizationServiceInterface $authorizationService,
    ) {}

    public function createGameTable(FrontendCreateGameTableDTO $dto): GameTableResponseDTO
    {
        // 1. Check eligibility
        $eligibility = $this->eligibilityService->canCreateTable($dto->createdBy);
        if (! $eligibility->eligible) {
            throw NotEligibleToCreateException::withReason($eligibility->reason ?? 'unknown');
        }

        // 2. Convert to CreateGameTableDTO
        $createDto = $dto->toCreateGameTableDTO();

        // 3. Create the table (GameTableService handles the creation)
        $gameTable = $this->gameTableService->create($createDto);

        // 4. Determine initial status based on publication mode
        $creationStatus = $this->determineInitialStatus($dto->createdBy);

        // 5. Retrieve the entity to update frontend creation status
        $tableEntity = $this->gameTableRepository->find(new GameTableId($gameTable->id));
        if ($tableEntity !== null) {
            $tableEntity->setFrontendCreationStatus($creationStatus);
            $this->gameTableRepository->save($tableEntity);
        }

        // 6. Auto-publish for approved status
        if ($creationStatus === FrontendCreationStatus::Approved) {
            $gameTable = $this->gameTableService->publish($gameTable->id);
        }

        return $gameTable;
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
            slug: \Illuminate\Support\Str::slug($dto->title),
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
     *     content_warnings: array<array{id: string, name: string, description: string|null}>,
     *     genres: array<string, string>
     * }
     */
    public function getCreateFormData(): array
    {
        // Get game systems
        $gameSystems = $this->gameSystemRepository->getActive();

        // Get content warnings
        $contentWarnings = $this->contentWarningRepository->getActive();

        return [
            'game_systems' => array_map(
                fn ($gs): array => ['id' => $gs->id->value, 'name' => $gs->name],
                $gameSystems,
            ),
            'table_types' => TableType::options(),
            'table_formats' => TableFormat::options(),
            'experience_levels' => ExperienceLevel::options(),
            'tones' => Tone::options(),
            'character_creation' => CharacterCreation::options(),
            'safety_tools' => SafetyTool::options(),
            'content_warnings' => array_map(
                fn ($cw): array => [
                    'id' => $cw->id->value,
                    'name' => $cw->name,
                    'description' => $cw->description,
                ],
                $contentWarnings,
            ),
            'genres' => Genre::options(),
        ];
    }

    /**
     * @return array<GameTableResponseDTO>
     */
    public function getUserDrafts(string $userId): array
    {
        // Get all tables created by user in draft status
        $tables = $this->gameTableRepository->getByCreator($userId);

        // Filter to only include drafts (unpublished tables)
        $draftTables = array_filter(
            $tables,
            fn ($table): bool => ! $table->isPublished,
        );

        return array_map(
            fn ($table): GameTableResponseDTO => GameTableResponseDTO::fromEntity($table),
            array_values($draftTables),
        );
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

        // For now, submitting for review means publishing the table
        // if the publication mode is 'auto' or user has auto-publish privileges
        $mode = $this->settingsReader->getPublicationMode();

        if ($mode === 'auto' || $this->checkAutoPublishEligibility($userId)) {
            $this->gameTableService->publish($tableId);
        }
        // In 'approval' mode, the table stays as draft until admin approval
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

        // Only allow editing unpublished tables
        if ($table->isPublished) {
            throw NotEditableException::forStatus('published');
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
            notes: $dto->notes,
        );

        return $this->gameTableService->update($updateDto);
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

        // Only allow deleting unpublished tables
        if ($table->isPublished) {
            throw NotEditableException::forStatus('published');
        }

        $this->gameTableService->delete($tableId);
    }

    public function approveFrontendCreation(string $tableId, ?string $notes = null): void
    {
        $table = $this->gameTableRepository->find(new GameTableId($tableId));

        if ($table === null) {
            throw GameTableNotFoundException::withId($tableId);
        }

        $table->approveFrontendCreation($notes);
        $this->gameTableRepository->save($table);

        // Publish the table when approved
        $this->gameTableService->publish($tableId);
    }

    public function rejectFrontendCreation(string $tableId, string $reason): void
    {
        $table = $this->gameTableRepository->find(new GameTableId($tableId));

        if ($table === null) {
            throw GameTableNotFoundException::withId($tableId);
        }

        $table->rejectFrontendCreation($reason);
        $this->gameTableRepository->save($table);
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

        // Only allow editing drafts
        if (! $campaign->canEditAsDraft()) {
            throw NotEditableException::forStatus('not_draft');
        }

        // Update the campaign
        $campaign->title = $dto->title;
        $campaign->slug = \Illuminate\Support\Str::slug($dto->title);
        $campaign->description = $dto->synopsis;
        $campaign->frequency = $dto->frequency;
        $campaign->maxPlayers = $dto->maxPlayers;

        // If rejected, reset to draft on edit
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

        // Submit for moderation
        $campaign->submitForModeration();

        // Check if auto-publish eligible
        $mode = $this->settingsReader->getPublicationMode();

        if ($mode === 'auto' || $this->checkAutoPublishEligibility($userId)) {
            $campaign->approveFrontendCreation();
            $campaign->publish();
        }

        $this->campaignRepository->save($campaign);
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

        // Only allow deleting drafts
        if ($campaign->isPublished) {
            throw NotEditableException::forStatus('published');
        }

        $this->campaignRepository->delete($campaign->id);
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
