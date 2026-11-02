<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Resources;

use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GameTables\Application\DTOs\CampaignGameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\Language;
use Modules\GameTables\Domain\Enums\SafetyTool;

/**
 * The link of the online session goes out only when the caller says the viewer may have it (its
 * directors and roster, on the campaign page); lists and other viewers never get it.
 *
 * @mixin CampaignResponseDTO
 */
final class CampaignResource extends JsonResource
{
    public function __construct(CampaignResponseDTO $resource, private readonly bool $revealsOnlineUrl = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(TimezoneServiceInterface::class);

        $mainGm = array_filter($this->gameMasters, fn (CampaignGameMasterResponseDTO $gm) => $gm->isMain);
        $mainGameMaster = reset($mainGm) ?: null;
        $mainGameMasterName = $mainGameMaster->displayName ?? $this->creatorName;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'gameSystemId' => $this->gameSystemId,
            'gameSystemName' => $this->gameSystemName,
            'createdBy' => $this->createdBy,
            'creatorName' => $this->creatorName,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusColor' => $this->status->color(),
            'frequency' => $this->frequency?->value,
            'frequencyLabel' => $this->frequency?->label(),
            'maxPlayers' => $this->maxPlayers,
            'currentPlayers' => $this->currentPlayers,
            'spotsAvailable' => $this->maxPlayers !== null ? max(0, $this->maxPlayers - $this->currentPlayers) : null,
            'isPublished' => $this->isPublished,
            'isRecruiting' => $this->isRecruiting,
            'acceptsNewPlayers' => $this->acceptsNewPlayers,
            'sessionCount' => $this->sessionCount,
            'currentSession' => $this->currentSession,
            'totalSessions' => $this->totalSessions,
            'imagePublicId' => $this->imagePublicId,
            'gameMasters' => CampaignGameMasterResource::collection($this->gameMasters)->resolve(),
            'gameTables' => GameTableListResource::collection($this->gameTables)->resolve(),
            'hasActiveOrUpcomingTables' => $this->hasActiveOrUpcomingTables,
            'mainGameMasterName' => $mainGameMasterName,
            // Fields required by frontend but not yet implemented in backend model
            'expectedDurationMonths' => null,
            'startDate' => null,
            'endDate' => null,
            'recruitmentMessage' => null,
            'settings' => null,
            'themes' => [],
            'safetyTools' => array_map(static fn (SafetyTool $tool): string => $tool->label(), $this->safetyTools),
            'contentWarnings' => $this->contentWarnings,
            'customWarnings' => $this->customWarnings,
            'minimumAge' => null,
            'minPlayers' => $this->minPlayers,
            'tableFormat' => $this->tableFormat?->value,
            'tableFormatLabel' => $this->tableFormat?->label(),
            'tableFormatColor' => $this->tableFormat?->color(),
            'location' => $this->location,
            'onlineUrl' => $this->revealsOnlineUrl ? $this->onlineUrl : null,
            'scheduleNotes' => $this->scheduleNotes,
            'genres' => array_map(static fn (Genre $genre): string => $genre->label(), $this->genres),
            'tone' => $this->tone?->value,
            'toneLabel' => $this->tone?->label(),
            'characterCreation' => $this->characterCreation?->value,
            'characterCreationLabel' => $this->characterCreation?->label(),
            'language' => $this->language,
            'languageLabel' => Language::nativeNameOf($this->language),
            'experienceLevel' => $this->experienceLevel?->value,
            'experienceLevelLabel' => $this->experienceLevel?->label(),
            'createdAt' => $timezone->iso($this->createdAt),
            'updatedAt' => $timezone->iso($this->updatedAt),
        ];
    }
}
