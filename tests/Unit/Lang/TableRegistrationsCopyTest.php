<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Lang;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The copy added for the delegated registrations exists in Spanish and English. The key lists are
 * written out on purpose: they are not derived from the files they check.
 */
final class TableRegistrationsCopyTest extends TestCase
{
    private const MESSAGE_KEYS = [
        'fields.delegates_registrations',
        'fields.delegates_registrations_help',
        'validation.delegated_directors_need_accounts',
        'errors.not_pending',
        'relation_managers.participants.reject_reason',
        'relation_managers.status_log.title',
        'relation_managers.status_log.when',
        'relation_managers.status_log.participant',
        'relation_managers.status_log.change',
        'relation_managers.status_log.actor',
        'relation_managers.status_log.reason',
        'relation_managers.status_log.system',
        'relation_managers.status_log.by_participant',
        'relation_managers.status_log.deleted_account',
        'relation_managers.status_log.registered',
        'registrations.errors.awaiting_email',
        'registrations.errors.too_many_actions',
        'registrations.success.approved',
        'registrations.success.rejected',
        'seo.registrations_title',
    ];

    private const EMAIL_KEYS = [
        'rejection.subject',
        'rejection.greeting',
        'rejection.intro',
        'rejection.table_title',
        'rejection.table_date',
        'rejection.table_location',
        'rejection.reason',
        'rejection.outro',
        'registration.manage',
        'registration.pending_intro',
    ];

    private const LOCALE_KEYS = [
        'myTables.drafts',
        'myTables.published',
        'myTables.manageRegistrations',
        'myTables.pendingCount',
        'myTables.emptyPublished',
        'myTables.emptyDrafts',
        'registrations.title',
        'registrations.back',
        'registrations.pending',
        'registrations.others',
        'registrations.empty',
        'registrations.noPending',
        'registrations.awaitingEmail',
        'registrations.guest',
        'registrations.approve',
        'registrations.reject',
        'registrations.approveNamed',
        'registrations.rejectTitle',
        'registrations.rejectReason',
        'registrations.rejectConfirm',
        'registrations.autoConfirmNotice',
        'registrations.seats',
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


    public function test_the_english_copy_is_not_a_copy_of_the_spanish(): void
    {
        $es = $this->lang('es', 'messages');
        $en = $this->lang('en', 'messages');

        foreach (['fields.delegates_registrations', 'errors.not_pending', 'registrations.errors.awaiting_email'] as $key) {
            $this->assertNotSame($es[$key], $en[$key], $key);
        }

        $this->assertSame('Historial de inscripciones', $es['relation_managers.status_log.title']);
        $this->assertSame('Registration history', $en['relation_managers.status_log.title']);
    }

    // With published tables but no drafts, the drafts list must not claim there are no tables
    public function test_my_tables_tells_an_empty_drafts_list_apart_from_no_tables(): void
    {
        $page = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/GameTables/MyTables.vue');

        $this->assertMatchesRegularExpression(
            "/<p v-if=\"props\.drafts\.length === 0\"[^>]*>\s*\{\{ t\('gameTables\.myTables\.emptyDrafts'\) \}\}/",
            $page,
        );
        $this->assertSame("'No tienes borradores.',", $this->locale('es')['myTables.emptyDrafts']);
        $this->assertSame("'You have no drafts.',", $this->locale('en')['myTables.emptyDrafts']);
    }

    public function test_the_rejection_email_carries_the_optional_reason(): void
    {
        $this->assertSame('Motivo: :reason', $this->lang('es', 'emails')['rejection.reason']);
        $this->assertSame('Reason: :reason', $this->lang('en', 'emails')['rejection.reason']);
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
