<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Surveys;

use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

/**
 * Tables on their own as survey subjects, through the core's contract: the surveys module, when
 * installed, asks it and nothing here depends on it. Campaign sessions are left out (they are
 * their own kind of subject). The players who confirmed are asked to answer (TableSurveySubject);
 * the table's directors manage its surveys.
 */
final readonly class GameTableSurveySubjectSource implements SurveySubjectSourceInterface
{
    /** Stored by surveys and their statistics: never changes. */
    public const string TYPE = 'game-table';

    public function __construct(
        private GameTableRepositoryInterface $tables,
        private GameSystemRepositoryInterface $systems,
        private TableSurveySubject $subjects,
        private TableDirectionInterface $direction,
    ) {}

    public function sourceType(): string
    {
        return self::TYPE;
    }

    public function sourceLabel(): string
    {
        return __('game-tables::messages.surveys.source_label');
    }

    public function find(string $subjectId): ?SurveySubjectDTO
    {
        $table = $this->table($subjectId);
        if (! $table instanceof GameTable) {
            return null;
        }

        $system = $this->systems->find($table->gameSystemId);

        return new SurveySubjectDTO(
            sourceType: self::TYPE,
            subjectId: $table->id->value,
            title: $table->title,
            startsAt: $table->timeSlot->startsAt,
            endsAt: $table->timeSlot->endsAt(),
            organizerIds: $this->direction->directorUserIds($table->id->value),
            attributes: [
                new SurveySubjectAttributeDTO('game_system', __('game-tables::messages.surveys.attributes.game_system'), $system instanceof GameSystem ? $system->name : null),
                new SurveySubjectAttributeDTO('table_format', __('game-tables::messages.surveys.attributes.table_format'), $table->tableFormat->label()),
                new SurveySubjectAttributeDTO('table_type', __('game-tables::messages.surveys.attributes.table_type'), $table->tableType->label()),
            ],
            url: route('gametables.show', $table->slug),
            status: TableSurveySubject::status($table->status),
        );
    }

    public function audience(string $subjectId): SurveyAudienceDTO
    {
        $table = $this->table($subjectId);

        return $table instanceof GameTable ? $this->subjects->audience($table) : SurveyAudienceDTO::empty();
    }

    public function canManage(string $userId, string $subjectId): bool
    {
        $table = $this->table($subjectId);

        return $table instanceof GameTable && $this->direction->isDirector($table->id->value, $userId);
    }

    /**
     * The table this source offers: an existing table that is not a campaign session.
     */
    private function table(string $subjectId): ?GameTable
    {
        if (! Str::isUuid($subjectId)) {
            return null;
        }

        $table = $this->tables->find(new GameTableId($subjectId));

        return $table instanceof GameTable && ! $table->campaignId instanceof CampaignId ? $table : null;
    }
}
