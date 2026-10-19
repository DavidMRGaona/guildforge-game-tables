<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use Livewire\Livewire;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The membership_integration.enabled setting (key kept: sites have it saved) turns on early access
 * for holders of the core's association member role; it does not integrate with any module.
 */
final class GameTablesSettingsLabelsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [GameTablesSettings::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function locales(): array
    {
        return [
            'es' => ['es', 'acceso anticipado', 'rol de socio'],
            'en' => ['en', 'early access', 'association member role'],
        ];
    }

    #[DataProvider('locales')]
    public function test_the_toggle_is_labelled_as_early_access_for_association_members(string $locale, string $inLabel, string $inHelp): void
    {
        $label = __('game-tables::messages.settings.fields.membership_enabled', [], $locale);
        $help = __('game-tables::messages.settings.fields.membership_enabled_help', [], $locale);

        $this->assertStringContainsStringIgnoringCase($inLabel, $label);
        $this->assertStringNotContainsStringIgnoringCase('integra', $label);
        $this->assertStringContainsStringIgnoringCase($inHelp, $help);
    }

    #[DataProvider('locales')]
    public function test_the_section_is_named_after_early_access(string $locale, string $inLabel, string $inHelp): void
    {
        $heading = __('game-tables::messages.settings.sections.membership', [], $locale);

        $this->assertStringContainsStringIgnoringCase($inLabel, $heading);
        $this->assertStringNotContainsStringIgnoringCase('integra', $heading);
    }

    public function test_the_settings_page_keeps_the_saved_key_under_the_new_label(): void
    {
        Livewire::test(GameTablesSettings::class)
            ->assertOk()
            ->assertFormFieldExists('membership_integration.enabled')
            ->assertSee(__('game-tables::messages.settings.fields.membership_enabled'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function guestSettingLabels(): array
    {
        return [
            'section' => ['settings.sections.guest_registrations', 'Inscripciones de invitados'],
            'mode' => ['settings.fields.guest_confirmation_mode', 'Plaza mientras no confirma'],
            'mode hold' => ['settings.guest_confirmation_mode.hold', 'Reserva la plaza con plazo'],
            'mode wait' => ['settings.guest_confirmation_mode.wait', 'No ocupa plaza hasta confirmar'],
            'hold hours' => ['settings.fields.guest_hold_hours', 'Plazo de confirmación (horas)'],
            'cap' => ['settings.fields.guest_max_unconfirmed_held', 'Máximo de plazas sin confirmar por mesa'],
            'cap help' => ['settings.fields.guest_max_unconfirmed_held_help', 'Las inscripciones de invitado sin confirmar que superen este número esperan sin ocupar plaza.'],
            'accounts' => ['settings.fields.guest_accounts_mode', 'Email con cuenta registrada'],
            'accounts require' => ['settings.guest_accounts_mode.require_login', 'Pedir que inicie sesión'],
            'accounts allow' => ['settings.guest_accounts_mode.allow_guest', 'Permitir como invitado salvo que esa cuenta ya esté inscrita'],
            'per minute' => ['settings.fields.guest_rate_per_minute', 'Inscripciones de invitado por minuto y conexión'],
            'per hour' => ['settings.fields.guest_rate_per_hour', 'Inscripciones de invitado por hora y conexión'],
            'window' => ['settings.fields.duplicate_window_hours', 'Ventana de «Posible duplicado» (horas)'],
        ];
    }

    #[DataProvider('guestSettingLabels')]
    public function test_the_guest_settings_have_their_spanish_copy(string $key, string $expected): void
    {
        $this->assertSame($expected, __('game-tables::messages.'.$key, [], 'es'));
        $this->assertNotSame('game-tables::messages.'.$key, __('game-tables::messages.'.$key, [], 'en'));
    }

    public function test_the_guest_section_sits_after_registrations_and_shows_its_fields(): void
    {
        app()->setLocale('es');

        $html = Livewire::test(GameTablesSettings::class)
            ->assertOk()
            ->assertFormFieldExists('guest_confirmation.mode')
            ->assertFormFieldExists('guest_confirmation.hold_hours')
            ->assertFormFieldExists('guest_confirmation.max_unconfirmed_held')
            ->assertFormFieldExists('guest_accounts.mode')
            ->assertFormFieldExists('guest_rate_limit.per_minute')
            ->assertFormFieldExists('guest_rate_limit.per_hour')
            ->assertFormFieldExists('duplicate_signal.window_hours')
            ->assertSee('Inscripciones de invitados')
            ->assertSee('Plazo de confirmación (horas)')
            ->html();

        $registrations = strpos($html, 'Automatización');
        $guests = strpos($html, 'Inscripciones de invitados');
        $this->assertNotFalse($registrations);
        $this->assertNotFalse($guests);
        $this->assertLessThan($registrations, $guests);
    }

    public function test_zero_hold_hours_are_rejected(): void
    {
        Livewire::test(GameTablesSettings::class)
            ->fillForm(['guest_confirmation.hold_hours' => 0])
            ->call('save')
            ->assertHasFormErrors(['guest_confirmation.hold_hours']);
    }
}
