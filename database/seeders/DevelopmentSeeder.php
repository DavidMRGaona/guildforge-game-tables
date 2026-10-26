<?php

declare(strict_types=1);

namespace Modules\GameTables\Database\Seeders;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final class DevelopmentSeeder extends Seeder
{
    /**
     * Enabling the module in a test runs every seeder, so the demo data would land in every module
     * test. Only the tests of this seeder turn it on.
     */
    public static bool $seedInTests = false;

    public function run(): void
    {
        // Demo data: the core runs every module seeder on install and update, production included
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (app()->runningUnitTests() && ! self::$seedInTests) {
            return;
        }

        // Ensure production seeders have run first
        $this->call([
            PublishersSeeder::class,
            GameSystemsSeeder::class,
            ContentWarningsSeeder::class,
        ]);

        $people = $this->samplePeople();

        $this->seedCampaigns();
        $this->seedGameTables();

        // Without model events: the observer would enrol the roster in the sessions and announce it
        // by email. Every row the services would write is written here instead
        GameTableModel::withoutEvents(function () use ($people): void {
            $this->seedRosterCampaigns($people);
            $this->seedReviewWorkflow($people);
            $this->seedTableScenarios($people);
        });
    }

    private function seedCampaigns(): void
    {
        $dnd5e = GameSystemModel::where('slug', 'dnd-5e')->first();
        $coc = GameSystemModel::where('slug', 'call-of-cthulhu-7e')->first();
        $vtm = GameSystemModel::where('slug', 'vtm-5e')->first();
        $user = $this->oldestUser();

        if (! $user instanceof UserModel || $dnd5e === null) {
            return;
        }

        $campaigns = [
            [
                'game_system_id' => $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'La maldición de Strahd',
                'slug' => 'maldicion-strahd',
                'description' => 'Una campaña gótica de terror en las tierras de Barovia, donde los aventureros deberán enfrentarse al temible vampiro Strahd von Zarovich.',
                'frequency' => CampaignFrequency::Weekly,
                'status' => CampaignStatus::Active,
                'session_count' => 12,
                'current_session' => 8,
                'accepts_new_players' => false,
                'max_players' => 5,
                'is_published' => true,
            ],
            [
                'game_system_id' => $coc->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'Las máscaras de Nyarlathotep',
                'slug' => 'mascaras-nyarlathotep',
                'description' => 'Una épica campaña de investigación que lleva a los investigadores por todo el mundo siguiendo el rastro de una conspiración cósmica.',
                'frequency' => CampaignFrequency::Biweekly,
                'status' => CampaignStatus::Recruiting,
                'session_count' => 24,
                'current_session' => 0,
                'accepts_new_players' => true,
                'max_players' => 6,
                'is_published' => true,
            ],
            [
                'game_system_id' => $vtm->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'Noches de sangre en Madrid',
                'slug' => 'noches-sangre-madrid',
                'description' => 'Crónica de Vampiro ambientada en el Madrid actual. Los personajes son neonatos que deben navegar la política vampírica de la ciudad.',
                'frequency' => CampaignFrequency::Weekly,
                'status' => CampaignStatus::Active,
                'session_count' => 20,
                'current_session' => 15,
                'accepts_new_players' => true,
                'max_players' => 4,
                'is_published' => true,
            ],
        ];

        foreach ($campaigns as $campaignData) {
            $campaign = CampaignModel::firstOrCreate(
                ['slug' => $campaignData['slug']],
                array_merge($campaignData, ['id' => Str::uuid()->toString()]),
            );

            $this->seedCampaignGameMasters($campaign);
        }
    }

    private function seedCampaignGameMasters(CampaignModel $campaign): void
    {
        $user = $this->oldestUser();
        if (! $user instanceof UserModel) {
            return;
        }

        // Find or create the GM in the unified table
        $gameMaster = GameMasterModel::firstOrCreate(
            ['user_id' => $user->id, 'role' => GameMasterRole::Main],
            [
                'id' => Str::uuid()->toString(),
                'is_name_public' => true,
                'notify_by_email' => true,
            ],
        );

        // Attach to campaign via pivot table if not already attached
        if (! $campaign->gameMasters()->where('gametables_game_masters.id', $gameMaster->id)->exists()) {
            $campaign->gameMasters()->attach($gameMaster->id, [
                'sort_order' => 0,
            ]);
        }
    }

