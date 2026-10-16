<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources to pin the rules the
 * registration button and the cancellation page follow (the server stays authoritative).
 */
final class RegistrationButtonSourceTest extends TestCase
{
    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/'.$file);
    }

    /** The body of `const <name> = computed...;` up to the next top-level declaration. */
    private function computedBody(string $source, string $name): string
    {
        $found = preg_match('/^const '.preg_quote($name, '/').'\b.*?^\}\);/ms', $source, $match);
        $this->assertSame(1, $found, "computed {$name} not found");

        return $match[0];
    }

    public function test_cancelling_follows_the_server_flag_only_with_an_active_registration(): void
    {
        $body = $this->computedBody($this->source('components/RegistrationButton.vue'), 'canCancel');

        $this->assertStringContainsString('hasActiveRegistration.value', $body);
        $this->assertStringContainsString('eligibility.value?.canCancel === true', $body);
    }

    public function test_closed_reasons_include_the_start_the_end_and_the_invitation(): void
    {
        $source = $this->source('components/RegistrationButton.vue');

        $this->assertStringContainsString(
            "['registration_closed', 'table_started', 'table_ended', 'invitation_only']",
            $source
        );
    }

    public function test_an_active_registration_wins_over_every_closed_reason(): void
    {
        $source = $this->source('components/RegistrationButton.vue');

        $disabled = $this->computedBody($source, 'buttonDisabled');
        $this->assertLessThan(
            strpos($disabled, 'isRegistrationClosed.value'),
            strpos($disabled, 'hasActiveRegistration.value'),
            'buttonDisabled must look at the active registration before the closed reasons'
        );
        $this->assertStringContainsString('if (hasActiveRegistration.value) return !canCancel.value;', $disabled);

        $gate = $this->computedBody($source, 'verificationBlockedAction');
        $this->assertLessThan(
            strpos($gate, 'isRegistrationClosed.value'),
            strpos($gate, 'hasActiveRegistration.value'),
            'verificationBlockedAction must look at the active registration before the closed reasons'
        );

        $text = $this->computedBody($source, 'buttonText');
        $this->assertLessThan(
            strpos($text, 'isRegistrationClosed.value'),
            strpos($text, 'hasActiveRegistration.value'),
        );
        $this->assertStringContainsString("t('gameTables.registration.withdrawalClosed')", $text);
    }

    public function test_a_closed_table_shows_the_server_message_unless_only_the_date_closed_it(): void
    {
        $text = $this->computedBody($this->source('components/RegistrationButton.vue'), 'buttonText');

        $this->assertStringContainsString("eligibility.value?.reason === 'registration_closed'", $text);
        $this->assertStringContainsString('eligibility.value?.message', $text);
    }

    public function test_the_eligibility_type_carries_the_cancel_flag(): void
    {
        $this->assertMatchesRegularExpression('/canCancel: boolean;/', $this->source('types/registration.ts'));
    }

    public function test_the_cancellation_page_uses_the_module_keys_and_the_started_message(): void
    {
        $source = $this->source('pages/GameTables/CancelRegistration.vue');

        $this->assertStringNotContainsString("gameTables.cancelRegistration.", $source);
        $this->assertStringContainsString('tableStarted: boolean;', $source);
        $this->assertStringContainsString("tableStarted ? t('cancelRegistration.tableStarted') : t('cancelRegistration.alreadyCancelled')", $source);
    }

    public function test_the_new_texts_exist_in_the_right_place_of_both_locales(): void
    {
        $es = $this->source('locales/es.ts');
        $en = $this->source('locales/en.ts');

        // withdrawalClosed sits in the registration block (right after registeredAt), tableStarted in cancelRegistration
        $this->assertStringContainsString("registeredAt: 'Inscrito el',\n            withdrawalClosed: 'La partida ya ha comenzado: ya no puedes cancelar la inscripción',", $es);
        $this->assertStringContainsString("guest: 'Invitado',\n        tableStarted: 'La partida ya ha comenzado: ya no puedes cancelar la inscripción.',", $es);
        $this->assertStringContainsString("registeredAt: 'Registered on',\n            withdrawalClosed: 'The session has already started: you can no longer cancel your registration',", $en);
        $this->assertStringContainsString("guest: 'Guest',\n        tableStarted: 'The session has already started: you can no longer cancel your registration.',", $en);
    }
}
