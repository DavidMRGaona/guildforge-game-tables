<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GameTables\Application\DTOs\CampaignGameMasterResponseDTO;

/**
 * @mixin CampaignGameMasterResponseDTO
 */
final class CampaignGameMasterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaignId' => $this->campaignId,
            'userId' => $this->userId,
            'displayName' => $this->displayName,
            'role' => $this->role->value,
            'roleLabel' => $this->role->label(),
            'customTitle' => $this->customTitle,
            'isMain' => $this->isMain,
            'isNamePublic' => $this->isNamePublic,
        ];
    }
}
