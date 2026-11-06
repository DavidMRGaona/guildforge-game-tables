<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Search;

use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Search\CampaignSearchSource;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Campaigns in the site search: the campaigns of the public listing, kept up to date on save. The test
 * queue is "sync", so the after-commit refresh runs inside the test.
 */
final class CampaignSearchSourceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignSearchSource $source;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->source = $this->app->make(CampaignSearchSource::class);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_the_registry_offers_campaigns_with_their_labels(): void
    {
        $sources = array_values(array_filter(
            $this->app->make(SearchSourceRegistryInterface::class)->indexedSources(),
            static fn ($source): bool => $source->type() === 'gametables.campaigns',
        ));

        $this->assertCount(1, $sources);
        $this->assertInstanceOf(CampaignSearchSource::class, $sources[0]);
        $this->assertSame('Campañas', $this->source->label());
        $this->assertSame('Campaña', $this->source->itemLabel());
        $this->assertSame('Terminada', $this->source->pastLabel());
    }

    public function test_cancelled_campaign_has_no_document(): void
    {
        $hidden = [
            $this->campaign(['status' => 'cancelled']),
            $this->campaign(['is_published' => false]),
            $this->campaign(['is_published' => false, 'frontend_creation_status' => 'pending_review']),
        ];

        foreach ($hidden as $campaign) {
            $this->assertNull($this->source->document($campaign->id));
        }

        $this->assertSame([], $this->documentIds());
    }

    public function test_every_listed_campaign_has_a_document(): void
    {
        $listed = [
            $this->campaign(['status' => 'recruiting']),
            $this->campaign(['status' => 'active']),
            $this->campaign(['status' => 'on_hold']),
            $this->campaign(['status' => 'completed']),
        ];
        $this->campaign(['status' => 'cancelled']);

        $this->assertEqualsCanonicalizing(
            array_map(static fn (CampaignModel $campaign): string => $campaign->id, $listed),
            $this->documentIds(),
        );
    }

    public function test_completed_campaign_is_finished(): void
    {
        $this->assertTrue($this->source->document($this->campaign(['status' => 'completed'])->id)?->finished);
        $this->assertFalse($this->source->document($this->campaign(['status' => 'active'])->id)?->finished);
    }

    public function test_campaign_document_has_system_and_description(): void
    {
        $campaign = $this->campaign([
            'title' => 'La marca del cuervo',
            'description' => '<p>Una saga &amp; sus <em>misterios</em></p><p>en el norte</p>',
            'image_public_id' => 'gametables/cuervo',
        ]);

        $document = $this->source->document($campaign->id);

        $this->assertInstanceOf(SearchDocumentDTO::class, $document);
        $this->assertSame($campaign->id, $document->id);
        $this->assertSame('La marca del cuervo', $document->title);
        $this->assertSame('Una saga & sus misterios en el norte', $document->body);
        $this->assertSame('Sistema de ejemplo', $document->keywords);
        $this->assertSame('Sistema de ejemplo', $document->meta);
        $this->assertSame('gametables/cuervo', $document->imagePublicId);
        $this->assertSame(route('campaigns.show', $campaign->slug, false), $document->url);
        $this->assertStringStartsWith('/', $document->url);
        $this->assertNull($document->startsAt);
        $this->assertNull($document->endsAt);
    }

    public function test_moderation_notes_are_never_indexed(): void
    {
        $campaign = $this->campaign(['moderation_notes' => 'Motivo de moderación secreto']);

        $document = $this->source->document($campaign->id);
        $stored = $this->stored($campaign->id);

        $this->assertNotNull($document);
        $this->assertNotNull($stored);
        foreach ([json_encode(get_object_vars($document), JSON_UNESCAPED_UNICODE), json_encode($stored->toArray(), JSON_UNESCAPED_UNICODE)] as $indexed) {
            $this->assertIsString($indexed);
            $this->assertStringNotContainsString('Motivo de moderación', $indexed);
        }
    }

    public function test_ids_that_are_not_uuids_or_unknown_have_no_document(): void
    {
        $this->assertNull($this->source->document('not-a-uuid'));
        $this->assertNull($this->source->document((string) Str::uuid()));
    }

    public function test_cancelling_a_campaign_forgets_it_on_save(): void
    {
        $campaign = $this->campaign(['title' => 'Campaña indexada']);
        $this->assertSame('Campaña indexada', $this->stored($campaign->id)?->title);

        $campaign->update(['status' => 'cancelled']);

        $this->assertNull($this->stored($campaign->id));
    }

    public function test_deleting_a_campaign_forgets_its_document(): void
    {
        $campaign = $this->campaign();
        $this->assertNotNull($this->stored($campaign->id));

        $campaign->delete();

        $this->assertNull($this->stored($campaign->id));
    }

    public function test_deleting_the_creator_for_good_forgets_the_campaigns_document(): void
    {
        // The campaign goes with its creator through the foreign key, without model events
        $creator = UserModel::factory()->create();
        $campaign = $this->campaign(['created_by' => $creator->id]);
        $other = $this->campaign();

        $creator->forceDelete();

        $this->assertNull($this->stored($campaign->id));
        $this->assertNotNull($this->stored($other->id));
    }

    public function test_a_soft_deleted_creator_keeps_the_campaigns_document(): void
    {
        $creator = UserModel::factory()->create();
        $campaign = $this->campaign(['created_by' => $creator->id]);

        $creator->delete();

        $this->assertNotNull($this->stored($campaign->id));
    }

    public function test_renaming_the_game_system_refreshes_its_campaigns(): void
    {
        $campaign = $this->campaign();

        $this->system->update(['name' => 'Sistema renombrado']);

        $this->assertSame('Sistema renombrado', $this->stored($campaign->id)?->keywords);
    }

    /**
     * @return list<string>
     */
    private function documentIds(): array
    {
        $ids = [];

        foreach ($this->source->documents() as $document) {
            $ids[] = $document->id;
        }

        return $ids;
    }

    private function stored(string $id): ?SearchDocumentModel
    {
        return SearchDocumentModel::query()->where('type', 'gametables.campaigns')->where('source_id', $id)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
        ], $attributes));
    }
}
