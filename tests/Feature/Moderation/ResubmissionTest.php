<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Moderation;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Notifications\CampaignModerationSubmittedNotification;
use Modules\GameTables\Notifications\ModerationSubmittedNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Only drafts and rejected items go to review, so repeated requests send one moderation email.
 */
final class ResubmissionTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $creator;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        Notification::fake();
        UserModel::factory()->admin()->create();
        config()->set('modules.settings.game-tables.frontend_creation.publication.mode', 'approval');

        $this->creator = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
    }

    public function test_a_campaign_sent_twice_reaches_the_moderators_once(): void
    {
        $campaign = $this->campaign('draft');

        $this->actingAs($this->creator)->post(route('campaigns.submit-review', $campaign->id))->assertRedirect();
        $this->actingAs($this->creator)->post(route('campaigns.submit-review', $campaign->id))->assertForbidden();

        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);
        Notification::assertSentOnDemandTimes(CampaignModerationSubmittedNotification::class, 1);
    }

    public function test_a_rejected_campaign_can_be_sent_again(): void
    {
        $campaign = $this->campaign('rejected');

        $this->actingAs($this->creator)->post(route('campaigns.submit-review', $campaign->id))->assertRedirect();

        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);
        Notification::assertSentOnDemandTimes(CampaignModerationSubmittedNotification::class, 1);
    }

    public function test_an_approved_campaign_cannot_go_back_to_review(): void
    {
        $campaign = $this->campaign('approved', published: true);

        $this->actingAs($this->creator)->post(route('campaigns.submit-review', $campaign->id))->assertForbidden();

        $campaign->refresh();
        $this->assertSame('approved', $campaign->frontend_creation_status?->value);
        $this->assertTrue($campaign->is_published);
        Notification::assertNothingSent();
    }

    public function test_a_table_sent_twice_reaches_the_moderators_once(): void
    {
        $table = $this->table('draft');

        $this->actingAs($this->creator)->post(route('gametables.submit-review', $table->slug))->assertRedirect();
        $this->actingAs($this->creator)->post(route('gametables.submit-review', $table->slug))->assertForbidden();

        $this->assertSame('pending_review', $table->refresh()->frontend_creation_status?->value);
        Notification::assertSentOnDemandTimes(ModerationSubmittedNotification::class, 1);
    }

    private function campaign(string $creationStatus, bool $published = false): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => 'La llamada',
            'slug' => 'la-llamada',
            'status' => 'recruiting',
            'is_published' => $published,
            'frontend_creation_status' => $creationStatus,
        ]);
    }

    private function table(string $creationStatus): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => 'Mesa de la web',
            'slug' => 'mesa-de-la-web',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'frontend_creation_status' => $creationStatus,
        ]);
    }
}
