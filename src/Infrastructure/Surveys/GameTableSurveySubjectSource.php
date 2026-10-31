<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Surveys;

use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveyGuestDTO;
use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

/**
 * Tables on their own as survey subjects, through the core's contract: the surveys module, when
 * installed, asks it and nothing here depends on it. Campaign sessions are left out (they are
 * their own kind of subject). The players who confirmed are asked to answer, with an account or
 * as guests whose email is confirmed; the table's directors manage its surveys.
 */
final readonly class GameTableSurveySubjectSource implements SurveySubjectSourceInterface
{
    /** Stored by surveys and their statistics: never changes. */
    public const string TYPE = 'game-table';

    public function __construct(
        private GameTableRepositoryInterface $tables,
        private GameSystemRepositoryInterface $systems,
        private ParticipantRepositoryInterface $participants,
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
            status: match ($table->status) {
                TableStatus::Completed => SurveySubjectStatus::Ended,
                TableStatus::Cancelled => SurveySubjectStatus::Cancelled,
                default => SurveySubjectStatus::Active,
            },
        );
    }

    public function audience(string $subjectId): SurveyAudienceDTO
    {
        $table = $this->table($subjectId);
        if (! $table instanceof GameTable) {
            return SurveyAudienceDTO::empty();
        }

        $players = $this->participants->getConfirmedPlayers($table->id);

        $accounts = array_values(array_filter(array_map(static fn (Participant $player): ?string => $player->userId, $players)));
        $active = $accounts === [] ? [] : UserModel::query()
            ->whereIn('id', $accounts)
            ->whereNull('anonymized_at')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        return new SurveyAudienceDTO(
            userIds: array_values(array_filter($accounts, static fn (string $id): bool => in_array($id, $active, true))),
            guests: array_values(array_map(
                static fn (Participant $guest): SurveyGuestDTO => new SurveyGuestDTO(
                    reference: $guest->id->value,
                    displayName: self::guestName($guest),
                    email: (string) $guest->email,
                ),
                array_filter($players, static fn (Participant $player): bool => self::isReachableGuest($player)),
            )),
        );
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

        return $table instanceof GameTable && $table->campaignId === null ? $table : null;
    }

    /**
     * A guest the survey can reach: no account, and an email they confirmed that is a valid
     * address (an invalid one would make the whole audience fail).
     */
    private static function isReachableGuest(Participant $player): bool
    {
        return $player->userId === null
            && $player->emailConfirmedAt instanceof \DateTimeImmutable
            && filter_var((string) $player->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function guestName(Participant $guest): string
    {
        $name = trim(($guest->firstName ?? '').' '.($guest->lastName ?? ''));

        return $name !== '' ? $name : (string) $guest->email;
    }
}
