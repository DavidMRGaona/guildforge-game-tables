<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Mail;

use App\Application\Mail\DTOs\MailSubjectDTO;
use InvalidArgumentException;

/**
 * What the module's notices are about: a table or a campaign. Muting one from a mail holds back
 * its optional notices, and deleting it forgets them (mail mutes and the bell's notices). The
 * types are stored with each person's mutes: never rename them.
 */
final class GameTablesMailSubjects
{
    public const string TABLE = 'game-tables.table';

    public const string CAMPAIGN = 'game-tables.campaign';

    public static function table(?string $id, string $title): ?MailSubjectDTO
    {
        return self::make(self::TABLE, $id, $title, 'game-tables::notices.subjects.table');
    }

    public static function campaign(?string $id, string $title): ?MailSubjectDTO
    {
        return self::make(self::CAMPAIGN, $id, $title, 'game-tables::notices.subjects.campaign');
    }

    /**
     * Null without an id (a notification queued before it carried one) or with a value the core
     * cannot store: the mail then goes out as before, about nothing in particular.
     */
    private static function make(string $type, ?string $id, string $title, string $typeLabel): ?MailSubjectDTO
    {
        if ($id === null || $id === '') {
            return null;
        }

        try {
            return new MailSubjectDTO($type, $id, $title, $typeLabel);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
