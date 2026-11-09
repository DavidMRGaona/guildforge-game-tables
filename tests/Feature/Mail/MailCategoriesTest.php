<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Mail;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class MailCategoriesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', []);
        app()->setLocale('es');
    }

    /**
     * @return array<string, array{string, bool, string, string, string|null}>
     */
    public static function categories(): array
    {
        return [
            'sign-ups' => [
                GameTablesMailCategories::SIGN_UPS,
                true,
                'Mis inscripciones',
                'Confirmaciones de tus inscripciones y bajas en partidas y campañas, plazas que se liberan para ti, ausencias y cancelaciones.',
                'Confirman algo que has hecho o te cambian los planes.',
            ],
            'reminders' => [
                GameTablesMailCategories::REMINDERS,
                false,
                'Recordatorios de partidas',
                'Un aviso antes de cada partida en la que tienes plaza.',
                null,
            ],
            'campaign news' => [
                GameTablesMailCategories::CAMPAIGN_NEWS,
                false,
                'Novedades de mis campañas',
                'Invitaciones a campañas y nuevas sesiones de las campañas en las que juegas.',
                null,
            ],
            'direction' => [
                GameTablesMailCategories::DIRECTION,
                false,
                'Avisos como director de juego',
                'Inscripciones, bajas y lista de espera de tus partidas; solicitudes, invitaciones aceptadas y salidas de tus campañas.',
                null,
            ],
            'proposals' => [
                GameTablesMailCategories::PROPOSALS,
                true,
                'Revisión de mis propuestas',
                'Si la mesa o la campaña que has propuesto en la web se publica o no.',
                'Te dicen si lo que has propuesto se publica.',
            ],
            'moderation' => [
                GameTablesMailCategories::MODERATION,
                false,
                'Mesas y campañas por revisar',
                'Las mesas y campañas propuestas en la web que esperan moderación.',
                null,
            ],
        ];
    }

    #[DataProvider('categories')]
    public function test_each_category_is_registered_with_its_spanish_texts(string $key, bool $mandatory, string $label, string $description, ?string $reason): void
    {
        $category = $this->registry()->find($key);

        $this->assertInstanceOf(MailCategoryDTO::class, $category);
        $this->assertSame($mandatory, $category->isMandatory());
        $this->assertSame('Mesas de rol', __($category->section));
        $this->assertSame($label, __($category->label));
        $this->assertSame($description, __($category->description));
        $this->assertSame($reason, $category->mandatoryReason === null ? null : __($category->mandatoryReason));
    }

    #[DataProvider('categories')]
    public function test_each_category_has_english_texts(string $key): void
    {
        app()->setLocale('en');
        $category = $this->registry()->find($key);
        $this->assertInstanceOf(MailCategoryDTO::class, $category);

        foreach ([$category->section, $category->label, $category->description, $category->mandatoryReason] as $text) {
            if ($text !== null) {
                $this->assertNotSame($text, __($text), "Missing English text for {$text}");
            }
        }
    }

    public function test_the_module_registers_only_its_six_categories(): void
    {
        $keys = array_values(array_filter(
            array_map(fn (MailCategoryDTO $category): string => $category->key, $this->registry()->all()),
            fn (string $key): bool => str_starts_with($key, 'game-tables.'),
        ));
        sort($keys);

        $expected = array_map(fn (array $row): string => $row[0], self::categories());
        sort($expected);

        $this->assertSame($expected, $keys);
    }

    public function test_everyone_sees_the_sign_ups_reminders_and_campaign_news(): void
    {
        foreach ([GameTablesMailCategories::SIGN_UPS, GameTablesMailCategories::REMINDERS, GameTablesMailCategories::CAMPAIGN_NEWS] as $key) {
            $category = $this->registry()->find($key);
            $this->assertInstanceOf(MailCategoryDTO::class, $category);
            $this->assertNull($category->audience, "{$key} should be listed for everyone");
        }
    }

    public function test_the_direction_category_is_seen_only_by_whoever_directs_something(): void
    {
        $player = UserModel::factory()->create();
        $gameMaster = UserModel::factory()->create();
        $webCreator = UserModel::factory()->create();

        GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $gameMaster->id,
            'first_name' => 'GM',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $this->campaign($webCreator, FrontendCreationStatus::Approved);

        $this->assertFalse($this->sees(GameTablesMailCategories::DIRECTION, $player));
        $this->assertTrue($this->sees(GameTablesMailCategories::DIRECTION, $gameMaster));
        $this->assertTrue($this->sees(GameTablesMailCategories::DIRECTION, $webCreator));
    }

    public function test_a_campaign_created_in_the_panel_does_not_make_its_author_a_director(): void
    {
        $author = UserModel::factory()->create();
        $this->campaign($author, null);

        $this->assertFalse($this->sees(GameTablesMailCategories::DIRECTION, $author));
    }

    public function test_the_proposals_category_is_seen_by_whoever_may_propose_on_the_web(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation', [
            'enabled' => true,
            'allowed_content' => 'both',
            'access_level' => 'registered',
        ]);
        $member = UserModel::factory()->create();

        $this->assertTrue($this->sees(GameTablesMailCategories::PROPOSALS, $member));

        config()->set('modules.settings.game-tables.frontend_creation.enabled', false);

        $this->assertFalse($this->sees(GameTablesMailCategories::PROPOSALS, $member));
    }

    public function test_whoever_already_proposed_something_keeps_seeing_the_proposals_category(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation.enabled', false);
        $author = UserModel::factory()->create();
        $this->campaign($author, FrontendCreationStatus::PendingReview);

        $this->assertTrue($this->sees(GameTablesMailCategories::PROPOSALS, $author));
    }

    public function test_the_moderation_category_is_seen_only_by_moderators(): void
    {
        $player = UserModel::factory()->create();
        $admin = UserModel::factory()->admin()->create();

        $this->assertFalse($this->sees(GameTablesMailCategories::MODERATION, $player));
        $this->assertTrue($this->sees(GameTablesMailCategories::MODERATION, $admin));
    }

    private function sees(string $key, UserModel $user): bool
    {
        $category = $this->registry()->find($key);
        $this->assertInstanceOf(MailCategoryDTO::class, $category);
        $this->assertNotNull($category->audience, "{$key} should have an audience");

        return ($category->audience)($user);
    }

    private function campaign(UserModel $author, ?FrontendCreationStatus $status): CampaignModel
    {
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema '.Str::random(6), 'slug' => 'sistema-'.Str::lower(Str::random(6)), 'is_active' => true]);

        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $author->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-'.Str::lower(Str::random(6)),
            'status' => 'recruiting',
            'frontend_creation_status' => $status,
        ]);
    }

    private function registry(): MailCategoryRegistryInterface
    {
        return app(MailCategoryRegistryInterface::class);
    }
}
