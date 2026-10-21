<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Lang;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The copy added for the campaign rosters exists in Spanish and English. The key lists are written
 * out on purpose: they are not derived from the files they check.
 */
final class CampaignRosterCopyTest extends TestCase
{
    private const MESSAGE_KEYS = [
        'fields.roster_access',
        'fields.roster_access_help',
        'enums.roster_access.anyone',
        'enums.roster_access.members_only',
        'enums.join_request_status.pending',
        'enums.join_request_status.accepted',
        'enums.join_request_status.rejected',
        'enums.join_request_status.withdrawn',
        'settings.sections.campaign_notifications',
        'settings.sections.campaign_notifications_description',
        'settings.fields.campaign_notify_session_scheduled',
        'settings.fields.campaign_notify_join_request_received',
        'settings.fields.campaign_notify_join_request_accepted',
        'settings.fields.campaign_notify_join_request_rejected',
        'settings.fields.campaign_notify_roster_added',
        'settings.fields.campaign_notify_roster_removed',
        'settings.fields.campaign_notify_player_left',
        'errors.campaign_roster_only',
        'errors.session_below_roster',
        'validation.campaign_required',
        'validation.max_players_below_roster',
        'validation.join_message_max',
        'validation.rejection_reason_max',
        'relation_managers.roster.title',
        'relation_managers.roster.name',
        'relation_managers.roster.email',
        'relation_managers.roster.since',
        'relation_managers.roster.add',
        'relation_managers.roster.account_email',
        'relation_managers.roster.remove',
        'relation_managers.roster.remove_description',
        'relation_managers.join_requests.title',
        'relation_managers.join_requests.player',
        'relation_managers.join_requests.message',
        'relation_managers.join_requests.status',
        'relation_managers.join_requests.requested_at',
        'relation_managers.join_requests.accept',
        'relation_managers.join_requests.reject',
        'relation_managers.join_requests.reason',
        'roster.errors.unavailable',
        'roster.errors.finished',
        'roster.errors.not_accepting',
        'roster.errors.roster_full',
        'roster.errors.members_only',
        'roster.errors.already_member',
        'roster.errors.account_already_member',
        'roster.errors.request_pending',
        'roster.errors.is_director',
        'roster.errors.account_not_found',
        'roster.errors.not_pending',
        'roster.errors.not_member',
        'roster.errors.max_below_roster',
        'roster.errors.session_not_in_campaign',
        'roster.errors.session_started',
        'roster.errors.nothing_to_change',
        'roster.errors.not_member_self',
        'roster.errors.too_many_requests',
        'roster.errors.too_many_additions',
        'roster.success.requested',
        'roster.success.withdrawn',
        'roster.success.accepted',
        'roster.success.rejected',
        'roster.success.added',
        'roster.success.removed',
        'roster.success.left',
        'roster.success.settings_saved',
        'roster.success.absence_marked',
        'roster.success.absence_cleared',
        'roster.success.rejoined',
        'roster.success.session_absence',
        'seo.roster_title',
    ];

    private const EMAIL_KEYS = [
        'campaign_sessions.intro',
        'campaign_sessions.waiting_suffix',
        'campaign_sessions.no_sessions',
        'campaign_sessions.absence_hint',
        'campaign_session_scheduled.subject',
        'campaign_session_scheduled.greeting',
        'campaign_session_scheduled.intro',
        'campaign_session_scheduled.intro_waiting',
        'campaign_session_scheduled.table_title',
        'campaign_session_scheduled.table_date',
        'campaign_session_scheduled.table_location',
        'campaign_session_scheduled.absence_hint',
        'campaign_session_scheduled.view_session',
        'campaign_join_request_received.subject',
        'campaign_join_request_received.greeting',
        'campaign_join_request_received.intro',
        'campaign_join_request_received.message',
        'campaign_join_request_received.review_requests',
        'campaign_join_request_accepted.subject',
        'campaign_join_request_accepted.greeting',
        'campaign_join_request_accepted.intro',
        'campaign_join_request_accepted.view_campaign',
        'campaign_join_request_rejected.subject',
        'campaign_join_request_rejected.greeting',
        'campaign_join_request_rejected.intro',
        'campaign_join_request_rejected.reason',
        'campaign_join_request_rejected.view_campaign',
        'campaign_roster_added.subject',
        'campaign_roster_added.greeting',
        'campaign_roster_added.intro',
        'campaign_roster_added.view_campaign',
        'campaign_roster_removed.subject',
        'campaign_roster_removed.greeting',
        'campaign_roster_removed.intro',
        'campaign_roster_removed.view_campaign',
        'campaign_player_left.subject',
        'campaign_player_left.greeting',
        'campaign_player_left.intro',
        'campaign_player_left.view_roster',
    ];

