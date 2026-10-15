<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The table defaults on the settings page are checked against the limits sent in the same
 * submission, so a new table never starts above them. The fixed bounds stay as outer bounds.
 */
final class GameTablesSettingsLimitsTest extends ModuleTestCase
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
     * @return array<string, array{bool}>
     */
    public static function waitingListSetting(): array
    {
        return [
            'waiting list off' => [false],
            'waiting list on' => [true],
        ];
    }

    // Characterization: defaults within the limits, or equal to them, are saved

    #[DataProvider('waitingListSetting')]
    public function test_defaults_within_the_limits_are_saved(bool $waitingList): void
    {
        $this->save([
            'registration.waiting_list_enabled' => $waitingList,
            'defaults.min_players' => 2,
            'defaults.max_players' => 6,
            'defaults.max_spectators' => 1,
            'defaults.duration_minutes' => 180,
            'defaults.members_early_access_days' => 3,
        ])->assertHasNoFormErrors();

        $settings = new GameTableSettingsReader();
        $this->assertSame(6, $settings->defaultMaxPlayers());
        $this->assertSame(1, $settings->defaultMaxSpectators());
        $this->assertSame(180, $settings->defaultDurationMinutes());
        $this->assertSame(3, $settings->defaultMembersEarlyAccessDays());
        $this->assertSame($waitingList, $settings->isWaitingListEnabled());
    }

    #[DataProvider('waitingListSetting')]
    public function test_defaults_equal_to_lowered_limits_are_saved(bool $waitingList): void
    {
        $this->save([
            'registration.waiting_list_enabled' => $waitingList,
            'limits.max_players_limit' => 6,
            'limits.max_spectators_limit' => 2,
            'limits.max_duration_minutes' => 300,
            'defaults.min_players' => 6,
            'defaults.max_players' => 6,
            'defaults.max_spectators' => 2,
            'defaults.duration_minutes' => 300,
        ])->assertHasNoFormErrors();

        $settings = new GameTableSettingsReader();
        $this->assertSame(6, $settings->maxPlayersLimit());
        $this->assertSame(6, $settings->defaultMaxPlayers());
    }

    public function test_the_fixed_bounds_stay_as_outer_bounds_above_raised_limits(): void
    {
        $this->save([
            'limits.max_players_limit' => 100,
            'limits.max_spectators_limit' => 200,
            'limits.max_duration_minutes' => 1440,
            'defaults.min_players' => 21,
            'defaults.max_players' => 21,
            'defaults.max_spectators' => 51,
            'defaults.duration_minutes' => 721,
            'defaults.members_early_access_days' => 31,
        ])->assertHasFormErrors([
            'defaults.min_players' => 'max',
            'defaults.max_players' => 'max',
            'defaults.max_spectators' => 'max',
            'defaults.duration_minutes' => 'max',
            'defaults.members_early_access_days' => 'max',
        ]);
    }

    public function test_the_fixed_bounds_still_admit_their_maximum(): void
    {
        $this->save([
            'defaults.min_players' => 20,
            'defaults.max_players' => 20,
            'defaults.max_spectators' => 50,
            'defaults.duration_minutes' => 720,
            'defaults.members_early_access_days' => 30,
        ])->assertHasNoFormErrors();
    }

    // Defaults above the limits of the same submission are refused

    #[DataProvider('waitingListSetting')]
    public function test_a_default_above_its_limit_is_refused_with_a_field_error(bool $waitingList): void
    {
        $this->save([
            'registration.waiting_list_enabled' => $waitingList,
            'limits.max_players_limit' => 8,
            'limits.max_spectators_limit' => 2,
            'limits.max_duration_minutes' => 480,
            'defaults.min_players' => 9,
            'defaults.max_players' => 10,
            'defaults.max_spectators' => 3,
            'defaults.duration_minutes' => 600,
        ])->assertHasFormErrors([
            'defaults.min_players',
            'defaults.max_players',
            'defaults.max_spectators',
            'defaults.duration_minutes',
        ])->assertHasNoFormErrors([
            'limits.max_players_limit',
            'limits.max_spectators_limit',
            'limits.max_duration_minutes',
        ]);

        // Nothing is saved
        $settings = new GameTableSettingsReader();
        $this->assertSame(20, $settings->maxPlayersLimit());
        $this->assertSame(5, $settings->defaultMaxPlayers());
        $this->assertFalse($settings->isWaitingListEnabled());
    }

    public function test_the_limits_sent_with_the_defaults_count_not_the_stored_ones(): void
    {
        // Stored limit 20; the submission lowers it below the default
        $this->save([
            'limits.max_players_limit' => 4,
            'defaults.max_players' => 5,
        ])->assertHasFormErrors(['defaults.max_players']);

        // Stored limit 8; the submission raises it above the default
        config()->set('modules.settings.game-tables.limits.max_players_limit', 8);

        $this->save([
            'limits.max_players_limit' => 12,
            'defaults.max_players' => 10,
        ])->assertHasNoFormErrors();

        $this->assertSame(10, (new GameTableSettingsReader())->defaultMaxPlayers());
    }

    public function test_the_default_minimum_may_not_exceed_the_default_maximum(): void
    {
        $this->save([
            'defaults.min_players' => 6,
            'defaults.max_players' => 4,
        ])->assertHasFormErrors(['defaults.min_players'])
            ->assertHasNoFormErrors(['defaults.max_players']);
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function refusals(): array
    {
        return [
            'players es' => ['es', 'defaults.max_players', 'above_players_limit', 8],
            'players en' => ['en', 'defaults.max_players', 'above_players_limit', 8],
            'spectators es' => ['es', 'defaults.max_spectators', 'above_spectators_limit', 2],
            'spectators en' => ['en', 'defaults.max_spectators', 'above_spectators_limit', 2],
            'duration es' => ['es', 'defaults.duration_minutes', 'above_duration_limit', 480],
            'duration en' => ['en', 'defaults.duration_minutes', 'above_duration_limit', 480],
            'minimum es' => ['es', 'defaults.min_players', 'min_above_max_players', 4],
            'minimum en' => ['en', 'defaults.min_players', 'min_above_max_players', 4],
        ];
    }

    #[DataProvider('refusals')]
    public function test_the_refusal_names_the_limit_in_each_language(string $locale, string $field, string $key, int $limit): void
    {
        app()->setLocale($locale);
        $message = __('game-tables::messages.settings.validation.'.$key, ['limit' => $limit]);

        $this->assertNotSame('game-tables::messages.settings.validation.'.$key, $message);
        $this->assertStringContainsString((string) $limit, $message);

        $this->save([
            'limits.max_players_limit' => 8,
            'limits.max_spectators_limit' => 2,
            'limits.max_duration_minutes' => 480,
            'defaults.min_players' => $field === 'defaults.min_players' ? 5 : 3,
            'defaults.max_players' => $field === 'defaults.max_players' ? 10 : 4,
            'defaults.max_spectators' => $field === 'defaults.max_spectators' ? 3 : 0,
            'defaults.duration_minutes' => $field === 'defaults.duration_minutes' ? 600 : 240,
        ])->assertHasFormErrors([$field => $message]);
    }

    public function test_the_spanish_and_english_refusals_differ(): void
    {
        foreach (['above_players_limit', 'above_spectators_limit', 'above_duration_limit', 'min_above_max_players'] as $key) {
            $this->assertNotSame(
                __('game-tables::messages.settings.validation.'.$key, ['limit' => 1], 'es'),
                __('game-tables::messages.settings.validation.'.$key, ['limit' => 1], 'en'),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return Testable<GameTablesSettings>
     */
    private function save(array $values): Testable
    {
        return Livewire::test(GameTablesSettings::class)
            ->fillForm($values)
            ->call('save');
    }
}
