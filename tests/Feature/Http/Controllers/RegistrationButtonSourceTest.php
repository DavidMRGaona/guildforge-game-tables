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

    public function test_closed_reasons_include_the_start_the_end_the_invitation_and_the_closed_campaign(): void
    {
        $source = $this->source('components/RegistrationButton.vue');

        $this->assertStringContainsString(
            "['registration_closed', 'table_started', 'table_ended', 'invitation_only', 'campaign_roster_only']",
            $source
        );
    }

    public function test_a_signed_in_roster_member_gets_the_campaign_box_instead_of_the_usual_flow(): void
    {
        $source = $this->source('components/RegistrationButton.vue');

        $box = $this->computedBody($source, 'rosterSession');
        $this->assertStringContainsString('if (!isAuthenticated.value) return null;', $box);
        $this->assertStringContainsString('if (props.campaignSession?.is_roster_member !== true) return null;', $box);
        // A member's active spectator row is not a roster seat: it keeps the usual flow
        $this->assertStringContainsString("return !hasActiveRegistration.value || registration.value?.role !== 'spectator' ? props.campaignSession : null;", $box);

        $this->assertStringContainsString('campaignSession?: CampaignSessionInfo | null;', $source);
        $this->assertStringContainsString("<CampaignSessionRosterBox\n            v-if=\"rosterSession\"", $source);
        $this->assertStringContainsString("<template v-else>", $source);
    }

    public function test_the_campaign_box_cancels_and_rejoins_through_the_registration_routes(): void
    {
        $source = $this->source('components/CampaignSessionRosterBox.vue');

        $this->assertStringContainsString('router.delete(`/mesas/${props.table.slug}/inscripcion`', $source);
        $this->assertStringContainsString("router.post(\n        `/mesas/\${props.table.slug}/inscripcion`,\n        { role: 'player' },", $source);
        $this->assertStringContainsString('<ConfirmDialog', $source);
        $this->assertStringContainsString(':disabled="!campaignSession.can_rejoin"', $source);
    }

    public function test_the_campaign_session_copy_is_pinned_in_both_locales(): void
    {
        $es = $this->source('locales/es.ts');
        $en = $this->source('locales/en.ts');

        foreach ([
            "enrolled: 'Inscrito por la campaña',",
            "waiting: 'En lista de espera (puesto {position}). Tienes prioridad por ser de la plantilla.',",
            "cannotGo: 'No puedo ir a esta sesión',",
            "cannotGoTitle: '¿No puedes ir a esta sesión?',",
            "cannotGoMessage: 'Seguirás en la campaña y en las demás sesiones.',",
            "cannotGoConfirm: 'Darme de baja',",
            "stillInCampaign: 'Sigues en la campaña y en las demás sesiones.',",
            "absent: 'No vas a esta sesión',",
            "absentHint: 'Si al final puedes, vuelve a apuntarte mientras quede sitio.',",
            "rejoin: 'Volver a apuntarme',",
            "viewCampaign: 'Ver campaña',",
            "wholeCampaign: '¿Quieres jugar toda la campaña?',",
            "requestToJoin: 'Solicita unirte',",
            "empty: 'No diriges ninguna campaña publicada.',",
            "createLink: 'Crea una campaña',",
            "newTab: '(se abre en otra pestaña)',",
            "emptyAlternative: 'o elige otro tipo de mesa.',",
            "waitingNoPosition: 'En lista de espera. Tienes prioridad por ser de la plantilla.',",
            "rosterOnly: 'Solo para la plantilla de la campaña',",
            "sessionOnly: 'Solo puedes crear sesiones de las campañas que diriges.',",
        ] as $line) {
            $this->assertStringContainsString($line, $es);
        }
        $this->assertStringContainsString(
            "notice: 'Al publicarse, la plantilla ({count} jugador) quedará inscrita y recibirá un correo. Se han rellenado el sistema de juego y el máximo de jugadores ({max}). | Al publicarse, la plantilla ({count} jugadores) quedará inscrita y recibirá un correo. Se han rellenado el sistema de juego y el máximo de jugadores ({max}).',",
            $es
        );

        foreach (['campaignSession: {', 'cannotGoConfirm:', 'rejoin:', 'requestToJoin:', 'newTab:', 'emptyAlternative:', 'waitingNoPosition:', 'rosterOnly:', 'sessionOnly:', 'notice:'] as $key) {
            $this->assertStringContainsString($key, $en);
        }
    }

    /**
     * A rejected or no-show row cannot be taken back and the server gives no reason
     * (can_rejoin false, rejoin_refusal null): no dead button and no «vuelve a apuntarte».
     */
    public function test_the_campaign_box_offers_nothing_it_cannot_do(): void
    {
        $source = $this->source('components/CampaignSessionRosterBox.vue');

        $this->assertStringContainsString('() => props.campaignSession.can_rejoin || props.campaignSession.rejoin_refusal !== null', $source);
        $this->assertStringContainsString('<p v-if="rejoinOffered" class="mt-2 text-base-secondary">'."\n                {{ t('gameTables.campaignSession.absentHint') }}", $source);
        $this->assertStringContainsString('<div v-if="rejoinOffered" class="mt-3">', $source);
    }

    public function test_the_waiting_line_has_a_variant_without_a_position(): void
    {
        $source = $this->source('components/CampaignSessionRosterBox.vue');

        $this->assertStringContainsString("waitingPosition !== null\n                        ? t('gameTables.campaignSession.waiting', { position: waitingPosition })\n                        : t('gameTables.campaignSession.waitingNoPosition')", $source);
        $this->assertStringNotContainsString("position: waitingPosition ?? ''", $source);
    }

    public function test_the_edit_form_locks_the_type_for_session_only_authors(): void
    {
        foreach (['pages/GameTables/Create.vue', 'pages/GameTables/Edit.vue'] as $page) {
            $source = $this->source($page);

            $this->assertStringContainsString('sessionOnly?: boolean;', $source, $page);
            $this->assertStringContainsString(":options=\"objectToOptions(props.formData.table_types)\"\n                                    :disabled=\"props.sessionOnly\"", $source, $page);
            $this->assertStringContainsString("<p v-if=\"props.sessionOnly\" class=\"mt-1 text-xs text-base-muted\">\n                                    {{ t('gameTables.create.campaign.sessionOnly') }}", $source, $page);
        }
    }

    public function test_the_campaign_field_names_the_new_tab_and_describes_its_error(): void
    {
        $source = $this->source('components/SessionCampaignField.vue');

        // The new-tab warning is part of the link's text, hence of its accessible name
        $this->assertStringContainsString(">{{ t('gameTables.create.campaign.createLink') }} {{ t('gameTables.create.campaign.newTab') }}</a>", $source);
        // The focusable button of the select is the one described by the error; a button does not
        // take `aria-required` (the label's marker says the field is required)
        $this->assertStringNotContainsString("setAttribute('aria-required'", $source);
        $this->assertStringNotContainsString(':aria-required', $source);
        $this->assertStringContainsString("button.setAttribute('aria-describedby', errorId);", $source);
        $this->assertStringContainsString('<p v-if="error" :id="errorId" class="mt-1 text-sm text-error">', $source);
    }

    public function test_a_closed_campaign_session_shows_the_roster_only_type(): void
    {
        $source = $this->source('pages/GameTables/Show.vue');

        $this->assertStringContainsString("props.campaignSession?.closed === true\n        ? t('gameTables.campaignSession.rosterOnly')\n        : props.table.registrationTypeLabel", $source);
        $this->assertStringContainsString('{{ registrationTypeText }}', $source);
        $this->assertStringNotContainsString('{{ table.registrationTypeLabel }}', $source);
    }

    public function test_the_form_offers_no_campaign_context_any_more(): void
    {
        foreach (['pages/GameTables/Create.vue', 'pages/GameTables/Edit.vue'] as $page) {
            $source = $this->source($page);

            $this->assertStringNotContainsString("context.campaign", $source, $page);
            $this->assertStringContainsString("type ContextType = 'independent' | 'event';", $source, $page);
            $this->assertStringContainsString('<SessionCampaignField', $source, $page);
            $this->assertStringContainsString("if (type !== 'campaign_session') form.campaign_id = null;", $source, $page);
        }
    }

    public function test_an_active_registration_wins_over_every_closed_reason(): void
    {
        $source = $this->source('components/RegistrationButton.vue');

        $disabled = $this->computedBody($source, 'buttonDisabled');
        $this->assertStringContainsString('if (hasActiveRegistration.value) return !canCancel.value;', $disabled);
        $this->assertBefore($disabled, 'hasActiveRegistration.value', 'isRegistrationClosed.value');

        $gate = $this->computedBody($source, 'verificationBlockedAction');
        $this->assertStringContainsString("if (hasActiveRegistration.value) return canCancel.value ? 'cancelRegistration' : null;", $gate);
        $this->assertBefore($gate, 'hasActiveRegistration.value', 'isRegistrationClosed.value');

        $text = $this->computedBody($source, 'buttonText');
        $this->assertStringContainsString("t('gameTables.registration.withdrawalClosed')", $text);
        $this->assertBefore($text, 'hasActiveRegistration.value', 'isRegistrationClosed.value');
    }

    public function test_a_click_with_an_active_registration_never_falls_through_to_registering(): void
    {
        $source = $this->source('components/RegistrationButton.vue');
        $found = preg_match('/^function handleClick\(\).*?^\}/ms', $source, $match);
        $this->assertSame(1, $found);

        $this->assertStringContainsString(
            "if (hasActiveRegistration.value) {\n        if (canCancel.value) showCancelConfirm.value = true;\n        return;\n    }",
            $match[0]
        );
        $this->assertBefore($match[0], 'hasActiveRegistration.value', 'showRoleSelect.value = true');
    }

    public function test_a_closed_table_shows_the_server_message_unless_only_the_date_closed_it(): void
    {
        $text = $this->computedBody($this->source('components/RegistrationButton.vue'), 'buttonText');

        $this->assertStringContainsString(
            "eligibility.value?.reason === 'registration_closed'\n            ? t('gameTables.registration.closed')\n            : (eligibility.value?.message ?? t('gameTables.registration.closed'));",
            $text
        );
    }

    private function assertBefore(string $haystack, string $first, string $second): void
    {
        $a = strpos($haystack, $first);
        $b = strpos($haystack, $second);
        $this->assertNotFalse($a, "{$first} missing");
        $this->assertNotFalse($b, "{$second} missing");
        $this->assertLessThan($b, $a, "{$first} must come before {$second}");
    }

    public function test_a_visitor_on_a_closed_table_sees_the_server_message_disabled_instead_of_the_login_button(): void
    {
        $source = $this->source('components/RegistrationButton.vue');

        $closed = $this->computedBody($source, 'guestRegistrationClosed');
        $this->assertStringContainsString('if (isAuthenticated.value) return false;', $closed);
        $this->assertStringContainsString('if (!guest || guest.eligible || guest.spectator.eligible) return false;', $closed);
        $this->assertStringContainsString('return guest.reason !== null && CLOSED_REASONS.includes(guest.reason);', $closed);

        $text = $this->computedBody($source, 'buttonText');
        $this->assertStringContainsString(
            "if (guestRegistrationClosed.value) {\n            return props.guestEligibility?.message ?? t('gameTables.registration.closed');\n        }\n        return t('gameTables.registration.loginRequired');",
            $text
        );

        $disabled = $this->computedBody($source, 'buttonDisabled');
        $this->assertStringContainsString('return guestRegistrationClosed.value;', $disabled);
        $this->assertStringNotContainsString('return false; // Login redirect still works', $disabled);
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
