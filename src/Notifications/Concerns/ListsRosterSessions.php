<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications\Concerns;

trait ListsRosterSessions
{
    /**
     * One line per session: «Title» — date, plus the waiting-list note when the member is waiting.
     * An empty list yields the "no sessions yet" line.
     *
     * @param  list<array{title: string, date: string, waiting: bool}>  $sessions
     * @return list<string>
     */
    protected function sessionLines(array $sessions): array
    {
        if ($sessions === []) {
            return [__('game-tables::emails.campaign_sessions.no_sessions')];
        }

        return array_map(
            static fn (array $session): string => '«'.$session['title'].'» — '.$session['date']
                .($session['waiting'] ? __('game-tables::emails.campaign_sessions.waiting_suffix') : ''),
            $sessions,
        );
    }
}
