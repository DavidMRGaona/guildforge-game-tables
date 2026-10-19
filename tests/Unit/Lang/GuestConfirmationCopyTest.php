<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Lang;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * Copy of the guest email confirmation: every key exists in both languages (PHP lang files and
 * page locales) and the Spanish texts follow the project's capitalization.
 */
final class GuestConfirmationCopyTest extends TestCase
{
    private const array NEW_MESSAGE_KEYS = [
        'cookies.guest_registrations.purpose',
        'cookies.guest_registrations.duration',
        'settings.sections.guest_registrations',
        'settings.fields.guest_confirmation_mode',
        'settings.fields.guest_hold_hours',
        'settings.fields.guest_max_unconfirmed_held',
        'settings.fields.guest_max_unconfirmed_held_help',
        'settings.fields.guest_accounts_mode',
        'settings.fields.guest_rate_per_minute',
        'settings.fields.guest_rate_per_hour',
        'settings.fields.duplicate_window_hours',
        'settings.guest_confirmation_mode.hold',
        'settings.guest_confirmation_mode.wait',
        'settings.guest_accounts_mode.require_login',
        'settings.guest_accounts_mode.allow_guest',
        'enums.participant_status.expired',
        'errors.email_registered_as_guest',
        'errors.email_has_account',
        'errors.invalid_confirmation',
        'errors.confirmation_closed',
        'errors.confirmation_filled_up',
        'errors.too_many_confirmation_attempts',
        'errors.guest_rate_limited',
        'success.guest_email_confirmed',
        'success.guest_email_already_confirmed',
        'success.guest_email_confirmed_pending',
        'success.guest_email_confirmed_waiting_list',
        'confirm_registration.awaiting_email',
        'success.registration_linked',
        'success.registration_linked_waiting_list',
        'success.guest_confirmation_pending',
        'success.guest_confirmation_sent',
        'success.guest_confirmation_sent_waiting_list',
        'success.guest_confirmation_sent_unguaranteed',
    ];

    /**
     * @return array<string, string>
     */
    private function lang(string $locale, string $file): array
    {
        return Arr::dot(require dirname(__DIR__, 3)."/lang/{$locale}/{$file}.php");
    }

    /**
     * Dotted paths of the keys of a locale module, read from its indentation.
     *
     * @return list<string>
     */
    private function localeKeys(string $locale): array
    {
        $lines = file(dirname(__DIR__, 3)."/resources/js/locales/{$locale}.ts", FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines);

        $stack = [];
        $keys = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\s*)([\'"]?[\w.\-]+[\'"]?):\s*(\{)?/', $line, $m) !== 1) {
                continue;
            }

            $depth = intdiv(strlen($m[1]), 4);
            $stack = array_slice($stack, 0, $depth);
            $stack[] = trim($m[2], '\'"');

            if (! isset($m[3])) {
                $keys[] = implode('.', $stack);
            }
        }

        sort($keys);

        return $keys;
    }

    public function test_every_message_key_exists_in_both_languages(): void
    {
        $es = array_keys($this->lang('es', 'messages'));
        $en = array_keys($this->lang('en', 'messages'));

        foreach (self::NEW_MESSAGE_KEYS as $key) {
            $this->assertContains($key, $es, "{$key} missing in es");
            $this->assertContains($key, $en, "{$key} missing in en");
        }

    }

    public function test_the_new_message_keys_have_a_text_in_both_languages(): void
    {
        foreach (['es', 'en'] as $locale) {
            $messages = $this->lang($locale, 'messages');

            foreach (self::NEW_MESSAGE_KEYS as $key) {
                $this->assertArrayHasKey($key, $messages, "{$locale}: {$key}");
                $this->assertNotSame('', trim((string) $messages[$key]), "{$locale}: {$key}");
            }
        }
    }

    public function test_the_guest_emails_exist_in_both_languages(): void
    {
        foreach (['es', 'en'] as $locale) {
            $emails = $this->lang($locale, 'emails');

            foreach (['subject', 'greeting', 'intro', 'button'] as $part) {
                $this->assertArrayHasKey("guest_email_confirmation.{$part}", $emails, $locale);
                $this->assertArrayHasKey("guest_registration_expired.{$part}", $emails, $locale);
            }
        }
    }

    public function test_the_page_locales_have_the_same_keys(): void
    {
        $es = $this->localeKeys('es');
        $en = $this->localeKeys('en');
        $scoped = static fn (array $keys): array => array_values(array_filter(
            $keys,
            static fn (string $key): bool => str_contains($key, 'guestRegistration.') || str_contains($key, 'confirmRegistration.'),
        ));

        $this->assertSame($scoped($es), $scoped($en));

        $this->assertNotEmpty(preg_grep('/guestRegistration\.logIn$/', $scoped($en)));
        $this->assertNotEmpty(preg_grep('/confirmRegistration\.button$/', $scoped($en)));
    }

    public function test_the_new_spanish_texts_are_not_in_title_case(): void
    {
        $messages = $this->lang('es', 'messages');

        foreach (self::NEW_MESSAGE_KEYS as $key) {
            $words = preg_split('/\s+/', trim((string) $messages[$key])) ?: [];
            $capitalised = array_filter($words, static fn (string $word): bool => preg_match('/^\p{Lu}\p{Ll}/u', $word) === 1);

            $this->assertLessThan(3, count($capitalised), "Title Case in {$key}");
        }
    }
}
