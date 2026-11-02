<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;

/**
 * The campaigns a person may create sessions of from the web: the approved or published ones they
 * direct that have not finished. Directing one is enough to create its sessions, whatever the
 * general creation rules say, as long as creation from the web is turned on.
 */
final readonly class CampaignSessionCreationGate
{
    public function __construct(
        private GameTableSettingsReader $settings,
        private CampaignDirectionInterface $direction,
        private CampaignRepositoryInterface $campaigns,
        private CampaignRosterRepositoryInterface $roster,
    ) {}

    /**
     * @return list<array{id: string, name: string, game_system_id: string, max_players: int|null, roster_count: int, table_format: string|null, location: string|null, online_url: string|null}>
     */
    public function sessionCampaigns(string $userId): array
    {
        $campaigns = [];

        foreach ($this->direction->campaignIdsDirectedBy($userId) as $campaignId) {
            $campaign = $this->campaigns->find(new CampaignId($campaignId));

            if ($campaign instanceof Campaign && $campaign->isAvailableForSessions()) {
                $campaigns[] = $campaign;
            }
        }

        if ($campaigns === []) {
            return [];
        }

        usort($campaigns, static fn (Campaign $a, Campaign $b): int => strnatcasecmp($a->title, $b->title));

        $counts = $this->roster->countsFor(array_map(static fn (Campaign $campaign): CampaignId => $campaign->id, $campaigns));

        return array_map(
            static fn (Campaign $campaign): array => self::option($campaign, $counts[$campaign->id->value] ?? 0, proposesPlace: true),
            $campaigns,
        );
    }

    public function allows(string $userId, string $campaignId): bool
    {
        if (! $this->settings->isFrontendCreationEnabled()) {
            return false;
        }

        return in_array($campaignId, $this->sessionCampaignIds($userId), true);
    }

    public function canCreateSessionsOnly(string $userId): bool
    {
        return $this->settings->isFrontendCreationEnabled() && $this->sessionCampaigns($userId) !== [];
    }

    /**
     * @return list<string>
     */
    public function sessionCampaignIds(string $userId): array
    {
        return array_map(static fn (array $option): string => $option['id'], $this->sessionCampaigns($userId));
    }

    /**
     * The same shape as sessionCampaigns() for any campaign, such as the one stored on a table being edited.
     * The person may not direct it: its place (format, location, online link) is not given.
     *
     * @return array{id: string, name: string, game_system_id: string, max_players: int|null, roster_count: int, table_format: string|null, location: string|null, online_url: string|null}|null
     */
    public function describe(string $campaignId): ?array
    {
        $campaign = $this->campaigns->find(new CampaignId($campaignId));

        return $campaign instanceof Campaign ? self::option($campaign, $this->roster->count($campaign->id), proposesPlace: false) : null;
    }

    /**
     * @return array{id: string, name: string, game_system_id: string, max_players: int|null, roster_count: int, table_format: string|null, location: string|null, online_url: string|null}
     */
    private static function option(Campaign $campaign, int $rosterCount, bool $proposesPlace): array
    {
        return [
            'id' => $campaign->id->value,
            'name' => $campaign->title,
            'game_system_id' => $campaign->gameSystemId->value,
            'max_players' => $campaign->maxPlayers,
            'roster_count' => $rosterCount,
            // What the session form proposes when the campaign is chosen; it may be changed
            'table_format' => $proposesPlace ? $campaign->tableFormat?->value : null,
            'location' => $proposesPlace ? $campaign->location : null,
            'online_url' => $proposesPlace ? $campaign->onlineUrl : null,
        ];
    }
}
