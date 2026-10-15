<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Events\GameTableModerationRejected;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Listeners\NotifyUserOnModerationRejected;
use Modules\GameTables\Notifications\ModerationRejectedNotification;
use Tests\Support\Modules\ModuleTestCase;

final class NotifyUserOnModerationRejectedTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_the_rejection_email_links_to_the_edit_page_by_the_table_slug(): void
    {
        Notification::fake();
        $author = UserModel::factory()->create(['email' => 'autora@example.com']);
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => $author->id,
            'title' => 'Mesa rechazada',
            'slug' => 'mesa-rechazada',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            'frontend_creation_status' => 'rejected',
        ]);

        app(NotifyUserOnModerationRejected::class)->handle(new GameTableModerationRejected(
            gameTableId: $table->id,
            title: $table->title,
            createdBy: $author->id,
            reason: 'Falta la sinopsis',
        ));

        Notification::assertSentOnDemand(
            ModerationRejectedNotification::class,
            fn (ModerationRejectedNotification $notification): bool => $notification
                ->toMail(new AnonymousNotifiable)->actionUrl === url('/mesas/mis-mesas/mesa-rechazada/editar'),
        );
    }
}