    private const LOCALE_KEYS = [
        'campaignSession.enrolled',
        'campaignSession.sessionOf',
        'campaignSession.waiting',
        'campaignSession.waitingNoPosition',
        'campaignSession.cannotGo',
        'campaignSession.cannotGoTitle',
        'campaignSession.cannotGoMessage',
        'campaignSession.cannotGoConfirm',
        'campaignSession.stillInCampaign',
        'campaignSession.absent',
        'campaignSession.absentHint',
        'campaignSession.rejoin',
        'campaignSession.viewCampaign',
        'campaignSession.wholeCampaign',
        'campaignSession.requestToJoin',
        'campaignSession.rosterOnly',
        'create.campaign.placeholder',
        'create.campaign.notice',
        'create.campaign.empty',
        'create.campaign.createLink',
        'create.campaign.newTab',
        'create.campaign.emptyAlternative',
        'create.campaign.sessionOnly',
        'myCampaigns.summary',
        'myCampaigns.summaryNoFrequency',
        'myCampaigns.playersNoMax',
        'myCampaigns.pendingRequests',
        'myCampaigns.roster',
        'myCampaigns.addSession',
        'roster.back',
        'roster.title',
        'roster.subtitle',
        'roster.subtitleNoMax',
        'roster.settings.title',
        'roster.settings.acceptsNewPlayers',
        'roster.settings.rosterAccess',
        'roster.settings.maxPlayers',
        'roster.settings.maxPlayersHelp',
        'roster.settings.save',
        'roster.settings.finished',
        'roster.requests.title',
        'roster.requests.noMessage',
        'roster.requests.accept',
        'roster.requests.reject',
        'roster.requests.rejectTitle',
        'roster.requests.rejectReason',
        'roster.requests.rejectConfirm',
        'roster.requests.empty',
        'roster.add.title',
        'roster.add.email',
        'roster.add.submit',
        'roster.players.title',
        'roster.players.name',
        'roster.players.sessions',
        'roster.players.remove',
        'roster.players.removeTitle',
        'roster.players.removeNamed',
        'roster.players.removeMessage',
        'roster.players.removeMessageNoEmail',
        'roster.players.since',
        'roster.players.empty',
        'roster.players.noSessions',
        'roster.chips.going',
        'roster.chips.absent',
        'roster.chips.waiting',
        'roster.chips.none',
        'roster.chips.accessibleName',
        'roster.footnote',
        'join.guest',
        'join.loginToRequest',
        'join.message',
        'join.request',
        'join.requestHint',
        'join.pendingSince',
        'join.pendingHint',
        'join.withdraw',
        'join.withdrawTitle',
        'join.withdrawMessage',
        'join.withdrawConfirm',
        'join.dialogTitle',
        'join.send',
        'join.counter',
        'join.memberTitle',
        'join.memberHint',
        'join.leave',
        'join.leaveTitle',
        'join.leaveMessage',
        'join.leaveConfirm',
        'join.full',
        'join.notAccepting',
        'join.finished',
        'join.membersOnly',
        'join.membersOnlyHint',
        'join.director',
        'join.manageRoster',
    ];

    private const SAMPLE_NAMES = ['John Doe', 'Jane Doe', 'Richard Roe', 'Mary Major'];

    /** @return array<string, mixed> */
    private function lang(string $locale, string $file): array
    {
        return Arr::dot(require dirname(__DIR__, 3)."/lang/{$locale}/{$file}.php");
    }

