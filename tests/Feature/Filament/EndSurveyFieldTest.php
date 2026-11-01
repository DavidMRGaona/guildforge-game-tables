<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveyOptionDTO;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Surveys\CampaignSessionSurveySubjectSource;
use Modules\GameTables\Infrastructure\Surveys\CampaignSurveySubjectSource;
use Modules\GameTables\Infrastructure\Surveys\GameTableSurveySubjectSource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Surveys\FakeSubjectEndSurveyChoice;

/**
 * The table and campaign forms carry the core's "survey when it ends" field, without knowing
 * the surveys module: a table of a campaign is a campaign session, with its own subject type.
 */
final class EndSurveyFieldTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    private string $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class]);
        $this->adminId = (string) $this->actingAsPanelAdmin()->id;
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_without_the_surveys_module_the_forms_have_no_end_survey(): void
    {
        Livewire::test(EditGameTable::class, ['record' => $this->gameTable()->getRouteKey()])
            ->assertFormFieldIsHidden('end_survey');
        Livewire::test(EditCampaign::class, ['record' => $this->campaign()->getRouteKey()])
            ->assertFormFieldIsHidden('end_survey');
    }

    public function test_a_table_chooses_its_end_survey(): void
    {
        $fake = $this->fake();
        $table = $this->gameTable();

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormFieldIsVisible('end_survey')
            ->fillForm(['end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([[GameTableSurveySubjectSource::TYPE, $table->id, 'none']], $this->chosen($fake));
    }

    public function test_a_table_of_a_campaign_chooses_it_as_a_campaign_session(): void
    {
        $fake = $this->fake();
        $table = $this->gameTable($this->campaign());

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([[CampaignSessionSurveySubjectSource::TYPE, $table->id, 'none']], $this->chosen($fake));
        $this->assertContains(CampaignSessionSurveySubjectSource::TYPE, array_column($fake->asked, 'sourceType'));
    }

    public function test_a_campaign_chooses_its_end_survey(): void
    {
        $fake = $this->fake();
        $campaign = $this->campaign();

        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->assertFormFieldIsVisible('end_survey')
            ->fillForm(['end_survey' => 'none'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([[CampaignSurveySubjectSource::TYPE, $campaign->id, 'none']], $this->chosen($fake));
    }

    private function fake(): FakeSubjectEndSurveyChoice
    {
        $fake = new FakeSubjectEndSurveyChoice(new EndSurveyChoiceDTO(
            options: [new EndSurveyOptionDTO('type-default', 'Plantilla del tipo: Valoración'), new EndSurveyOptionDTO('none', 'Ninguna')],
            selected: 'type-default',
        ));
        $this->app->instance(SubjectEndSurveyChoiceInterface::class, $fake);

        return $fake;
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function chosen(FakeSubjectEndSurveyChoice $fake): array
    {
        return array_map(static fn (array $call): array => [$call['sourceType'], $call['subjectId'], $call['selection']->value], $fake->chosen);
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->adminId,
            'title' => 'La llamada',
            'slug' => 'la-llamada-'.Str::random(6),
            'status' => 'recruiting',
        ]);
    }

    private function gameTable(?CampaignModel $campaign = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'campaign_id' => $campaign?->id,
            'created_by' => $this->adminId,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::random(6),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => $campaign instanceof CampaignModel ? 'campaign_session' : 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ]);
    }
}
