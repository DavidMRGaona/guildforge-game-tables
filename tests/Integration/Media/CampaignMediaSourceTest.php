<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Media\CampaignMediaSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Media\ColumnSourceContractTests;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Campaign images in the panel media library: the shared contract of column sources plus what is
 * specific to campaigns.
 */
final class CampaignMediaSourceTest extends ModuleTestCase
{
    use ColumnSourceContractTests;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->registerOnAdminPanel([CampaignResource::class]);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    protected function source(): MediaUsageSourceInterface
    {
        return $this->app->make(CampaignMediaSource::class);
    }

    protected function makeRecord(?string $path): Model
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
            'image_public_id' => $path,
        ]);
    }

    protected function updatePermission(): string
    {
        return 'gametables:campaigns.update';
    }

    public function test_declares_its_type_label_and_folders(): void
    {
        $this->assertSame('gametables.campaigns', $this->source()->type());
        $this->assertSame('Campañas', $this->source()->label());
        $this->assertSame(['campaigns/'], $this->source()->folders());
    }

    public function test_usage_carries_the_campaign_title_and_role(): void
    {
        $campaign = $this->makeRecord('campaigns/2026/11/a.jpg');

        $usage = $this->source()->usagesFor((string) $campaign->getKey())[0];

        $this->assertSame('Campaña de ejemplo', $usage->title);
        $this->assertSame('Imagen', $usage->role);
    }

    public function test_edit_url_is_decided_for_the_given_user(): void
    {
        $campaign = $this->makeRecord('campaigns/a.jpg');
        $with = $this->editorWithPermissions(['gametables:campaigns.update']);
        $without = $this->editorWithPermissions([]);

        $this->actingAs($with);
        $this->assertNull($this->source()->editUrl((string) $campaign->getKey(), $without));

        $this->actingAs($without);
        $url = $this->source()->editUrl((string) $campaign->getKey(), $with);
        $this->assertStringEndsWith('/edit', (string) $url);
        $this->assertStringContainsString((string) $campaign->getKey(), (string) $url);
    }
}
