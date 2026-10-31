<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Surveys;

use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveyGuestDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;

/**
 * What a table on its own and a campaign session have in common as survey subjects: the players
 * who confirmed are asked to answer, with an account or as guests whose email is confirmed, and
 * the subject ends or is cancelled with the table.
 */
final readonly class TableSurveySubject
{
    public function __construct(
        private ParticipantRepositoryInterface $participants,
    ) {}

    public function audience(GameTable $table): SurveyAudienceDTO
    {
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
                array_filter($players, $this->isReachableGuest(...)),
            )),
        );
    }

    /**
     * The kind of subject a table is: a campaign session or a table on its own.
     */
    public static function sourceType(bool $inCampaign): string
    {
        return $inCampaign ? CampaignSessionSurveySubjectSource::TYPE : GameTableSurveySubjectSource::TYPE;
    }

    public static function status(TableStatus $status): SurveySubjectStatus
    {
        return match ($status) {
            TableStatus::Completed => SurveySubjectStatus::Ended,
            TableStatus::Cancelled => SurveySubjectStatus::Cancelled,
            default => SurveySubjectStatus::Active,
        };
    }

    /**
     * A guest the survey can reach: no account, and an email they confirmed that is a valid
     * address (an invalid one would make the whole audience fail).
     */
    private function isReachableGuest(Participant $player): bool
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
