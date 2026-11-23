<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Linking;

use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Filament\Linking\Sources\CampaignLinkableRecordSource;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Campaigns as linkable records (core contract), read through the core's lookup as any consumer
 * does: what their page shows to everybody, unpublished ones for who views campaigns in the
 * panel, and the running campaigns each person organizes.
 */
final class CampaignLinkableRecordSourceTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private LinkableRecordLookupInterface $lookup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
        $this->lookup = $this->app->make(LinkableRecordLookupInterface::class);
    }

    public function test_campaigns_are_offered_with_their_label(): void
    {
        $this->assertSame('Campaña', $this->lookup->types()[CampaignLinkableRecordSource::TYPE] ?? null);
    }

    public function test_a_visitor_finds_published_campaigns_only(): void
    {
        $published = $this->campaign(['title' => 'La maldición de Strahd']);
        $hidden = $this->campaign(['is_published' => false]);

        $record = $this->lookup->find('campaign', $published->id, LinkableRecordSourceInterface::VISITOR);

        $this->assertSame('La maldición de Strahd', $record?->title);
        $this->assertNull($record->date);
        $this->assertNull($record->panelUrl);
        $this->assertNull($this->lookup->find('campaign', $hidden->id, LinkableRecordSourceInterface::VISITOR));
        $this->assertSame([$published->id], array_keys($this->lookup->findMany('campaign', [$hidden->id, $published->id], LinkableRecordSourceInterface::VISITOR)));
    }

    public function test_who_views_campaigns_in_the_panel_finds_unpublished_ones_and_gets_the_edit_page_when_allowed(): void
    {
        $this->registerOnAdminPanel([CampaignResource::class]);
        $hidden = $this->campaign(['is_published' => false]);
        $viewer = $this->editorWithPermissions(['gametables:campaigns.view']);
        $editor = $this->editorWithPermissions(['gametables:campaigns.view', 'gametables:campaigns.update']);

        $seen = $this->lookup->find('campaign', $hidden->id, $viewer->id);

        $this->assertSame($hidden->id, $seen?->id);
        $this->assertNull($seen->panelUrl);
        $this->assertStringEndsWith('/edit', (string) $this->lookup->find('campaign', $hidden->id, $editor->id)?->panelUrl);
    }

    public function test_a_search_goes_from_the_latest_created_and_narrows_by_title(): void
    {
        $older = $this->campaign(['title' => 'Primera campaña', 'created_at' => now()->subMonth()]);
        $newer = $this->campaign(['title' => 'Segunda campaña', 'created_at' => now()->subDay()]);

        $this->assertSame([$newer->id, $older->id], $this->ids($this->lookup->search('campaign', '', LinkableRecordSourceInterface::VISITOR)));
        $this->assertSame([$older->id], $this->ids($this->lookup->search('campaign', 'primera', LinkableRecordSourceInterface::VISITOR)));
    }

    public function test_the_campaigns_a_person_organizes_are_running_ones_they_created_or_direct(): void
    {
        $person = UserModel::factory()->create();
        $created = $this->campaign(['created_by' => $person->id, 'created_at' => now()->subDays(2)]);
        $directed = $this->campaign(['created_at' => now()->subDay()]);
        $directed->gameMasters()->attach($this->gameMaster($person)->id, ['sort_order' => 1]);
        $this->campaign(['created_by' => $person->id, 'status' => 'completed']);
        $this->campaign(['created_by' => $person->id, 'status' => 'cancelled']);
        $this->campaign(['created_by' => $person->id, 'is_published' => false]);
        $this->campaign();

        $this->assertSame([$directed->id, $created->id], $this->ids($this->lookup->organizedBy('campaign', $person)));
    }

    /**
     * @param  list<LinkableRecordDTO>  $records
     * @return list<string>
     */
    private function ids(array $records): array
    {
        return array_map(static fn (LinkableRecordDTO $record): string => $record->id, $records);
    }

    private function gameMaster(UserModel $user): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Nombre',
            'last_name' => 'Apellido',
            'role' => GameMasterRole::Main,
            'notify_by_email' => false,
            'is_name_public' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        $campaign = CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
        ], array_diff_key($attributes, ['created_at' => true])));

        if (isset($attributes['created_at'])) {
            $campaign->forceFill(['created_at' => $attributes['created_at']])->saveQuietly();
        }

        return $campaign;
    }
}