    private function seedGameTables(): void
    {
        $dnd5e = GameSystemModel::where('slug', 'dnd-5e')->first();
        $dnd2024 = GameSystemModel::where('slug', 'dnd-2024')->first();
        $coc = GameSystemModel::where('slug', 'call-of-cthulhu-7e')->first();
        $morkBorg = GameSystemModel::where('slug', 'mork-borg')->first();
        $blades = GameSystemModel::where('slug', 'blades-in-the-dark')->first();
        $user = $this->oldestUser();

        if (! $user instanceof UserModel || $dnd5e === null) {
            return;
        }

        // Get campaigns to link some tables
        $strahdCampaign = CampaignModel::where('slug', 'maldicion-strahd')->first();

        $violenceWarning = ContentWarningModel::where('slug', 'violence')->first();
        $horrorWarning = ContentWarningModel::where('slug', 'horror')->first();
        $deathWarning = ContentWarningModel::where('slug', 'death')->first();

        $tables = [
            [
                'game_system_id' => $dnd2024->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'Aventura en la Cripta de los Horrores',
                'slug' => 'aventura-cripta-horrores',
                'starts_at' => $this->siteNow()->addDays(7)->setHour(17)->setMinute(0),
                'duration_minutes' => 240,
                'table_type' => TableType::OneShot,
                'table_format' => TableFormat::InPerson,
                'status' => TableStatus::Scheduled,
                'min_players' => 3,
                'max_players' => 5,
                'max_spectators' => 2,
                'synopsis' => 'Los aventureros son contratados para investigar una antigua cripta donde se rumorea que habita un nigromante. ¿Qué secretos oscuros descubrirán en las profundidades?',
                'location' => 'Sede de la asociación',
                'language' => 'es',
                'genres' => [Genre::Fantasy->value, Genre::Horror->value],
                'tone' => Tone::Dark,
                'experience_level' => ExperienceLevel::Beginner,
                'character_creation' => CharacterCreation::PreGenerated,
                'safety_tools' => [SafetyTool::XCard->value, SafetyTool::LinesAndVeils->value],
                'is_published' => true,
                'published_at' => now(),
                'warnings' => [$violenceWarning?->id, $horrorWarning?->id],
            ],
            [
                'game_system_id' => $coc->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'La casa de la colina',
                'slug' => 'casa-colina-coc',
                'starts_at' => $this->siteNow()->addDays(14)->setHour(18)->setMinute(30),
                'duration_minutes' => 180,
                'table_type' => TableType::OneShot,
                'table_format' => TableFormat::InPerson,
                'status' => TableStatus::Scheduled,
                'min_players' => 2,
                'max_players' => 4,
                'max_spectators' => 0,
                'synopsis' => 'Una sesión de terror lovecraftiano. Un grupo de investigadores recibe una misteriosa carta que les lleva a una mansión abandonada en las afueras de Arkham.',
                'location' => 'Sede de la asociación',
                'language' => 'es',
                'genres' => [Genre::Horror->value, Genre::Mystery->value],
                'tone' => Tone::Dark,
                'experience_level' => ExperienceLevel::None,
                'character_creation' => CharacterCreation::PreGenerated,
                'safety_tools' => [SafetyTool::XCard->value, SafetyTool::OpenDoor->value],
                'is_published' => true,
                'published_at' => now(),
                'warnings' => [$horrorWarning?->id, $deathWarning?->id],
            ],
            [
                'game_system_id' => $morkBorg->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'El fin está cerca',
                'slug' => 'fin-cerca-morkborg',
                'starts_at' => $this->siteNow()->addDays(21)->setHour(16)->setMinute(0),
                'duration_minutes' => 180,
                'table_type' => TableType::OneShot,
                'table_format' => TableFormat::Hybrid,
                'status' => TableStatus::Scheduled,
                'min_players' => 2,
                'max_players' => 4,
                'max_spectators' => 3,
                'synopsis' => 'El apocalipsis se acerca. En este mundo moribundo, un grupo de miserables intenta sobrevivir un día más mientras buscan un artefacto que podría salvarles... o condenarles.',
                'location' => 'Sede de la asociación + Discord',
                'online_url' => 'https://discord.gg/example',
                'language' => 'es',
                'genres' => [Genre::Fantasy->value, Genre::Horror->value],
                'tone' => Tone::Dark,
                'experience_level' => ExperienceLevel::Intermediate,
                'character_creation' => CharacterCreation::CreateAtTable,
                'safety_tools' => [SafetyTool::XCard->value],
                'is_published' => true,
                'published_at' => now(),
                'warnings' => [$violenceWarning?->id, $deathWarning?->id],
            ],
            [
                'game_system_id' => $blades->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'Sombras sobre Doskvol',
                'slug' => 'sombras-doskvol',
                'starts_at' => $this->siteNow()->addDays(10)->setHour(19)->setMinute(0),
                'duration_minutes' => 210,
                'table_type' => TableType::CampaignSession,
                'table_format' => TableFormat::Online,
                'status' => TableStatus::Scheduled,
                'min_players' => 3,
                'max_players' => 4,
                'max_spectators' => 0,
                'synopsis' => 'Sesión 0 + primera sesión de una campaña de Blades in the Dark. Crearemos la banda criminal juntos y daremos nuestro primer golpe en las calles de Doskvol.',
                'online_url' => 'https://discord.gg/example',
                'language' => 'es',
                'genres' => [Genre::Fantasy->value, Genre::Modern->value],
                'tone' => Tone::Dark,
                'experience_level' => ExperienceLevel::Beginner,
                'character_creation' => CharacterCreation::CreateAtTable,
                'safety_tools' => [SafetyTool::LinesAndVeils->value, SafetyTool::Script->value],
                'is_published' => true,
                'published_at' => now(),
                'warnings' => [$violenceWarning?->id],
            ],
            [
                'game_system_id' => $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'Iniciación a D&D para principiantes',
                'slug' => 'iniciacion-dnd-principiantes',
                'starts_at' => $this->siteNow()->addDays(5)->setHour(11)->setMinute(0),
                'duration_minutes' => 180,
                'table_type' => TableType::Demo,
                'table_format' => TableFormat::InPerson,
                'status' => TableStatus::Scheduled,
                'min_players' => 3,
                'max_players' => 6,
                'max_spectators' => 4,
                'synopsis' => 'Partida de iniciación para quienes nunca han jugado a rol. Explicaremos las reglas básicas y jugaremos una aventura sencilla. ¡No se necesita experiencia previa!',
                'location' => 'Sede de la asociación',
                'language' => 'es',
                'genres' => [Genre::Fantasy->value],
                'tone' => Tone::Light,
                'experience_level' => ExperienceLevel::Beginner,
                'character_creation' => CharacterCreation::PreGenerated,
                'safety_tools' => [SafetyTool::XCard->value],
                'is_published' => true,
                'published_at' => now(),
                'warnings' => [],
            ],
            // Campaign-linked table - GMs inherited from campaign
            [
                'game_system_id' => $dnd5e->id,
                'campaign_id' => $strahdCampaign?->id,
                'created_by' => $user->id,
                'title' => 'Strahd: Sesión 9 - El castillo Ravenloft',
                'slug' => 'strahd-sesion-9',
                'starts_at' => $this->siteNow()->addDays(3)->setHour(17)->setMinute(0),
                'duration_minutes' => 240,
                'table_type' => TableType::CampaignSession,
                'table_format' => TableFormat::InPerson,
                'status' => TableStatus::Scheduled,
                'min_players' => 4,
                'max_players' => 5,
                'max_spectators' => 0,
                'synopsis' => 'Los aventureros finalmente llegan al castillo Ravenloft para enfrentarse a Strahd. ¿Conseguirán acabar con su maldición?',
                'location' => 'Sede de la asociación',
                'language' => 'es',
                'genres' => [Genre::Fantasy->value, Genre::Horror->value],
                'tone' => Tone::Dark,
                'experience_level' => ExperienceLevel::Intermediate,
                'character_creation' => CharacterCreation::BringOwn,
                'safety_tools' => [SafetyTool::XCard->value, SafetyTool::LinesAndVeils->value],
                'is_published' => true,
                'published_at' => now(),
                'warnings' => [$horrorWarning?->id, $deathWarning?->id],
            ],
        ];

        foreach ($tables as $tableData) {
            $warnings = $tableData['warnings'];
            unset($tableData['warnings']);

            $table = GameTableModel::firstOrCreate(
                ['slug' => $tableData['slug']],
                array_merge($tableData, ['id' => Str::uuid()->toString()]),
            );

            $warningIds = array_filter($warnings);
            if ($warningIds !== []) {
                $table->contentWarnings()->syncWithoutDetaching($warningIds);
            }

            // Seed local GMs for standalone tables
            // Campaign-linked tables inherit GMs automatically
            if ($table->campaign_id === null) {
                $this->seedGameMasters($table);
            }

            if ($tableData['slug'] === 'aventura-cripta-horrores') {
                $this->seedParticipants($table);
            }
        }
    }

