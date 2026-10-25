<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\TableSeatsDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Http\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The table page shows the moment registrations really close, computed by the server.
 */
final class EffectiveClosingDateExposureTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_without_a_configured_date_the_page_gets_the_start(): void
    {
        $this->assertSame('2030-01-10 18:00:00', $this->exposed([]));
    }

    public function test_a_date_before_the_start_is_exposed_as_it_is(): void
    {
        $this->assertSame(
            '2030-01-09 12:00:00',
            $this->exposed(['registration_closes_at' => '2030-01-09 12:00:00']),
        );
    }

    public function test_a_date_after_the_start_is_exposed_as_the_start(): void
    {
        $this->assertSame(
            '2030-01-10 18:00:00',
            $this->exposed(['registration_closes_at' => '2030-01-12 12:00:00']),
        );
    }

    public function test_an_in_play_table_exposes_its_end(): void
    {
        $this->assertSame(
            '2030-01-10 22:00:00',
            $this->exposed(['accepts_registrations_in_progress' => true, 'registration_closes_at' => '2030-01-12 12:00:00']),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function exposed(array $attributes): string
    {
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $table = GameTableModel::create($attributes + [
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => '2030-01-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'is_published' => true,
        ]);

        $dto = app(GameTableQueryServiceInterface::class)->findPublished($table->id);
        $this->assertNotNull($dto);

        $seats = new TableSeatsDTO(0, 0, 5, 0, 0);

        $value = (new GameTableResource($dto, $seats))->toArray(request())['effectiveRegistrationClosesAt'];
        $this->assertIsString($value);

        // Stored and read in the application's timezone
        return (new \DateTimeImmutable($value))
            ->setTimezone(new \DateTimeZone((string) config('app.timezone')))
            ->format('Y-m-d H:i:s');
    }
}
