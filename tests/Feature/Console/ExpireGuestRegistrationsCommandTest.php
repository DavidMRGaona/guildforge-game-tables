<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Console\Commands\ExpireGuestRegistrationsCommand;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

final class ExpireGuestRegistrationsCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is booted after the console application exists, so its commands have to be
        // added to it by hand
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ExpireGuestRegistrationsCommand::class));

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => true,
        ]);
    }

    public function test_the_command_prints_the_counts(): void
    {
        $guest = $this->submit('invitada@example.test');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $this->runCommand('Expired 1 registration(s), closed 0, cleared 0 fingerprint(s), failed 0.');

        $this->assertSame('expired', ParticipantModel::query()->findOrFail($guest->id)->status->value);
    }

    public function test_it_runs_with_automatic_status_updates_off(): void
    {
        config()->set('modules.settings.game-tables.automation.auto_status_enabled', false);
        $guest = $this->submit('invitada@example.test');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $this->runCommand('Expired 1 registration(s), closed 0, cleared 0 fingerprint(s), failed 0.');

        $this->assertSame('expired', ParticipantModel::query()->findOrFail($guest->id)->status->value);
    }

    public function test_the_command_is_scheduled_every_five_minutes_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'game-tables:expire-guest-registrations'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('*/5 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        // A killed run must not block the expiry for long
        $this->assertSame(10, $events[0]->expiresAt);
    }

    private function submit(string $email): ParticipantResponseDTO
    {
        return app(RegistrationServiceInterface::class)->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Invitada',
            email: $email,
        ));
    }

    private function runCommand(string $expectedOutput): void
    {
        $command = $this->artisan('game-tables:expire-guest-registrations');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput($expectedOutput)->assertSuccessful()->run();
    }
}
