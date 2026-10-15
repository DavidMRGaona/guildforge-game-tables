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
}
