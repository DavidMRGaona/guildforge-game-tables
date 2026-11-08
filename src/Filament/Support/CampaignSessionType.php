<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Support;

use Modules\GameTables\Domain\Enums\TableType;

/**
 * In the panel, as on the site, a table with a campaign is a campaign session. The form locks the
 * type while there is a campaign; this keeps a forged payload from saving any other type.
 */
final class CampaignSessionType
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function guardFormData(array $data): array
    {
        if (filled($data['campaign_id'] ?? null)) {
            $data['table_type'] = TableType::CampaignSession->value;
        }

        return $data;
    }
}
