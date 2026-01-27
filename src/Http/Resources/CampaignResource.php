<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        return [
            'id' => $this->id,
            'title' => $this->title,
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
            'totalSessions' => $this->totalSessions,
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
