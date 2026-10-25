<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners\Concerns;

use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

trait DescribesRosterSessions
{
    /**
     * Turns the sessions of a roster event into titles and dates for the email.
     *
     * @param  list<array{game_table_id: string, waiting: bool}>  $sessions
     * @return list<array{title: string, date: string, waiting: bool}>
     */
    private function describeSessions(GameTableRepositoryInterface $tables, array $sessions): array
    {
        $described = [];

        foreach ($sessions as $session) {
            $table = $tables->find(new GameTableId($session['game_table_id']));

            if (! $table instanceof GameTable) {
                continue;
            }

            $described[] = [
                'title' => $table->title,
                'date' => $table->timeSlot->startsAt->format('d/m/Y H:i'),
                'waiting' => $session['waiting'],
            ];
        }

        return $described;
    }
}
