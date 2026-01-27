<?php

declare(strict_types=1);

namespace Modules\GameTables\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\GameTables\Domain\Enums\WarningSeverity;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;

final class ContentWarningsSeeder extends Seeder
{
    public function run(): void
    {
        $warnings = [
            // Mild warnings
            [
                'name' => 'Romance',
                'slug' => 'romance',
                'label' => 'Romance',
                'description' => 'Temas románticos y relaciones amorosas',
                'severity' => WarningSeverity::Mild->value,
                'icon' => 'heroicon-o-heart',
                'is_active' => true,
            ],
            [
                'name' => 'Alcohol y drogas',
                'slug' => 'alcohol',
                'label' => 'Alcohol y drogas',
                'description' => 'Referencias al consumo de alcohol o drogas',
                'severity' => WarningSeverity::Mild->value,
                'icon' => 'heroicon-o-beaker',
                'is_active' => true,
            ],
            [
                'name' => 'Lenguaje soez',
                'slug' => 'language',
                'label' => 'Lenguaje soez',
                'description' => 'Uso de palabras malsonantes o insultos',
                'severity' => WarningSeverity::Mild->value,
                'icon' => 'heroicon-o-chat-bubble-bottom-center-text',
                'is_active' => true,
            ],
            [
                'name' => 'Juego de azar',
                'slug' => 'gambling',
                'label' => 'Juego de azar',
                'description' => 'Escenas o temas relacionados con apuestas y juego',
                'severity' => WarningSeverity::Mild->value,
                'icon' => 'heroicon-o-currency-euro',
                'is_active' => true,
            ],
            [
                'name' => 'Temas religiosos',
                'slug' => 'religion',
                'label' => 'Temas religiosos',
                'description' => 'Referencias a religiones, cultos o temas espirituales',
                'severity' => WarningSeverity::Mild->value,
                'icon' => 'heroicon-o-sparkles',
                'is_active' => true,
            ],
            [
                'name' => 'Oscuridad y claustrofobia',
                'slug' => 'darkness',
                'label' => 'Oscuridad y claustrofobia',
                'description' => 'Escenas en oscuridad total o espacios cerrados',
                'severity' => WarningSeverity::Mild->value,
                'icon' => 'heroicon-o-moon',
                'is_active' => true,
            ],

            // Moderate warnings
            [
                'name' => 'Violencia',
                'slug' => 'violence',
                'label' => 'Violencia',
                'description' => 'Escenas de violencia física o combate',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-fire',
                'is_active' => true,
            ],
            [
                'name' => 'Sangre y gore',
                'slug' => 'blood',
                'label' => 'Sangre y gore',
                'description' => 'Descripciones gráficas de sangre o heridas',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-exclamation-triangle',
                'is_active' => true,
            ],
            [
                'name' => 'Terror',
                'slug' => 'horror',
                'label' => 'Terror',
                'description' => 'Elementos de terror, miedo o suspenso',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-eye',
                'is_active' => true,
            ],
            [
                'name' => 'Salud mental',
                'slug' => 'mental-health',
                'label' => 'Salud mental',
                'description' => 'Temas de trastornos mentales o inestabilidad psicológica',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-user',
                'is_active' => true,
            ],
            [
                'name' => 'Discriminación',
                'slug' => 'discrimination',
                'label' => 'Discriminación',
                'description' => 'Temas de racismo, sexismo u otras formas de discriminación',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-user-group',
                'is_active' => true,
            ],
            [
                'name' => 'Cautiverio',
                'slug' => 'captivity',
                'label' => 'Cautiverio',
                'description' => 'Escenas de prisión, secuestro o encierro',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-lock-closed',
                'is_active' => true,
            ],
            [
                'name' => 'Horror corporal',
                'slug' => 'body-horror',
                'label' => 'Horror corporal',
                'description' => 'Transformaciones corporales, mutaciones o deformaciones',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-bug-ant',
                'is_active' => true,
            ],
            [
                'name' => 'Insectos y arañas',
                'slug' => 'insects',
                'label' => 'Insectos y arañas',
                'description' => 'Presencia destacada de insectos, arácnidos o similar',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-bug-ant',
                'is_active' => true,
            ],
            [
                'name' => 'Guerra',
                'slug' => 'war',
                'label' => 'Guerra',
                'description' => 'Temas bélicos, conflictos armados o atrocidades de guerra',
                'severity' => WarningSeverity::Moderate->value,
                'icon' => 'heroicon-o-fire',
                'is_active' => true,
            ],

            // Severe warnings
            [
                'name' => 'Muerte',
                'slug' => 'death',
                'label' => 'Muerte',
                'description' => 'Temas de muerte, morir o pérdida de seres queridos',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-exclamation-circle',
                'is_active' => true,
            ],
            [
                'name' => 'Tortura',
                'slug' => 'torture',
                'label' => 'Tortura',
                'description' => 'Escenas de tortura física o psicológica',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-hand-raised',
                'is_active' => true,
            ],
            [
                'name' => 'Contenido sexual',
                'slug' => 'sexual-content',
                'label' => 'Contenido sexual',
                'description' => 'Escenas o temas de naturaleza sexual explícita',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-shield-exclamation',
                'is_active' => true,
            ],
            [
                'name' => 'Violencia sexual',
                'slug' => 'sexual-violence',
                'label' => 'Violencia sexual',
                'description' => 'Referencias a agresión o violencia sexual',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-no-symbol',
                'is_active' => true,
            ],
            [
                'name' => 'Daño a menores',
                'slug' => 'child-harm',
                'label' => 'Daño a menores',
                'description' => 'Temas que involucran violencia o daño a menores',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-shield-exclamation',
                'is_active' => true,
            ],
            [
                'name' => 'Suicidio',
                'slug' => 'suicide',
                'label' => 'Suicidio',
                'description' => 'Temas de suicidio o autolesión',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-exclamation-circle',
                'is_active' => true,
            ],
            [
                'name' => 'Daño a animales',
                'slug' => 'animal-harm',
                'label' => 'Daño a animales',
                'description' => 'Escenas de crueldad o daño hacia animales',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-exclamation-triangle',
                'is_active' => true,
            ],
            [
                'name' => 'Canibalismo',
                'slug' => 'cannibalism',
                'label' => 'Canibalismo',
                'description' => 'Escenas o temas de canibalismo',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-no-symbol',
                'is_active' => true,
            ],
            [
                'name' => 'Trata de personas',
                'slug' => 'human-trafficking',
                'label' => 'Trata de personas',
                'description' => 'Temas de esclavitud o trata de seres humanos',
                'severity' => WarningSeverity::Severe->value,
                'icon' => 'heroicon-o-no-symbol',
                'is_active' => true,
            ],
        ];

        foreach ($warnings as $warning) {
            ContentWarningModel::query()->updateOrCreate(
                ['slug' => $warning['slug']],
                $warning,
            );
        }
    }
}
