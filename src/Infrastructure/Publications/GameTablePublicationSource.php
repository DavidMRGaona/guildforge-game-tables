<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Publications;

use App\Application\Publications\Contracts\PublicationSourceInterface;
use App\Application\Publications\DTOs\PublicationDTO;
use App\Application\Publications\Enums\PublicationState;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Search\Support\PlainText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\SeatCounter;

/**
 * Tables on their own as publications (core contract), for modules that announce them elsewhere.
 * Campaign sessions are left out: their campaign is the publication. Full and open read the same
 * rules as the table page: the seats of SeatCounter and the public registration window.
 */
final readonly class GameTablePublicationSource implements PublicationSourceInterface
{
    /** Stored by whoever announces the tables: never changes. */
    public const string TYPE = 'game-table';

    public function __construct(
        private GameTableRepositoryInterface $tables,
        private SeatCounter $seats,
        private GameSystemPublicationTopic $gameSystem,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function find(string $id): ?PublicationDTO
    {
        $table = $this->table($id);

        if (! $table instanceof GameTable) {
            return null;
        }

        $now = CarbonImmutable::now()->toDateTimeImmutable();

        return new PublicationDTO(
            type: self::TYPE,
            id: $table->id->value,
            title: $table->title,
            summary: PlainText::from((string) $table->synopsis),
            url: route('gametables.show', $table->slug),
            state: match ($table->status) {
                TableStatus::Completed => PublicationState::Ended,
                TableStatus::Cancelled => PublicationState::Cancelled,
                default => PublicationState::Active,
            },
            startsAt: $table->timeSlot->startsAt,
            endsAt: $table->timeSlot->endsAt(),
            topics: $this->gameSystem->of($table->gameSystemId),
            isFull: ! $table->admitsNewPlayersToSeats() || ! $table->hasCapacity($this->seats->takenPlayerSeats($table)),
            registrationOpen: $table->canRegister() && ! $table->requiresInvitation() && $table->isPublicRegistrationOpen($now),
            registrationClosesAt: $table->effectiveRegistrationClosesAt(),
        );
    }

    /**
     * Published tables on their own that are not over: scheduled, full or in play, and cancelled
     * ones until their start.
     */
    public function publishedIds(): iterable
    {
        // Bindings bypass the model's storage-zone cast, so the bound is converted by hand
        $now = $this->timezone->toStorage(now())->format('Y-m-d H:i:s');

        $tables = GameTableModel::query()
            ->select('id')
            ->where('is_published', true)
            ->whereNull('campaign_id')
            ->where(/** @param  Builder<GameTableModel>  $query */ static function (Builder $query) use ($now): void {
                $query->whereIn('status', [TableStatus::Scheduled->value, TableStatus::Full->value, TableStatus::InProgress->value])
                    ->orWhere(/** @param  Builder<GameTableModel>  $cancelled */ static function (Builder $cancelled) use ($now): void {
                        $cancelled->where('status', TableStatus::Cancelled->value)->where('starts_at', '>=', $now);
                    });
            })
            ->lazyById(200);

        foreach ($tables as $table) {
            yield (string) $table->getKey();
        }
    }

    /**
     * A published table on its own (not a campaign session, not a draft).
     */
    private function table(string $id): ?GameTable
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $table = $this->tables->find(new GameTableId($id));

        return $table instanceof GameTable
            && ! $table->campaignId instanceof CampaignId
            && $table->isPublished
            && $table->status !== TableStatus::Draft
            ? $table
            : null;
    }
}
