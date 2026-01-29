<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GameTables\Application\DTOs\GameTableListDTO;

/**
 * @mixin GameTableListDTO
 */
final class GameTableListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'gameSystemName' => $this->gameSystemName,
            'startsAt' => $this->startsAt?->format('c'),
            'durationMinutes' => $this->durationMinutes,
            'tableFormat' => [
                'value' => $this->tableFormat->value,
                'label' => $this->tableFormat->label(),
                'color' => $this->tableFormat->color(),
            ],
            'tableType' => [
                'value' => $this->tableType->value,
                'label' => $this->tableType->label(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'location' => $this->location,
            'onlineUrl' => $this->onlineUrl,
            'minPlayers' => $this->minPlayers,
            'maxPlayers' => $this->maxPlayers,
            'currentPlayers' => $this->currentPlayers,
            'spotsAvailable' => max(0, $this->maxPlayers - $this->currentPlayers),
            'isFull' => $this->currentPlayers >= $this->maxPlayers,
            'isPublished' => $this->isPublished,
            'creatorName' => $this->creatorName,
            'mainGameMasterName' => $this->mainGameMasterName,
            'eventId' => $this->eventId,
            'eventTitle' => $this->eventTitle,
            'imagePublicId' => $this->imagePublicId,
        ];
    }
}
