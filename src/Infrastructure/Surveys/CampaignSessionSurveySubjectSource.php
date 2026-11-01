<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Surveys;

use App\Application\Surveys\Contracts\SurveySubjectAttributeValuesInterface;
use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

/**
 * Campaign sessions as survey subjects of their own, apart from tables on their own, so they never
 * count as such in the statistics. Like a table: its confirmed players are asked to answer
 * (TableSurveySubject) and its directors (the campaign's it inherits and its own) manage its surveys.
 */
final readonly class CampaignSessionSurveySubjectSource implements SurveySubjectAttributeValuesInterface, SurveySubjectSourceInterface
{
    /** Stored by surveys and their statistics: never changes. */
    public const string TYPE = 'campaign-session';

    public function __construct(
        private GameTableRepositoryInterface $tables,
        private CampaignRepositoryInterface $campaigns,
        private GameSystemSurveyAttribute $gameSystem,
        private TableSurveySubject $subjects,
        private TableDirectionInterface $direction,
    ) {}

    public function sourceType(): string
    {
        return self::TYPE;
    }

    public function sourceLabel(): string
    {
        return __('game-tables::messages.surveys.campaign_session_label');
    }

    public function find(string $subjectId): ?SurveySubjectDTO
    {
        $session = $this->session($subjectId);
        if (! $session instanceof GameTable || ! $session->campaignId instanceof CampaignId) {
            return null;
        }

        $campaign = $this->campaigns->find($session->campaignId);

        return new SurveySubjectDTO(
            sourceType: self::TYPE,
            subjectId: $session->id->value,
            title: $session->title,
            startsAt: $session->timeSlot->startsAt,
            endsAt: $session->timeSlot->endsAt(),
            organizerIds: $this->direction->directorUserIds($session->id->value),
            attributes: [
                $this->gameSystem->of($session->gameSystemId),
                new SurveySubjectAttributeDTO('campaign', __('game-tables::messages.surveys.attributes.campaign'), $campaign instanceof Campaign ? $campaign->title : null),
                new SurveySubjectAttributeDTO('table_format', __('game-tables::messages.surveys.attributes.table_format'), $session->tableFormat->label()),
                new SurveySubjectAttributeDTO('table_type', __('game-tables::messages.surveys.attributes.table_type'), $session->tableType->label()),
            ],
            url: route('gametables.show', $session->slug),
            status: TableSurveySubject::status($session->status),
        );
    }

    public function audience(string $subjectId): SurveyAudienceDTO
    {
        $session = $this->session($subjectId);

        return $session instanceof GameTable ? $this->subjects->audience($session) : SurveyAudienceDTO::empty();
    }

    public function canManage(string $userId, string $subjectId): bool
    {
        $session = $this->session($subjectId);

        return $session instanceof GameTable && $this->direction->isDirector($session->id->value, $userId);
    }

    public function attributeValues(string $key): array
    {
        return $this->gameSystem->values($key);
    }

    /**
     * The session this source offers: an existing table that belongs to a campaign.
     */
    private function session(string $subjectId): ?GameTable
    {
        if (! Str::isUuid($subjectId)) {
            return null;
        }

        $table = $this->tables->find(new GameTableId($subjectId));

        return $table instanceof GameTable && $table->campaignId instanceof CampaignId ? $table : null;
    }
}
