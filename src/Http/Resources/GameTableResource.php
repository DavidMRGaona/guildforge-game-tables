<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GameTables\Application\DTOs\GameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;

/**
 * @mixin GameTableResponseDTO
 */
final class GameTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentPlayers = count(array_filter(
            $this->participants,
            fn ($p) => $p->role->value === 'player' && in_array($p->status->value, ['confirmed', 'pending'])
        ));

        $currentSpectators = count(array_filter(
            $this->participants,
            fn ($p) => $p->role->value === 'spectator' && in_array($p->status->value, ['confirmed', 'pending'])
        ));

        $mainGm = array_filter($this->gameMasters, fn (GameMasterResponseDTO $gm) => $gm->isMain);
        $mainGameMaster = reset($mainGm) ?: null;
        $mainGameMasterName = $mainGameMaster?->displayName ?? $this->creatorName;

        return [
            'id' => $this->id,
            'title' => $this->title,
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
            'startsAt' => $this->startsAt?->format('c'),
            'durationMinutes' => $this->durationMinutes,
            'location' => $this->location,
            'onlineUrl' => $this->onlineUrl,
            'minPlayers' => $this->minPlayers,
            'maxPlayers' => $this->maxPlayers,
            'maxSpectators' => $this->maxSpectators,
            'minimumAge' => $this->minimumAge,
            'language' => $this->language,
            'languageLabel' => $this->getLanguageLabel($this->language),
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
            'registrationOpensAt' => $this->registrationOpensAt?->format('c'),
            'registrationClosesAt' => $this->registrationClosesAt?->format('c'),
            'autoConfirm' => $this->autoConfirm,
            'isPublished' => $this->isPublished,
            'publishedAt' => $this->publishedAt?->format('c'),
            'notes' => $this->notes,
            'gameMasters' => GameMasterResource::collection($this->gameMasters)->resolve(),
            'mainGameMasterName' => $mainGameMasterName,
            'currentPlayers' => $currentPlayers,
            'currentSpectators' => $currentSpectators,
            'spotsAvailable' => max(0, $this->maxPlayers - $currentPlayers),
            'spectatorSpotsAvailable' => max(0, $this->maxSpectators - $currentSpectators),
            'waitingListCount' => count(array_filter(
                $this->participants,
                fn ($p) => $p->waitingListPosition !== null
            )),
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }

    private function getLanguageLabel(?string $language): ?string
    {
        if ($language === null) {
            return null;
        }

        $languages = [
            'es' => 'Español',
            'en' => 'English',
            'ca' => 'Català',
            'eu' => 'Euskara',
            'gl' => 'Galego',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
        ];

        return $languages[$language] ?? $language;
    }
}
