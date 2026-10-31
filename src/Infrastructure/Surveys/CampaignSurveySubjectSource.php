<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Surveys;

use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;

/**
 * Whole campaigns as survey subjects, once they are completed: its roster is asked to answer
 * (accounts only, anonymized ones never listed) and its directors manage its surveys. Its dates
 * span its sessions that took or will take place.
 */
final readonly class CampaignSurveySubjectSource implements SurveySubjectSourceInterface
{
    /** Stored by surveys and their statistics: never changes. */
    public const string TYPE = 'campaign';

    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private GameTableRepositoryInterface $tables,
        private GameSystemRepositoryInterface $systems,
        private CampaignRosterRepositoryInterface $roster,
        private CampaignDirectionInterface $direction,
    ) {}

    public function sourceType(): string
    {
        return self::TYPE;
    }

    public function sourceLabel(): string
    {
        return __('game-tables::messages.surveys.campaign_label');
    }

    public function find(string $subjectId): ?SurveySubjectDTO
    {
        $campaign = $this->campaign($subjectId);
        if (! $campaign instanceof Campaign) {
            return null;
        }

        $system = $this->systems->find($campaign->gameSystemId);
        [$startsAt, $endsAt] = $this->span($campaign->id);

        return new SurveySubjectDTO(
            sourceType: self::TYPE,
            subjectId: $campaign->id->value,
            title: $campaign->title,
            startsAt: $startsAt,
            endsAt: $endsAt,
            organizerIds: $this->direction->directorUserIds($campaign->id->value),
            attributes: [
                new SurveySubjectAttributeDTO('game_system', __('game-tables::messages.surveys.attributes.game_system'), $system instanceof GameSystem ? $system->name : null),
                new SurveySubjectAttributeDTO('frequency', __('game-tables::messages.surveys.attributes.frequency'), $campaign->frequency?->label()),
            ],
            url: route('campaigns.show', $campaign->slug),
            status: match ($campaign->status) {
                CampaignStatus::Completed => SurveySubjectStatus::Ended,
                CampaignStatus::Cancelled => SurveySubjectStatus::Cancelled,
                default => SurveySubjectStatus::Active,
            },
        );
    }

    public function audience(string $subjectId): SurveyAudienceDTO
    {
        $campaign = $this->campaign($subjectId);

        // Directors outside the roster are left out: they ran the campaign, they did not play it
        return $campaign instanceof Campaign
            ? new SurveyAudienceDTO(userIds: $this->roster->memberIds($campaign->id), guests: [])
            : SurveyAudienceDTO::empty();
    }

    public function canManage(string $userId, string $subjectId): bool
    {
        $campaign = $this->campaign($subjectId);

        return $campaign instanceof Campaign && $this->direction->isDirector($campaign->id->value, $userId);
    }

    private function campaign(string $subjectId): ?Campaign
    {
        return Str::isUuid($subjectId) ? $this->campaigns->find(new CampaignId($subjectId)) : null;
    }

    /**
     * From the start of its first session to the end of its last one, drafts and cancelled
     * sessions left out; no dates without sessions.
     *
     * @return array{0: DateTimeImmutable|null, 1: DateTimeImmutable|null}
     */
    private function span(CampaignId $campaignId): array
    {
        $sessions = array_filter(
            $this->tables->getByCampaign($campaignId),
            static fn (GameTable $session): bool => ! in_array($session->status, [TableStatus::Draft, TableStatus::Cancelled], true),
        );

        $startsAt = null;
        $endsAt = null;
        foreach ($sessions as $session) {
            $start = $session->timeSlot->startsAt;
            $end = $session->timeSlot->endsAt();
            $startsAt = $startsAt === null || $start < $startsAt ? $start : $startsAt;
            $endsAt = $endsAt === null || $end > $endsAt ? $end : $endsAt;
        }

        return [$startsAt, $endsAt];
    }
}
