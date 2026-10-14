<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use Livewire\Livewire;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel settings take effect: the page starts from the shipped defaults,
 * and the table form takes its defaults and limits from them.
 */
final class GameTablesSettingsWiringTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class], [GameTablesSettings::class]);
        $this->actingAsPanelAdmin();

        // Nothing saved from the panel yet
        config()->set('modules.settings.game-tables', []);
    }

    public function test_the_settings_page_starts_from_the_shipped_defaults(): void
    {
        Livewire::test(GameTablesSettings::class)
            ->assertOk()
            ->assertFormSet([
                'membership_integration.enabled' => true,
                'registration.waiting_list_enabled' => false,
                'defaults.duration_minutes' => 240,
                'defaults.min_players' => 3,
                'defaults.max_players' => 5,
                'limits.max_players_limit' => 20,
                'notifications.notify_on_registration' => true,
                'frontend_creation.enabled' => true,
                'frontend_creation.allowed_content' => 'both',
                'frontend_creation.access_level' => 'registered',
                'frontend_creation.publication.mode' => 'approval',
            ]);
    }

    public function test_the_settings_page_shows_saved_values_over_the_defaults(): void
    {
        config()->set('modules.settings.game-tables', ['defaults' => ['duration_minutes' => 180]]);

        Livewire::test(GameTablesSettings::class)
            ->assertFormSet([
                'defaults.duration_minutes' => 180,
                'defaults.min_players' => 3,
                'frontend_creation.allowed_content' => 'both',
            ]);
    }

    public function test_the_settings_page_turns_the_waiting_list_on(): void
    {
        Livewire::test(GameTablesSettings::class)
            ->assertFormFieldExists('registration.waiting_list_enabled')
            ->fillForm(['registration.waiting_list_enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue((new \Modules\GameTables\Infrastructure\Services\GameTableSettingsReader())->isWaitingListEnabled());
    }

    // Panel creation is governed by the gametables.create permission and web creation by its access level
    public function test_the_settings_page_has_no_creators_setting(): void
    {
        Livewire::test(GameTablesSettings::class)
            ->assertFormFieldDoesNotExist('creators');
    }

    public function test_the_table_form_takes_its_defaults_from_the_settings(): void
    {
        config()->set('modules.settings.game-tables.defaults', [
            'duration_minutes' => 180,
            'min_players' => 2,
            'max_players' => 6,
            'max_spectators' => 1,
            'auto_confirm' => false,
            'registration_type' => 'members_only',
            'members_early_access_days' => 2,
        ]);

        Livewire::test(CreateGameTable::class)
            ->assertFormSet([
                'duration_minutes' => 180,
                'min_players' => 2,
                'max_players' => 6,
                'max_spectators' => 1,
                'auto_confirm' => false,
                'registration_type' => 'members_only',
                'members_early_access_days' => 2,
            ]);
    }

    public function test_the_table_form_applies_the_limits_from_the_settings(): void
    {
        config()->set('modules.settings.game-tables.limits', [
            'max_players_limit' => 8,
            'max_spectators_limit' => 2,
            'max_duration_minutes' => 120,
        ]);

        Livewire::test(CreateGameTable::class)
            ->fillForm(['min_players' => 9, 'max_players' => 10, 'max_spectators' => 3, 'duration_minutes' => 150])
            ->call('create')
            ->assertHasFormErrors([
                'min_players' => 'max',
                'max_players' => 'max',
                'max_spectators' => 'max',
                'duration_minutes' => 'max',
            ]);
    }

    public function test_the_default_limits_keep_the_previous_bounds(): void
    {
        Livewire::test(CreateGameTable::class)
            ->fillForm(['min_players' => 20, 'max_players' => 20, 'max_spectators' => 50, 'duration_minutes' => 720])
            ->call('create')
            ->assertHasNoFormErrors(['min_players', 'max_players', 'max_spectators', 'duration_minutes'])
            ->fillForm(['max_players' => 21, 'max_spectators' => 51, 'duration_minutes' => 721])
            ->call('create')
            ->assertHasFormErrors(['max_players' => 'max', 'max_spectators' => 'max', 'duration_minutes' => 'max']);
    }
}
