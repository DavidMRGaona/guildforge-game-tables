<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Listeners\SendGuestEmailConfirmationRequest;
use Modules\GameTables\Notifications\GuestEmailConfirmationRequest;
use Tests\Support\Modules\ModuleTestCase;

final class SendGuestEmailConfirmationRequestTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Make the module routes, added after boot, resolvable by name
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_the_email_carries_the_confirmation_link(): void
    {
        Notification::fake();
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $table = GameTableModel::create([
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
            'language' => 'es',
            'is_published' => true,
        ]);
        $token = str_repeat('c', 64);

        app(SendGuestEmailConfirmationRequest::class)->handle(new GuestConfirmationRequested(
            participantId: (string) Str::uuid(),
            gameTableId: $table->id,
            email: 'invitada@example.test',
            firstName: 'Invitada',
            confirmationToken: $token,
            holdsPlace: true,
            deadline: '02/11/2026 12:00',
        ));

        Notification::assertSentOnDemand(
            GuestEmailConfirmationRequest::class,
            fn (GuestEmailConfirmationRequest $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'invitada@example.test'
                && $notification->toMail($notifiable)->actionUrl === route('gametables.confirm-guest', $token),
        );
        $this->assertStringEndsWith('/mesas/confirmar/'.$token, route('gametables.confirm-guest', $token));
    }
}
