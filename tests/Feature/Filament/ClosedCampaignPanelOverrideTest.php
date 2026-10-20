<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The staff is not bound by the closed-campaign rule in the panel, and cancelling a roster row
 * there is an absence.
 */
final class ClosedCampaignPanelOverrideTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $session;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        Notification::fake();

        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $owner = UserModel::factory()->create();
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $owner->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba',
            'status' => 'on_hold',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => false,
            'roster_access' => 'anyone',
        ]);
        $this->session = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'campaign_id' => $this->campaign->id,
            'created_by' => $owner->id,
            'title' => 'Sesión de prueba',
            'slug' => 'sesion-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
        ]);
    }

    public function test_staff_can_register_an_outsider_and_a_guest_in_a_closed_session(): void
    {
        $outsider = UserModel::factory()->create();

        $this->participants()
            ->callTableAction('create', data: ['participant_type' => 'user', 'user_id' => $outsider->id, 'role' => 'player'])
            ->assertHasNoTableActionErrors();
        $this->participants()
            ->callTableAction('create', data: [
                'participant_type' => 'external',
                'first_name' => 'Richard',
                'email' => 'richard@example.com',
                'role' => 'player',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $this->session->id)->where('user_id', $outsider->id)->count());
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $this->session->id)->where('email', 'richard@example.com')->count());
    }

    public function test_a_panel_cancellation_of_a_roster_row_is_an_absence(): void
    {
        $member = UserModel::factory()->create();
        $this->app->make(CampaignRosterRepositoryInterface::class)
            ->add(new CampaignId($this->campaign->id), $member->id, now()->toDateTimeImmutable());
        $row = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->session->id,
            'user_id' => $member->id,
            'role' => 'player',
            'status' => 'confirmed',
            'from_roster' => true,
            'confirmed_at' => now(),
        ]);

        $this->participants()->callTableAction('cancelRegistration', $row)->assertHasNoTableActionErrors();

        $row->refresh();
        $this->assertSame('cancelled', $row->status->value);
        $this->assertSame(ParticipantCancellationReason::SessionAbsence, $row->cancellation_reason);
    }

    private function participants(): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $this->session, 'pageClass' => EditGameTable::class]);
    }
}