    /** @return array<string, string> Dotted path below the root object => raw value */
    private function locale(string $locale): array
    {
        $path = [];
        $values = [];

        foreach (explode("\n", (string) file_get_contents(dirname(__DIR__, 3)."/resources/js/locales/{$locale}.ts")) as $line) {
            if (preg_match('/^\s*(\w+): \{$/', $line, $match) === 1) {
                $path[] = $match[1];
            } elseif (preg_match('/^\s*\},?$/', $line) === 1) {
                array_pop($path);
            } elseif (preg_match('/^\s*(\w+): (.+)$/', $line, $match) === 1) {
                $values[implode('.', [...array_slice($path, 1), $match[1]])] = (string) $match[2];
            }
        }

        return $values;
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function phpFiles(): array
    {
        return [
            'es messages' => ['messages', 'es', self::MESSAGE_KEYS],
            'en messages' => ['messages', 'en', self::MESSAGE_KEYS],
            'es emails' => ['emails', 'es', self::EMAIL_KEYS],
            'en emails' => ['emails', 'en', self::EMAIL_KEYS],
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    #[DataProvider('phpFiles')]
    public function test_every_php_key_has_a_text(string $file, string $locale, array $keys): void
    {
        $lang = $this->lang($locale, $file);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $lang, "{$locale}/{$file}: {$key}");
            $this->assertIsString($lang[$key]);
            $this->assertNotSame('', trim($lang[$key]), "{$locale}/{$file}: {$key} is empty");
        }
    }

    public function test_every_page_key_has_a_text_in_both_locales(): void
    {
        foreach (['es', 'en'] as $locale) {
            $values = $this->locale($locale);

            foreach (self::LOCALE_KEYS as $key) {
                $this->assertArrayHasKey($key, $values, "{$locale}.ts: {$key}");
                $this->assertNotSame("'',", $values[$key], "{$locale}.ts: {$key} is empty");
            }
        }
    }

    public function test_the_english_copy_is_not_a_copy_of_the_spanish(): void
    {
        $es = $this->lang('es', 'messages');
        $en = $this->lang('en', 'messages');

        foreach (['roster.errors.roster_full', 'roster.success.left', 'errors.campaign_roster_only'] as $key) {
            $this->assertNotSame($es[$key], $en[$key], $key);
        }

        $this->assertSame('Roster', $en['relation_managers.roster.title']);
        $this->assertSame('Plantilla', $es['relation_managers.roster.title']);
    }

    public function test_the_spanish_values_are_not_title_case(): void
    {
        $messages = $this->lang('es', 'messages');
        $emails = $this->lang('es', 'emails');
        $page = $this->locale('es');
        $texts = [];

        foreach (self::MESSAGE_KEYS as $key) {
            $texts["messages.{$key}"] = $messages[$key];
        }
        foreach (self::EMAIL_KEYS as $key) {
            $texts["emails.{$key}"] = $emails[$key];
        }
        foreach (self::LOCALE_KEYS as $key) {
            $texts["es.ts {$key}"] = $page[$key];
        }

        foreach ($texts as $name => $text) {
            $clean = (string) preg_replace('/(:\w+|\{\w+\})/', '', $text);

            foreach (preg_split('/[.!?:|«»·]\s*/u', $clean) ?: [] as $sentence) {
                $words = preg_split('/\s+/u', trim($sentence), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $capitalised = array_filter(
                    array_slice($words, 1),
                    static fn (string $word): bool => preg_match('/^\p{Lu}\p{Ll}{3,}/u', $word) === 1,
                );
                $this->assertCount(0, $capitalised, "{$name} looks like Title Case: {$text}");
            }
        }
    }

    public function test_the_new_email_greetings_are_the_standard_ones(): void
    {
        $greetings = array_values(array_filter(self::EMAIL_KEYS, static fn (string $key): bool => str_ends_with($key, '.greeting')));
        $this->assertCount(7, $greetings);

        $es = $this->lang('es', 'emails');
        $en = $this->lang('en', 'emails');

        foreach ($greetings as $key) {
            $this->assertSame('¡Hola, :name!', $es[$key], $key);
            $this->assertSame('Hello, :name!', $en[$key], $key);
        }
    }

    public function test_no_value_names_a_real_looking_person(): void
    {
        $texts = [];

        foreach (['es', 'en'] as $locale) {
            $messages = $this->lang($locale, 'messages');
            $emails = $this->lang($locale, 'emails');
            $page = $this->locale($locale);

            foreach (self::MESSAGE_KEYS as $key) {
                $texts["{$locale} messages.{$key}"] = $messages[$key];
            }
            foreach (self::EMAIL_KEYS as $key) {
                $texts["{$locale} emails.{$key}"] = $emails[$key];
            }
            foreach (self::LOCALE_KEYS as $key) {
                $texts["{$locale}.ts {$key}"] = $page[$key];
            }
        }

        foreach ($texts as $name => $text) {
            $text = str_replace(self::SAMPLE_NAMES, '', $text);
            $this->assertSame(0, preg_match('/\b\p{Lu}\p{Ll}+ \p{Lu}\p{Ll}+\b/u', $text), "{$name} names a person: {$text}");
        }
    }
}
