<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignDirectionTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignDirectionInterface $direction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->direction = $this->app->make(CampaignDirectionInterface::class);
    }

    private function user(string $name, string $email): UserModel
    {
        return UserModel::factory()->create(['name' => $name, 'email' => $email]);
    }

    private function campaign(UserModel $creator, ?FrontendCreationStatus $status): CampaignModel
    {
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema '.Str::random(5), 'slug' => Str::random(10), 'is_active' => true]);

        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $creator->id,
            'title' => 'Campaña',
            'slug' => Str::random(10),
            'status' => 'recruiting',
            'frontend_creation_status' => $status,
        ]);
    }

    private function gm(CampaignModel $campaign, ?UserModel $user, bool $notify = true): GameMasterModel
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'first_name' => 'GM',
            'last_name' => 'Externo',
            'email' => $user instanceof UserModel ? null : 'external@example.com',
            'role' => GameMasterRole::Main,
            'notify_by_email' => $notify,
            'is_name_public' => true,
        ]);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => $campaign->gameMasters()->count() + 1]);

        return $gm;
    }

    public function test_a_web_creator_directs(): void
    {
        $john = $this->user('John Doe', 'john@example.com');
        $campaign = $this->campaign($john, FrontendCreationStatus::Approved);

        $this->assertTrue($this->direction->isDirector($campaign->id, $john->id));
        $this->assertSame([$john->id], $this->direction->directorUserIds($campaign->id));
        $this->assertFalse($this->direction->isDirector($campaign->id, $this->user('Mary Major', 'mary@example.com')->id));
    }

    public function test_a_panel_creator_does_not_direct(): void
    {
        $john = $this->user('John Doe', 'john@example.com');
        $jane = $this->user('Jane Doe', 'jane@example.com');
        $campaign = $this->campaign($john, null);
        $this->gm($campaign, $jane);

        $this->assertFalse($this->direction->isDirector($campaign->id, $john->id));
        $this->assertTrue($this->direction->isDirector($campaign->id, $jane->id));
        $this->assertSame([$jane->id], $this->direction->directorUserIds($campaign->id));
    }

    public function test_gms_without_account_are_not_directors(): void
    {
        $john = $this->user('John Doe', 'john@example.com');
        $campaign = $this->campaign($john, null);
        $this->gm($campaign, null);

        $this->assertSame([], $this->direction->directorUserIds($campaign->id));
        $this->assertSame([], $this->direction->recipients($campaign->id));
    }

    public function test_recipients_respect_notify_by_email_but_not_for_the_web_creator(): void
    {
        $john = $this->user('John Doe', 'john@example.com');
        $jane = $this->user('Jane Doe', 'jane@example.com');
        $richard = $this->user('Richard Roe', 'richard@example.com');
        $campaign = $this->campaign($john, FrontendCreationStatus::Approved);
        $this->gm($campaign, $john, false);
        $this->gm($campaign, $jane, true);
        $this->gm($campaign, $richard, false);

        $recipients = $this->direction->recipients($campaign->id);

        $this->assertEquals([
            new MailRecipientDTO('john@example.com', 'John Doe'),
            new MailRecipientDTO('jane@example.com', 'Jane Doe'),
        ], $recipients);
        $this->assertCount(3, $this->direction->directorUserIds($campaign->id));
    }

    public function test_anonymized_directors_are_left_out(): void
    {
        $john = $this->user('John Doe', 'john@example.com');
        $jane = $this->user('Jane Doe', 'jane@example.com');
        $campaign = $this->campaign($john, FrontendCreationStatus::Approved);
        $this->gm($campaign, $jane);
        $jane->forceFill(['anonymized_at' => now()])->save();

        $this->assertSame([$john->id], $this->direction->directorUserIds($campaign->id));
        $this->assertFalse($this->direction->isDirector($campaign->id, $jane->id));
        $this->assertSame(['john@example.com'], array_map(fn (MailRecipientDTO $r): string => $r->email, $this->direction->recipients($campaign->id)));
        $this->assertSame([], $this->direction->campaignIdsDirectedBy($jane->id));
    }

    public function test_campaign_ids_directed_by(): void
    {
        $john = $this->user('John Doe', 'john@example.com');
        $jane = $this->user('Jane Doe', 'jane@example.com');
        $web = $this->campaign($john, FrontendCreationStatus::Approved);
        $asGm = $this->campaign($jane, null);
        $this->gm($asGm, $john);
        $panelOnly = $this->campaign($john, null);
        $both = $this->campaign($john, FrontendCreationStatus::Draft);
        $this->gm($both, $john);

        $ids = $this->direction->campaignIdsDirectedBy($john->id);

        $this->assertEqualsCanonicalizing([$web->id, $asGm->id, $both->id], $ids);
        $this->assertNotContains($panelOnly->id, $ids);
    }
}
