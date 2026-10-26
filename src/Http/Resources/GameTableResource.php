<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Resources;

use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GameTables\Application\DTOs\GameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\TableSeatsDTO;
use Modules\GameTables\Domain\Enums\Language;

/**
 * The seats come counted by the server (SeatCounter), with the rule it applies on registration.
 *
 * @mixin GameTableResponseDTO
 */
final class GameTableResource extends JsonResource
{
    public function __construct(GameTableResponseDTO $resource, private readonly TableSeatsDTO $seats)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(TimezoneServiceInterface::class);

        $mainGm = array_filter($this->gameMasters, fn (GameMasterResponseDTO $gm) => $gm->isMain);
        $mainGameMaster = reset($mainGm) ?: null;
        $mainGameMasterName = $mainGameMaster->displayName ?? $this->creatorName;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'synopsis' => $this->synopsis,
            'gameSystemId' => $this->gameSystemId,
            'gameSystemName' => $this->gameSystemName,
            'campaignId' => $this->campaignId,
            'campaignTitle' => $this->campaignTitle,
            'eventId' => $this->eventId,
            'eventTitle' => $this->eventTitle,
            'createdBy' => $this->createdBy,
            'creatorName' => $this->creatorName,
            'tableType' => $this->tableType->value,
            'tableTypeLabel' => $this->tableType->label(),
            'tableFormat' => $this->tableFormat->value,
            'tableFormatLabel' => $this->tableFormat->label(),
            'tableFormatColor' => $this->tableFormat->color(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusColor' => $this->status->color(),
            'startsAt' => $timezone->iso($this->startsAt),
            'durationMinutes' => $this->durationMinutes,
            'location' => $this->location,
            'onlineUrl' => $this->onlineUrl,
            'minPlayers' => $this->minPlayers,
            'maxPlayers' => $this->maxPlayers,
            'maxSpectators' => $this->maxSpectators,
            'minimumAge' => $this->minimumAge,
            'language' => $this->language,
            'languageLabel' => Language::nativeNameOf($this->language),
            'experienceLevel' => $this->experienceLevel?->value,
            'experienceLevelLabel' => $this->experienceLevel?->label(),
            'characterCreation' => $this->characterCreation?->value,
            'characterCreationLabel' => $this->characterCreation?->label(),
            'genres' => array_map(fn ($genre) => $genre->label(), $this->genres),
            'tone' => $this->tone?->value,
            'toneLabel' => $this->tone?->label(),
            'safetyTools' => array_map(fn ($tool) => $tool->label(), $this->safetyTools),
            'contentWarnings' => $this->contentWarnings,
            'customWarnings' => $this->customWarnings,
            'registrationType' => $this->registrationType->value,
            'registrationTypeLabel' => $this->registrationType->label(),
            'membersEarlyAccessDays' => $this->membersEarlyAccessDays,
            'registrationOpensAt' => $timezone->iso($this->registrationOpensAt),
            'registrationClosesAt' => $timezone->iso($this->registrationClosesAt),
            'effectiveRegistrationClosesAt' => $timezone->iso($this->effectiveRegistrationClosesAt),
            'autoConfirm' => $this->autoConfirm,
            'acceptsRegistrationsInProgress' => $this->acceptsRegistrationsInProgress,
            'isPublished' => $this->isPublished,
            'publishedAt' => $timezone->iso($this->publishedAt),
            'notes' => $this->notes,
            'imagePublicId' => $this->imagePublicId,
            'gameMasters' => GameMasterResource::collection($this->gameMasters)->resolve(),
            'mainGameMasterName' => $mainGameMasterName,
            'currentPlayers' => $this->seats->takenPlayerSeats,
            'currentSpectators' => $this->seats->takenSpectatorSeats,
            'spotsAvailable' => $this->seats->freePlayerSeats,
            'spectatorSpotsAvailable' => $this->seats->freeSpectatorSeats,
            'waitingListCount' => $this->seats->waitingPlayers,
            'createdAt' => $timezone->iso($this->createdAt),
            'updatedAt' => $timezone->iso($this->updatedAt),
        ];
    }
}
