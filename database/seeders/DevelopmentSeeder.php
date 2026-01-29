<?php

declare(strict_types=1);

namespace Modules\GameTables\Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed in development/testing environments
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Ensure production seeders have run first
        $this->call([
            PublishersSeeder::class,
            GameSystemsSeeder::class,
            ContentWarningsSeeder::class,
        ]);

        $this->seedCampaigns();
        $this->seedGameTables();
    }

    private function seedCampaigns(): void
    {
        $dnd5e = GameSystemModel::where('slug', 'dnd-5e')->first();
        $coc = GameSystemModel::where('slug', 'call-of-cthulhu-7e')->first();
        $vtm = GameSystemModel::where('slug', 'vtm-5e')->first();
        $user = UserModel::first();

        if ($user === null || $dnd5e === null) {
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
                'game_system_id' => $coc?->id ?? $dnd5e->id,
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
                'game_system_id' => $vtm?->id ?? $dnd5e->id,
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
        $user = UserModel::first();
        if ($user === null) {
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
        $user = UserModel::first();

        if ($user === null || $dnd5e === null) {
            return;
        }

        // Get campaigns to link some tables
        $strahdCampaign = CampaignModel::where('slug', 'maldicion-strahd')->first();

        $violenceWarning = ContentWarningModel::where('slug', 'violencia')->first();
        $horrorWarning = ContentWarningModel::where('slug', 'terror')->first();
        $deathWarning = ContentWarningModel::where('slug', 'muerte')->first();

        $tables = [
            [
                'game_system_id' => $dnd2024?->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'Aventura en la Cripta de los Horrores',
                'slug' => 'aventura-cripta-horrores',
                'starts_at' => now()->addDays(7)->setHour(17)->setMinute(0),
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
                'game_system_id' => $coc?->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'La casa de la colina',
                'slug' => 'casa-colina-coc',
                'starts_at' => now()->addDays(14)->setHour(18)->setMinute(30),
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
                'game_system_id' => $morkBorg?->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'El fin está cerca',
                'slug' => 'fin-cerca-morkborg',
                'starts_at' => now()->addDays(21)->setHour(16)->setMinute(0),
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
                'game_system_id' => $blades?->id ?? $dnd5e->id,
                'created_by' => $user->id,
                'title' => 'Sombras sobre Doskvol',
                'slug' => 'sombras-doskvol',
                'starts_at' => now()->addDays(10)->setHour(19)->setMinute(0),
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
                'starts_at' => now()->addDays(5)->setHour(11)->setMinute(0),
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
                'starts_at' => now()->addDays(3)->setHour(17)->setMinute(0),
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
            $warnings = $tableData['warnings'] ?? [];
            unset($tableData['warnings']);

            $table = GameTableModel::firstOrCreate(
                ['slug' => $tableData['slug']],
                array_merge($tableData, ['id' => Str::uuid()->toString()]),
            );

            $warningIds = array_filter($warnings);
            if (! empty($warningIds)) {
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
        $user = UserModel::first();
        if ($user === null) {
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
        $users = UserModel::limit(3)->get();

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
}
