<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Tables created from the public form take the panel defaults.
 */
final class FrontendCreationSettingsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', [
            'frontend_creation' => ['enabled' => true, 'allowed_content' => 'both', 'access_level' => 'registered'],
        ]);
    }

    public function test_the_form_data_carries_the_defaults_and_limits_of_the_settings(): void
    {
        config()->set('modules.settings.game-tables.defaults', ['duration_minutes' => 180, 'min_players' => 2, 'max_players' => 6, 'max_spectators' => 1]);
        config()->set('modules.settings.game-tables.limits', ['max_players_limit' => 8, 'max_spectators_limit' => 2, 'max_duration_minutes' => 300]);

        $formData = app(FrontendCreationServiceInterface::class)->getCreateFormData();

        $this->assertSame(['duration_minutes' => 180, 'min_players' => 2, 'max_players' => 6, 'max_spectators' => 1], $formData['defaults']);
        $this->assertSame(['max_players' => 8, 'max_spectators' => 2, 'max_duration_minutes' => 300], $formData['limits']);
    }

    public function test_a_table_created_from_the_web_takes_the_registration_defaults(): void
    {
        config()->set('modules.settings.game-tables.defaults', [
            'auto_confirm' => false,
            'registration_type' => 'members_only',
            'members_early_access_days' => 2,
        ]);

        $table = $this->createFromTheWeb();

        $this->assertFalse($table->auto_confirm);
        $this->assertSame('members_only', $table->registration_type->value);
        $this->assertSame(2, $table->members_early_access_days);
    }

    public function test_without_panel_values_a_web_table_keeps_the_previous_registration_values(): void
    {
        $table = $this->createFromTheWeb();

        $this->assertTrue($table->auto_confirm);
        $this->assertSame('everyone', $table->registration_type->value);
        $this->assertSame(0, $table->members_early_access_days);
    }

    private function createFromTheWeb(): GameTableModel
    {
        $user = UserModel::factory()->create();
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);

        $created = app(FrontendCreationServiceInterface::class)->createGameTable(new FrontendCreateGameTableDTO(
            createdBy: (string) $user->id,
            gameSystemId: $system->id,
            title: 'Mesa desde la web',
            startsAt: new DateTimeImmutable('+1 week'),
            durationMinutes: 240,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            minPlayers: 3,
            maxPlayers: 5,
            experienceLevel: ExperienceLevel::cases()[0],
            characterCreation: CharacterCreation::cases()[0],
        ));

        return GameTableModel::query()->findOrFail($created->id);
    }
}
