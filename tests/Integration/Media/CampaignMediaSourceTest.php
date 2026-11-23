<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Media\CampaignMediaSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use PHPUnit\Framework\Attributes\DataProvider;
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
        return $this->makeCampaign(['image_public_id' => $path]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCampaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
            'image_public_id' => 'campaigns/2026/11/a.jpg',
            ...$attributes,
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

    public function test_shows_its_previews_in_the_media_library(): void
    {
        $this->assertInstanceOf(ShowsMediaPreviews::class, $this->source());
    }

    public function test_the_image_of_a_published_campaign_is_shown_with_its_title(): void
    {
        $campaign = $this->makeCampaign();

        $usage = $this->source()->usagesFor((string) $campaign->getKey())[0];

        $this->assertFalse($usage->hidden);
        $this->assertSame('Campaña de ejemplo', $usage->title);
    }

    public function test_a_published_campaign_stays_shown_once_cancelled_or_completed(): void
    {
        // Its page still opens, image included, so the library may show it too
        foreach (['cancelled', 'completed'] as $status) {
            $campaign = $this->makeCampaign(['status' => $status]);

            $this->assertFalse($this->source()->usagesFor((string) $campaign->getKey())[0]->hidden, $status);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('unpublishedCampaigns')]
    public function test_the_image_of_an_unpublished_campaign_is_hidden_under_a_neutral_title(array $attributes): void
    {
        $campaign = $this->makeCampaign(['title' => 'Sorpresa de fin de año', 'is_published' => false, ...$attributes]);

        $usage = $this->source()->usagesFor((string) $campaign->getKey())[0];

        $this->assertTrue($usage->hidden);
        $this->assertSame('Campaña sin publicar', $usage->title);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unpublishedCampaigns(): array
    {
        return [
            'not published from the panel' => [[]],
            'draft from the site' => [['frontend_creation_status' => 'draft']],
            'pending moderation' => [['frontend_creation_status' => 'pending_review']],
            'rejected' => [['frontend_creation_status' => 'rejected']],
        ];
    }

    public function test_publishing_or_unpublishing_a_campaign_refreshes_its_usages(): void
    {
        $campaign = $this->makeCampaign(['is_published' => false]);
        $indexer = $this->spy(MediaIndexerInterface::class);

        $campaign->update(['is_published' => true]);
        $campaign->update(['is_published' => false]);

        $indexer->shouldHaveReceived('refresh')->with(CampaignMediaSource::TYPE, (string) $campaign->getKey())->twice();
    }
}
