<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Mail;

use App\Application\Mail\DTOs\MailCategoryDTO;

/**
 * The module's mail categories. The keys are stored with each person's choice: never rename them.
 */
final class GameTablesMailCategories
{
    /** Confirms what the person did with their seats, or changes their plans (mandatory) */
    public const string SIGN_UPS = 'game-tables.sign-ups';

    public const string REMINDERS = 'game-tables.reminders';

    /** Invitations and new sessions of the campaigns the person plays */
    public const string CAMPAIGN_NEWS = 'game-tables.campaign-news';

    /** Notices about other people in the tables and campaigns the person directs */
    public const string DIRECTION = 'game-tables.direction';

    /** The answer to a table or campaign the person proposed on the web (mandatory) */
    public const string PROPOSALS = 'game-tables.proposals';

    /** Proposals waiting for review, for moderators */
    public const string MODERATION = 'game-tables.moderation';

    private const string LANG = 'game-tables::emails.categories.';

    /**
     * @return list<MailCategoryDTO>
     */
    public static function definitions(): array
    {
        $section = self::LANG.'section';

        return [
            MailCategoryDTO::mandatory(
                key: self::SIGN_UPS,
                section: $section,
                label: self::LANG.'sign_ups.label',
                description: self::LANG.'sign_ups.description',
                reason: self::LANG.'sign_ups.reason',
            ),
            MailCategoryDTO::optional(
                key: self::REMINDERS,
                section: $section,
                label: self::LANG.'reminders.label',
                description: self::LANG.'reminders.description',
            ),
            MailCategoryDTO::optional(
                key: self::CAMPAIGN_NEWS,
                section: $section,
                label: self::LANG.'campaign_news.label',
                description: self::LANG.'campaign_news.description',
            ),
            MailCategoryDTO::optional(
                key: self::DIRECTION,
                section: $section,
                label: self::LANG.'direction.label',
                description: self::LANG.'direction.description',
                audience: static fn (object $user): bool => app(MailCategoryAudiences::class)->directs($user),
            ),
            MailCategoryDTO::mandatory(
                key: self::PROPOSALS,
                section: $section,
                label: self::LANG.'proposals.label',
                description: self::LANG.'proposals.description',
                reason: self::LANG.'proposals.reason',
                audience: static fn (object $user): bool => app(MailCategoryAudiences::class)->proposes($user),
            ),
            MailCategoryDTO::optional(
                key: self::MODERATION,
                section: $section,
                label: self::LANG.'moderation.label',
                description: self::LANG.'moderation.description',
                audience: static fn (object $user): bool => app(MailCategoryAudiences::class)->moderates($user),
            ),
        ];
    }
}