    private function seedGameMasters(GameTableModel $table): void
    {
        $user = $this->oldestUser();
        if (! $user instanceof UserModel) {
            return;
        }

        // Find or create the GM in the unified table
        $gameMaster = GameMasterModel::firstOrCreate(
            ['user_id' => $user->id, 'role' => GameMasterRole::Main],
            [
                'id' => Str::uuid()->toString(),
                'is_name_public' => true,
                'notify_by_email' => true,
            ],
        );

        // Attach to table via pivot table as a local GM
        if (! $table->gameMasters()->where('gametables_game_masters.id', $gameMaster->id)->exists()) {
            $table->gameMasters()->attach($gameMaster->id, [
                'source' => 'local',
                'excluded' => false,
                'sort_order' => 0,
            ]);
        }
    }

    private function seedParticipants(GameTableModel $table): void
    {
        $users = UserModel::query()->orderBy('created_at')->orderBy('id')->limit(3)->get();

        foreach ($users as $index => $user) {
            ParticipantModel::firstOrCreate(
                [
                    'game_table_id' => $table->id,
                    'user_id' => $user->id,
                ],
                [
                    'id' => Str::uuid()->toString(),
                    'role' => ParticipantRole::Player,
                    'status' => $index === 0 ? ParticipantStatus::Confirmed : ParticipantStatus::Pending,
                    'confirmed_at' => $index === 0 ? now() : null,
                ],
            );
        }
    }

