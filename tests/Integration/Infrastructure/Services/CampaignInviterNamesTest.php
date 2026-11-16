<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignInviterNamesInterface;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * An invitation names whoever sent it when it is read: a director of the campaign by their public name
 * only when they chose to show it (otherwise by the title the campaign page shows), anybody else by the
 * account's public name or account name, and an account that is gone by the name stored when inviting.
 */
final class CampaignInviterNamesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', []);

        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true, 'game_master_title' => 'Narrador',
        ]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'accepts_new_players' => true,
            'max_players' => 3,
            'roster_access' => 'anyone',
        ]);
    }

    public function test_a_director_who_shows_the_name_goes_by_the_public_name(): void
    {
        $ana = $this->account('Ana Account', 'Ana Reader');
        $this->direct($ana, public: true);

        $this->assertSame('Ana Reader', $this->names()->nameOf($this->invitation($ana, 'Stored Name')));
        $this->assertSame('Ana Reader', $this->names()->forNewInvitation($this->campaign->id, (string) $ana->id));
    }

    public function test_a_director_who_hides_the_name_goes_by_the_title(): void
    {
        $ana = $this->account('Ana Account', 'Ana Reader');
        $this->direct($ana, public: false);

        $this->assertSame('Narrador', $this->names()->nameOf($this->invitation($ana, 'Ana Reader')));
        $this->assertSame('Narrador', $this->names()->forNewInvitation($this->campaign->id, (string) $ana->id));
    }

    public function test_anybody_else_goes_by_the_public_name_or_else_the_account_name(): void
    {
        $ana = $this->account('Ana Account', 'Ana Reader');
        $bea = $this->account('Bea Account', null);

        $names = $this->names()->namesOf([
            $first = $this->invitation($ana, 'Old Name'),
            $second = $this->invitation($bea, 'Old Name'),
        ]);

        $this->assertSame('Ana Reader', $names[$first->id->value]);
        $this->assertSame('Bea Account', $names[$second->id->value]);
    }

    public function test_an_account_that_is_gone_keeps_the_stored_name(): void
    {
        $ana = $this->account('Ana Account', 'Ana Reader');
        $invitation = $this->invitation($ana, 'Stored Name');
        $ana->delete();

        $this->assertSame('Stored Name', $this->names()->nameOf($invitation));
        $this->assertSame([], $this->names()->namesOf([]));
    }

    private function names(): CampaignInviterNamesInterface
    {
        return $this->app->make(CampaignInviterNamesInterface::class);
    }

    private function account(string $name, ?string $publicName): UserModel
    {
        return UserModel::factory()->create(['name' => $name, 'display_name' => $publicName]);
    }

    private function direct(UserModel $account, bool $public): void
    {
        $director = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $account->id,
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => $public,
        ]);
        $this->campaign->gameMasters()->attach($director->id, ['sort_order' => 1]);
    }

    private function invitation(UserModel $inviter, string $storedName): CampaignInvitation
    {
        return new CampaignInvitation(
            id: CampaignInvitationId::generate(),
            campaignId: new CampaignId($this->campaign->id),
            email: 'guest@example.com',
            invitedBy: (string) $inviter->id,
            inviterName: $storedName,
            tokenHash: hash('sha256', Str::random(64)),
            expiresAt: new DateTimeImmutable('+14 days'),
            sendCount: 1,
            lastSentAt: new DateTimeImmutable,
        );
    }
}
