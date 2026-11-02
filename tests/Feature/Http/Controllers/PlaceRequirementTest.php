<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Where a table or campaign is played must be told: the location in person, the link online, and
 * at least one of the two when hybrid. The web form marks the same fields the server requires.
 */
final class PlaceRequirementTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        app()->setLocale('es');
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
            ],
        ]);

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{string, array<string, string>, string|null}>
     */
    public static function places(): array
    {
        return [
            'in person without location' => ['in_person', [], 'location'],
            'in person with location' => ['in_person', ['location' => 'Local de la asociación'], null],
            'in person with only a link' => ['in_person', ['online_url' => 'https://example.com/mesa'], 'location'],
            'online without link' => ['online', [], 'online_url'],
            'online with link' => ['online', ['online_url' => 'https://example.com/mesa'], null],
            'hybrid without either' => ['hybrid', [], 'location'],
            'hybrid with only a location' => ['hybrid', ['location' => 'Local de la asociación'], null],
            'hybrid with only a link' => ['hybrid', ['online_url' => 'https://example.com/mesa'], null],
        ];
    }

    /**
     * @param  array<string, string>  $place
     */
    #[DataProvider('places')]
    public function test_a_web_table_tells_where_it_is_played(string $format, array $place, ?string $missing): void
    {
        $response = $this->actingAs($this->author)
            ->post('/mesas/crear', [...$this->tableData(), 'table_format' => $format, ...$place]);

        if ($missing === null) {
            $response->assertSessionHasNoErrors();
            $this->assertSame(1, GameTableModel::query()->count());
        } else {
            $response->assertSessionHasErrors([$missing => $this->messageFor($format, $missing)]);
            $this->assertSame(0, GameTableModel::query()->count());
        }
    }

    /**
     * @param  array<string, string>  $place
     */
    #[DataProvider('places')]
    public function test_a_web_campaign_tells_where_it_is_played(string $format, array $place, ?string $missing): void
    {
        $response = $this->actingAs($this->author)
            ->post('/campanas/crear', [...$this->campaignData(), 'table_format' => $format, ...$place]);

        if ($missing === null) {
            $response->assertSessionHasNoErrors();
            $this->assertSame(1, CampaignModel::query()->count());
        } else {
            $response->assertSessionHasErrors([$missing => $this->messageFor($format, $missing)]);
            $this->assertSame(0, CampaignModel::query()->count());
        }
    }

    public function test_the_format_decides_which_field_is_required(): void
    {
        $this->assertTrue(TableFormat::needsLocation(TableFormat::InPerson, 'https://example.com'));
        $this->assertFalse(TableFormat::needsLocation(TableFormat::Online, null));
        $this->assertTrue(TableFormat::needsLocation(TableFormat::Hybrid, '  '));
        $this->assertFalse(TableFormat::needsLocation(TableFormat::Hybrid, 'https://example.com'));
        // A campaign saved before the format was stored shows both fields
        $this->assertTrue(TableFormat::needsLocation(null, null));
        $this->assertFalse(TableFormat::needsLocation(null, 'https://example.com'));

        $this->assertTrue(TableFormat::needsOnlineUrl(TableFormat::Online));
        $this->assertFalse(TableFormat::needsOnlineUrl(TableFormat::Hybrid));
        $this->assertFalse(TableFormat::needsOnlineUrl(null));
    }

    private function messageFor(string $format, string $field): string
    {
        if ($format === 'hybrid') {
            return __('game-tables::messages.validation.location_or_online_url_required');
        }

        return $field === 'location'
            ? 'El campo ubicación es obligatorio.'
            : 'El campo enlace de la sesión online es obligatorio.';
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => 'Mesa nueva',
            'starts_at' => now()->addWeeks(2)->toIso8601String(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignData(): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => 'Campaña nueva',
            'frequency' => 'weekly',
            'min_players' => 2,
            'max_players' => 5,
        ];
    }
}
