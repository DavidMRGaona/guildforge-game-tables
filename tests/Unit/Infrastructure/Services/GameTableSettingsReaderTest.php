<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Infrastructure\Services;

use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Tests\TestCase;

final class GameTableSettingsReaderTest extends TestCase
{
    private GameTableSettingsReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', null);
        $this->reader = new GameTableSettingsReader();
    }

    /**
     * @return array<string, mixed>
     */
    private function shippedDefaults(): array
    {
        return require dirname(__DIR__, 4).'/config/settings.php';
    }

    public function test_the_shipped_defaults_are_the_settings_file_of_the_module(): void
    {
        $this->assertSame($this->shippedDefaults(), $this->reader->defaults());
    }

    public function test_the_module_has_a_single_source_of_defaults(): void
    {
        $this->assertFileDoesNotExist(dirname(__DIR__, 4).'/config/game-tables.php');
    }

    public function test_without_panel_values_every_setting_is_the_shipped_default(): void
    {
        $defaults = $this->shippedDefaults();

        $this->assertSame($defaults, $this->reader->all());
        $this->assertTrue($this->reader->isNotifyOnRegistrationEnabled());
        $this->assertTrue($this->reader->isNotifyOnCancellationEnabled());
        $this->assertTrue($this->reader->isNotifyWaitingListPromotionEnabled());
        $this->assertSame($defaults['frontend_creation']['enabled'], $this->reader->isFrontendCreationEnabled());
        $this->assertSame($defaults['frontend_creation']['allowed_content'], $this->reader->getAllowedContent());
        $this->assertSame($defaults['frontend_creation']['publication']['mode'], $this->reader->getPublicationMode());
        $this->assertSame($defaults['defaults']['duration_minutes'], $this->reader->defaultDurationMinutes());
        $this->assertSame($defaults['defaults']['min_players'], $this->reader->defaultMinPlayers());
        $this->assertSame($defaults['defaults']['max_players'], $this->reader->defaultMaxPlayers());
        $this->assertSame($defaults['defaults']['max_spectators'], $this->reader->defaultMaxSpectators());
        $this->assertSame($defaults['defaults']['auto_confirm'], $this->reader->defaultAutoConfirm());
        $this->assertSame($defaults['defaults']['registration_type'], $this->reader->defaultRegistrationType()->value);
        $this->assertSame($defaults['defaults']['members_early_access_days'], $this->reader->defaultMembersEarlyAccessDays());
        $this->assertSame($defaults['limits']['max_players_limit'], $this->reader->maxPlayersLimit());
        $this->assertSame($defaults['limits']['max_spectators_limit'], $this->reader->maxSpectatorsLimit());
        $this->assertSame($defaults['limits']['max_duration_minutes'], $this->reader->maxDurationMinutes());
        $this->assertSame($defaults['membership_integration']['enabled'], $this->reader->isMembershipIntegrationEnabled());
    }

    // The values the module had before the panel sections were wired keep applying
    public function test_the_shipped_defaults_keep_the_current_effective_values(): void
    {
        $this->assertTrue($this->reader->isFrontendCreationEnabled());
        $this->assertSame('both', $this->reader->getAllowedContent());
        $this->assertSame('registered', $this->reader->getAccessLevel()->value);
        $this->assertSame('approval', $this->reader->getPublicationMode());
        $this->assertSame([], $this->reader->getAllowedRoles());
        $this->assertNull($this->reader->getRequiredPermission());
        $this->assertSame([], $this->reader->getPriorityTiers());
        $this->assertSame([], $this->reader->getAutoPublishRoles());
        $this->assertSame([], $this->reader->getAutoPublishPermissions());
        $this->assertSame(240, $this->reader->defaultDurationMinutes());
        $this->assertSame(3, $this->reader->defaultMinPlayers());
        $this->assertSame(5, $this->reader->defaultMaxPlayers());
        $this->assertSame(0, $this->reader->defaultMaxSpectators());
        $this->assertTrue($this->reader->defaultAutoConfirm());
        $this->assertSame(RegistrationType::Everyone, $this->reader->defaultRegistrationType());
        $this->assertSame(0, $this->reader->defaultMembersEarlyAccessDays());
        $this->assertSame(20, $this->reader->maxPlayersLimit());
        $this->assertSame(50, $this->reader->maxSpectatorsLimit());
        $this->assertSame(720, $this->reader->maxDurationMinutes());
        $this->assertTrue($this->reader->isMembershipIntegrationEnabled());
    }

    // Off by default: the module behaves as before until a site turns the list on
    public function test_the_waiting_list_is_off_by_default(): void
    {
        $this->assertFalse($this->shippedDefaults()['registration']['waiting_list_enabled']);
        $this->assertFalse($this->reader->isWaitingListEnabled());
    }

    public function test_the_waiting_list_follows_the_panel_value(): void
    {
        config()->set('modules.settings.game-tables', ['registration' => ['waiting_list_enabled' => true]]);

        $this->assertTrue($this->reader->isWaitingListEnabled());
    }

    public function test_the_creators_key_is_no_longer_a_setting(): void
    {
        $this->assertArrayNotHasKey('creators', $this->reader->defaults());
    }

    public function test_panel_values_override_the_defaults_key_by_key(): void
    {
        config()->set('modules.settings.game-tables', [
            'notifications' => ['notify_on_registration' => false],
            'defaults' => ['duration_minutes' => 180],
            'limits' => ['max_players_limit' => 8],
            'membership_integration' => ['enabled' => false],
        ]);

        $this->assertFalse($this->reader->isNotifyOnRegistrationEnabled());
        $this->assertTrue($this->reader->isNotifyOnCancellationEnabled());
        $this->assertSame(180, $this->reader->defaultDurationMinutes());
        $this->assertSame(3, $this->reader->defaultMinPlayers());
        $this->assertSame(8, $this->reader->maxPlayersLimit());
        $this->assertSame(50, $this->reader->maxSpectatorsLimit());
        $this->assertFalse($this->reader->isMembershipIntegrationEnabled());
        $this->assertSame(180, $this->reader->all()['defaults']['duration_minutes']);
        $this->assertSame(5, $this->reader->all()['defaults']['max_players']);
    }

    public function test_numeric_panel_values_saved_as_strings_are_read_as_integers(): void
    {
        config()->set('modules.settings.game-tables.defaults.duration_minutes', '180');
        config()->set('modules.settings.game-tables.limits.max_players_limit', '8');

        $this->assertSame(180, $this->reader->defaultDurationMinutes());
        $this->assertSame(8, $this->reader->maxPlayersLimit());
    }

    public function test_a_panel_list_replaces_the_default_list_whole(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation.publication.auto_publish_roles', ['editor']);

        $this->assertSame(['editor'], $this->reader->getAutoPublishRoles());
    }

    // The panel field is a comma-separated text input
    public function test_allowed_roles_typed_in_the_panel_are_split_by_commas(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation.allowed_roles', 'socio, colaborador ,');

        $this->assertSame(['socio', 'colaborador'], $this->reader->getAllowedRoles());
    }

    public function test_an_unknown_registration_type_falls_back_to_everyone(): void
    {
        config()->set('modules.settings.game-tables.defaults.registration_type', 'nonsense');

        $this->assertSame(RegistrationType::Everyone, $this->reader->defaultRegistrationType());
    }

    public function test_automatic_status_updates_are_on_by_default(): void
    {
        $this->assertTrue($this->shippedDefaults()['automation']['auto_status_enabled']);
        $this->assertTrue($this->reader->isAutoStatusEnabled());
    }

    public function test_the_panel_turns_automatic_status_updates_off(): void
    {
        config()->set('modules.settings.game-tables', ['automation' => ['auto_status_enabled' => false]]);

        $this->assertFalse($this->reader->isAutoStatusEnabled());
    }
}
