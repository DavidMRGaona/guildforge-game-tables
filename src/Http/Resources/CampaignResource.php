<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GameTables\Application\DTOs\CampaignGameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;

/**
 * @mixin CampaignResponseDTO
 */
final class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mainGm = array_filter($this->gameMasters, fn (CampaignGameMasterResponseDTO $gm) => $gm->isMain);
        $mainGameMaster = reset($mainGm) ?: null;
        $mainGameMasterName = $mainGameMaster?->displayName ?? $this->creatorName;

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
            'safetyTools' => [],
            'contentWarnings' => [],
            'minimumAge' => null,
            'language' => 'es',
            'experienceLevel' => 'none',
            'experienceLevelLabel' => __('game-tables::messages.enums.experience_level.none'),
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