    /**
     * Always the same account: without an order the database may return a different one each run.
     */
    private function oldestUser(): ?UserModel
    {
        return UserModel::query()->orderBy('created_at')->orderBy('id')->first();
    }

    /**
     * The four sample people, with a verified account and the base role.
     *
     * @return array{john: UserModel, jane: UserModel, richard: UserModel, mary: UserModel}
     */
    private function samplePeople(): array
    {
        $memberRole = app(MemberRoleServiceInterface::class);
        $people = [];

        foreach (['john' => 'John Doe', 'jane' => 'Jane Doe', 'richard' => 'Richard Roe', 'mary' => 'Mary Major'] as $key => $name) {
            $email = Str::lower(str_replace(' ', '.', $name)).'@example.local';
            $user = UserModel::where('email', $email)->first();

            if ($user === null) {
                $user = new UserModel;
                $user->forceFill([
                    'name' => $name,
                    'display_name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ])->save();
            }

            // Idempotent: also covers an account another module's seeder created
            $memberRole->assignTo($user);
            $people[$key] = $user;
        }

        return $people;
    }

    /**
     * Two campaigns John created from the web (approved) and directs: one closed to new players with
     * a roster, a pending join request and its sessions; one recruiting.
     *
     * @param  array{john: UserModel, jane: UserModel, richard: UserModel, mary: UserModel}  $people
     */
    private function seedRosterCampaigns(array $people): void
    {
        $john = $this->gameMasterOf($people['john']);

        // Closed: the roster is complete for now; Mary asked to join while it was recruiting
        $closed = $this->campaign([
            'game_system_id' => $this->gameSystemId('forbidden-lands'),
            'created_by' => $people['john']->id,
            'title' => 'Crónicas de la marca del este',
            'slug' => 'cronicas-marca-del-este',
            'description' => 'Una campaña de exploración en las tierras salvajes de la marca. El grupo levanta su fortaleza, recorre las ruinas y decide qué hacer con lo que encuentra.',
            'frequency' => CampaignFrequency::Weekly,
            'status' => CampaignStatus::Active,
            'session_count' => 12,
            'current_session' => 4,
            'accepts_new_players' => false,
            'roster_access' => RosterAccess::Anyone,
            'max_players' => 4,
            'is_published' => true,
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ]);
        $this->attachCampaignDirector($closed, $john);
        $this->addToRoster($closed, $people['jane'], now()->subWeeks(5));
        $this->addToRoster($closed, $people['richard'], now()->subWeeks(5));

        CampaignJoinRequestModel::firstOrCreate(
            [
                'campaign_id' => $closed->id,
                'user_id' => $people['mary']->id,
                'status' => JoinRequestStatus::Pending,
            ],
            [
                'id' => Str::uuid()->toString(),
                'message' => 'Hola, he jugado a otras campañas de exploración y me encantaría unirme si queda un hueco.',
            ],
        );

        $closedSession = [
            'game_system_id' => $closed->game_system_id,
            'campaign_id' => $closed->id,
            'created_by' => $people['john']->id,
            'duration_minutes' => 240,
            'table_type' => TableType::CampaignSession,
            'table_format' => TableFormat::InPerson,
            'min_players' => 2,
            'max_players' => 4,
            'location' => 'Sede de la asociación',
            'genres' => [Genre::Fantasy->value],
            'tone' => Tone::Mixed,
            'experience_level' => ExperienceLevel::Beginner,
            'character_creation' => CharacterCreation::BringOwn,
            'safety_tools' => [SafetyTool::XCard->value],
            'is_published' => true,
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ];

        $past = $this->table(array_merge($closedSession, [
            'title' => 'Crónicas de la marca del este: sesión 4',
            'slug' => 'cronicas-marca-del-este-sesion-4',
            'starts_at' => $this->siteNow()->subDays(7)->setTime(18, 0),
            'status' => TableStatus::Completed,
            'synopsis' => 'El grupo terminó de explorar la torre del vigía y volvió a la fortaleza con un prisionero.',
            'published_at' => now()->subDays(14),
        ]));
        $this->rosterRegistration($past, $people['jane']);
        $this->rosterRegistration($past, $people['richard']);

        $next = $this->table(array_merge($closedSession, [
            'title' => 'Crónicas de la marca del este: sesión 5',
            'slug' => 'cronicas-marca-del-este-sesion-5',
            'starts_at' => $this->siteNow()->addDays(4)->setTime(18, 0),
            'status' => TableStatus::Scheduled,
            'synopsis' => 'El prisionero habla de una mina abandonada al norte. ¿Merece la pena el viaje?',
            'published_at' => now()->subDays(3),
        ]));
        $this->rosterRegistration($next, $people['jane']);
        $this->rosterRegistration($next, $people['richard']);

        $later = $this->table(array_merge($closedSession, [
            'title' => 'Crónicas de la marca del este: sesión 6',
            'slug' => 'cronicas-marca-del-este-sesion-6',
            'starts_at' => $this->siteNow()->addDays(11)->setTime(18, 0),
            'status' => TableStatus::Scheduled,
            'synopsis' => 'La mina del norte guarda algo más que plata.',
            'published_at' => now()->subDays(3),
        ]));
        $this->rosterRegistration($later, $people['jane']);
        // Richard told the direction he will miss this one
        $this->rosterRegistration($later, $people['richard'], [
            'status' => ParticipantStatus::Cancelled,
            'cancelled_at' => now()->subDay(),
            'cancellation_reason' => ParticipantCancellationReason::SessionAbsence,
        ]);

        // Recruiting: Jane is in, the session is open to anyone else
        $recruiting = $this->campaign([
            'game_system_id' => $this->gameSystemId('dnd-5e'),
            'created_by' => $people['john']->id,
            'title' => 'Los herederos del dragón',
            'slug' => 'herederos-del-dragon',
            'description' => 'Campaña de fantasía heroica para empezar desde nivel 1. Buscamos jugadores con ganas de construir la historia del grupo.',
            'frequency' => CampaignFrequency::Biweekly,
            'status' => CampaignStatus::Recruiting,
            'session_count' => 20,
            'current_session' => 0,
            'accepts_new_players' => true,
            'roster_access' => RosterAccess::Anyone,
            'max_players' => 5,
            'is_published' => true,
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ]);
        $this->attachCampaignDirector($recruiting, $john);
        $this->addToRoster($recruiting, $people['jane'], now()->subWeek());

        $first = $this->table([
            'game_system_id' => $recruiting->game_system_id,
            'campaign_id' => $recruiting->id,
            'created_by' => $people['john']->id,
            'title' => 'Los herederos del dragón: sesión 1',
            'slug' => 'herederos-del-dragon-sesion-1',
            'starts_at' => $this->siteNow()->addDays(6)->setTime(17, 30),
            'duration_minutes' => 240,
            'table_type' => TableType::CampaignSession,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Scheduled,
            'min_players' => 3,
            'max_players' => 5,
            'synopsis' => 'Primera sesión: crearemos el grupo y saldremos de la aldea de Robleda. Quien quiera probar puede apuntarse.',
            'location' => 'Sede de la asociación',
            'genres' => [Genre::Fantasy->value],
            'tone' => Tone::Light,
            'experience_level' => ExperienceLevel::None,
            'character_creation' => CharacterCreation::CreateAtTable,
            'safety_tools' => [SafetyTool::XCard->value, SafetyTool::LinesAndVeils->value],
            'registration_type' => RegistrationType::Everyone,
            'is_published' => true,
            'published_at' => now()->subDays(2),
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ]);
        $this->rosterRegistration($first, $people['jane']);
        // An outsider: signed up on their own, not through the roster
        $this->registration($first, $people['richard'], [
            'status' => ParticipantStatus::Confirmed,
            'confirmed_at' => now()->subDay(),
        ]);
    }

    /**
     * Created from the web and waiting for the panel: a campaign in review, a table in review, a
     * table sent back with a reason and a draft never sent.
     *
     * @param  array{john: UserModel, jane: UserModel, richard: UserModel, mary: UserModel}  $people
     */
    private function seedReviewWorkflow(array $people): void
    {
        $this->campaign([
            'game_system_id' => $this->gameSystemId('vaesen'),
            'created_by' => $people['mary']->id,
            'title' => 'Secretos del norte',
            'slug' => 'secretos-del-norte',
            'description' => 'Investigación de misterios en una Escandinavia del siglo XIX donde las criaturas del folclore son reales.',
            'frequency' => CampaignFrequency::Monthly,
            'status' => CampaignStatus::Recruiting,
            'current_session' => 0,
            'accepts_new_players' => true,
            'roster_access' => RosterAccess::Anyone,
            'max_players' => 4,
            'is_published' => false,
            'frontend_creation_status' => FrontendCreationStatus::PendingReview,
        ]);

        $john = $this->gameMasterOf($people['john']);
        $webDraft = [
            'created_by' => $people['john']->id,
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Draft,
            'min_players' => 3,
            'max_players' => 5,
            'location' => 'Sede de la asociación',
            'is_published' => false,
            'published_at' => null,
        ];

        $inReview = $this->table(array_merge($webDraft, [
            'game_system_id' => $this->gameSystemId('dnd-2024'),
            'title' => 'La torre del hechicero',
            'slug' => 'torre-del-hechicero',
            'starts_at' => $this->siteNow()->addDays(20)->setTime(17, 0),
            'synopsis' => 'Un hechicero desaparecido, una torre sellada y un pueblo que no quiere hablar.',
            'genres' => [Genre::Fantasy->value, Genre::Mystery->value],
            'tone' => Tone::Mixed,
            'experience_level' => ExperienceLevel::Beginner,
            'character_creation' => CharacterCreation::PreGenerated,
            'safety_tools' => [SafetyTool::XCard->value],
            'frontend_creation_status' => FrontendCreationStatus::PendingReview,
        ]));
        $this->attachLocalDirector($inReview, $john, 0);

        $rejected = $this->table(array_merge($webDraft, [
            'game_system_id' => $this->gameSystemId('mork-borg'),
            'title' => 'La mazmorra sin nombre',
            'slug' => 'mazmorra-sin-nombre',
            'starts_at' => $this->siteNow()->addDays(18)->setTime(17, 0),
            'synopsis' => 'Bajad. No preguntéis.',
            'genres' => [Genre::Fantasy->value, Genre::Horror->value],
            'tone' => Tone::Dark,
            'character_creation' => CharacterCreation::CreateAtTable,
            'frontend_creation_status' => FrontendCreationStatus::Draft,
            'moderation_notes' => 'La sinopsis es demasiado breve y faltan las advertencias de contenido. Añade de qué trata la partida y qué temas aparecen, y vuelve a enviarla.',
        ]));
        $this->attachLocalDirector($rejected, $john, 0);

        $draft = $this->table(array_merge($webDraft, [
            'game_system_id' => $this->gameSystemId('dragonbane'),
            'title' => 'El bosque de los susurros',
            'slug' => 'bosque-de-los-susurros',
            'starts_at' => $this->siteNow()->addDays(25)->setTime(11, 0),
            'synopsis' => 'Una aventura ligera para jugar en familia.',
            'genres' => [Genre::Fantasy->value],
            'tone' => Tone::Light,
            'frontend_creation_status' => FrontendCreationStatus::Draft,
        ]));
        $this->attachLocalDirector($draft, $john, 0);
    }

    /**
     * Tables in every state worth looking at: full with a waiting list, in progress, completed,
     * invite-only, co-directed with warnings, and with guests (no account).
     *
     * @param  array{john: UserModel, jane: UserModel, richard: UserModel, mary: UserModel}  $people
     */
    private function seedTableScenarios(array $people): void
    {
        $john = $this->gameMasterOf($people['john']);
        $published = [
            'created_by' => $people['john']->id,
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Scheduled,
            'location' => 'Sede de la asociación',
            'registration_type' => RegistrationType::Everyone,
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now()->subDays(3),
        ];

        // Full: both seats taken, Mary first on the waiting list
        $full = $this->table(array_merge($published, [
            'game_system_id' => $this->gameSystemId('call-of-cthulhu-7e'),
            'title' => 'Noche en el faro',
            'slug' => 'noche-en-el-faro',
            'starts_at' => $this->siteNow()->addDays(9)->setTime(18, 0),
            'min_players' => 2,
            'max_players' => 2,
            'synopsis' => 'Dos fareros, una tormenta y una luz que no debería estar encendida. Partida para dos jugadores.',
            'genres' => [Genre::Horror->value, Genre::Mystery->value],
            'tone' => Tone::Dark,
            'experience_level' => ExperienceLevel::None,
            'character_creation' => CharacterCreation::PreGenerated,
            'safety_tools' => [SafetyTool::XCard->value],
        ]));
        $this->attachLocalDirector($full, $john, 0);
        $this->registration($full, $people['jane'], ['status' => ParticipantStatus::Confirmed, 'confirmed_at' => now()->subDays(2)]);
        $this->registration($full, $people['richard'], ['status' => ParticipantStatus::Confirmed, 'confirmed_at' => now()->subDays(2)]);
        $this->registration($full, $people['mary'], ['status' => ParticipantStatus::WaitingList, 'waiting_list_position' => 1]);

        // In progress: started an hour ago
        $inProgress = $this->table(array_merge($published, [
            'game_system_id' => $this->gameSystemId('blades-in-the-dark'),
            'title' => 'Asalto al tren blindado',
            'slug' => 'asalto-al-tren-blindado',
            'starts_at' => now()->subHour()->startOfMinute(),
            'status' => TableStatus::InProgress,
            'min_players' => 2,
            'max_players' => 4,
            'synopsis' => 'La banda tiene un plan para asaltar el tren del gobernador. Qué podría salir mal.',
            'genres' => [Genre::Fantasy->value],
            'tone' => Tone::Mixed,
            'experience_level' => ExperienceLevel::Intermediate,
            'character_creation' => CharacterCreation::PreGenerated,
            'safety_tools' => [SafetyTool::LinesAndVeils->value],
            'published_at' => now()->subDays(10),
        ]));
        $this->attachLocalDirector($inProgress, $john, 0);
        $this->registration($inProgress, $people['jane'], ['status' => ParticipantStatus::Confirmed, 'confirmed_at' => now()->subDays(5)]);
        $this->registration($inProgress, $people['mary'], ['status' => ParticipantStatus::Confirmed, 'confirmed_at' => now()->subDays(4)]);

        // Completed last week; Richard did not turn up
        $completed = $this->table(array_merge($published, [
            'game_system_id' => $this->gameSystemId('wfrp-4e'),
            'title' => 'Las ruinas de Karak',
            'slug' => 'ruinas-de-karak',
            'starts_at' => $this->siteNow()->subDays(5)->setTime(17, 0),
            'status' => TableStatus::Completed,
            'min_players' => 2,
            'max_players' => 4,
            'synopsis' => 'Una expedición a una fortaleza enana abandonada.',
            'genres' => [Genre::Fantasy->value],
            'tone' => Tone::Dark,
            'experience_level' => ExperienceLevel::Beginner,
            'character_creation' => CharacterCreation::PreGenerated,
            'safety_tools' => [SafetyTool::XCard->value],
            'published_at' => now()->subDays(20),
        ]));
        $this->attachLocalDirector($completed, $john, 0);
        $this->registration($completed, $people['jane'], ['status' => ParticipantStatus::Confirmed, 'confirmed_at' => now()->subDays(12)]);
        $this->registration($completed, $people['mary'], ['status' => ParticipantStatus::Confirmed, 'confirmed_at' => now()->subDays(11)]);
        $this->registration($completed, $people['richard'], ['status' => ParticipantStatus::NoShow, 'confirmed_at' => now()->subDays(10)]);

        // Invite-only: the organisation adds who plays
        $invite = $this->table(array_merge($published, [
            'game_system_id' => $this->gameSystemId('vtm-5e'),
            'title' => 'Cena con el conde',
            'slug' => 'cena-con-el-conde',
            'starts_at' => $this->siteNow()->addDays(13)->setTime(20, 0),
            'duration_minutes' => 180,
            'min_players' => 2,
            'max_players' => 4,
            'synopsis' => 'Una velada en el Elíseo a la que solo se acude con invitación.',
            'genres' => [Genre::Horror->value, Genre::Modern->value],
            'tone' => Tone::Serious,
            'experience_level' => ExperienceLevel::Intermediate,
            'character_creation' => CharacterCreation::BringOwn,
            'safety_tools' => [SafetyTool::LinesAndVeils->value, SafetyTool::OpenDoor->value],
            'registration_type' => RegistrationType::Invite,
        ]));
        $this->attachLocalDirector($invite, $john, 0);
        $this->registration($invite, $people['jane'], ['status' => ParticipantStatus::Confirmed, 'confirmed_at' => now()->subDay()]);

        // Web draft with a co-director without an account, warnings and safety tools, for the edit form
        $coDirected = $this->table([
            'game_system_id' => $this->gameSystemId('mothership'),
            'created_by' => $people['john']->id,
            'title' => 'Ecos de la colmena',
            'slug' => 'ecos-de-la-colmena',
            'starts_at' => $this->siteNow()->addDays(16)->setTime(18, 0),
            'duration_minutes' => 210,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::Hybrid,
            'status' => TableStatus::Draft,
            'min_players' => 3,
            'max_players' => 5,
            'max_spectators' => 2,
            'synopsis' => 'Una estación minera deja de responder. La tripulación del carguero es la única nave en la zona.',
            'location' => 'Sede de la asociación',
            'online_url' => 'https://example.local/sala',
            'minimum_age' => 16,
            'genres' => [Genre::SciFi->value, Genre::Horror->value],
            'tone' => Tone::Dark,
            'experience_level' => ExperienceLevel::None,
            'character_creation' => CharacterCreation::PreGenerated,
            'safety_tools' => [SafetyTool::XCard->value, SafetyTool::LinesAndVeils->value, SafetyTool::OpenDoor->value],
            'custom_warnings' => ['Espacios cerrados', 'Insectos gigantes'],
            'is_published' => false,
            'published_at' => null,
            'frontend_creation_status' => FrontendCreationStatus::Draft,
        ], ['violence', 'body-horror', 'death']);
        $this->attachLocalDirector($coDirected, $john, 0);
        $coDirector = GameMasterModel::firstOrCreate(
            ['user_id' => null, 'email' => 'richard.roe@example.local', 'role' => GameMasterRole::CoGm],
            [
                'id' => Str::uuid()->toString(),
                'first_name' => 'Richard',
                'last_name' => 'Roe',
                'custom_title' => 'Codirector',
                'is_name_public' => true,
                'notify_by_email' => true,
            ],
        );
        $this->attachLocalDirector($coDirected, $coDirector, 1);

        // Guests: one still has to confirm the email (no seat held, so nothing lapses with a notice),
        // the other confirmed it
        $guests = $this->table(array_merge($published, [
            'game_system_id' => $this->gameSystemId('dread'),
            'title' => 'Feria de las sombras',
            'slug' => 'feria-de-las-sombras',
            'starts_at' => $this->siteNow()->addDays(8)->setTime(18, 0),
            'duration_minutes' => 180,
            'min_players' => 3,
            'max_players' => 6,
            'synopsis' => 'Una feria ambulante llega al pueblo por la noche. Partida abierta también a quien no tiene cuenta.',
            'genres' => [Genre::Horror->value],
            'tone' => Tone::Dark,
            'experience_level' => ExperienceLevel::None,
            'character_creation' => CharacterCreation::PreGenerated,
            'safety_tools' => [SafetyTool::XCard->value],
        ]));
        $this->attachLocalDirector($guests, $john, 0);
        $this->guestRegistration($guests, 'Richard', 'Roe', 'richard.roe+invitado@example.local', [
            'status' => ParticipantStatus::Pending,
            'confirmation_mode' => GuestConfirmationMode::Wait,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'confirmation_sent_at' => now()->subMinutes(30),
        ]);
        $this->guestRegistration($guests, 'Mary', 'Major', 'mary.major+invitado@example.local', [
            'status' => ParticipantStatus::Confirmed,
            'confirmed_at' => now()->subDays(2),
            'confirmation_mode' => GuestConfirmationMode::Hold,
            'confirmation_token' => bin2hex(random_bytes(32)),
            'confirmation_sent_at' => now()->subDays(2),
            'email_confirmed_at' => now()->subDays(2)->addMinutes(10),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes): CampaignModel
    {
        return CampaignModel::firstOrCreate(
            ['slug' => $attributes['slug']],
            array_merge($attributes, ['id' => Str::uuid()->toString()]),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $warningSlugs
     */
    private function table(array $attributes, array $warningSlugs = []): GameTableModel
    {
        $table = GameTableModel::firstOrCreate(
            ['slug' => $attributes['slug']],
            array_merge(['language' => 'es'], $attributes, ['id' => Str::uuid()->toString()]),
        );

        if ($warningSlugs !== []) {
            $table->contentWarnings()->syncWithoutDetaching(
                ContentWarningModel::whereIn('slug', $warningSlugs)->pluck('id')->all(),
            );
        }

        return $table;
    }

    private function gameSystemId(string $slug): string
    {
        return (string) (GameSystemModel::where('slug', $slug)->value('id')
            ?? GameSystemModel::where('slug', 'dnd-5e')->value('id'));
    }

    /**
     * The person's main director record, shared by all their tables and campaigns.
     */
    private function gameMasterOf(UserModel $user): GameMasterModel
    {
        return GameMasterModel::firstOrCreate(
            ['user_id' => $user->id, 'role' => GameMasterRole::Main],
            [
                'id' => Str::uuid()->toString(),
                'is_name_public' => true,
                'notify_by_email' => true,
            ],
        );
    }

    private function attachCampaignDirector(CampaignModel $campaign, GameMasterModel $gameMaster): void
    {
        if (! $campaign->gameMasters()->where('gametables_game_masters.id', $gameMaster->id)->exists()) {
            $campaign->gameMasters()->attach($gameMaster->id, ['sort_order' => 0]);
        }
    }

    private function attachLocalDirector(GameTableModel $table, GameMasterModel $gameMaster, int $sortOrder): void
    {
        if (! $table->gameMasters()->where('gametables_game_masters.id', $gameMaster->id)->exists()) {
            $table->gameMasters()->attach($gameMaster->id, [
                'source' => 'local',
                'excluded' => false,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    private function addToRoster(CampaignModel $campaign, UserModel $user, Carbon $joinedAt): void
    {
        if (! $campaign->players()->whereKey($user->id)->exists()) {
            $campaign->players()->attach($user->id, ['created_at' => $joinedAt, 'updated_at' => $joinedAt]);
        }
    }

    /**
     * A session registration placed by the roster: a confirmed player seat unless told otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function rosterRegistration(GameTableModel $table, UserModel $user, array $attributes = []): void
    {
        $this->registration($table, $user, array_merge([
            'status' => ParticipantStatus::Confirmed,
            'confirmed_at' => now()->subDays(3),
            'from_roster' => true,
        ], $attributes));
    }

    /**
     * One row per account and table, as the registration service keeps it.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function registration(GameTableModel $table, UserModel $user, array $attributes): void
    {
        ParticipantModel::firstOrCreate(
            ['game_table_id' => $table->id, 'user_id' => $user->id],
            array_merge(['id' => Str::uuid()->toString(), 'role' => ParticipantRole::Player], $attributes),
        );
    }

    /**
     * A registration without an account, found again by its email.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function guestRegistration(GameTableModel $table, string $firstName, string $lastName, string $email, array $attributes): void
    {
        ParticipantModel::firstOrCreate(
            ['game_table_id' => $table->id, 'user_id' => null, 'email' => $email],
            array_merge([
                'id' => Str::uuid()->toString(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => ParticipantRole::Player,
                'cancellation_token' => bin2hex(random_bytes(32)),
            ], $attributes),
        );
    }

    /**
     * Now in the site's zone: the seeded tables start at those hours where the site is.
     */
    private function siteNow(): CarbonImmutable
    {
        return CarbonImmutable::now(app(TimezoneServiceInterface::class)->displayTimezone());
    }
}
