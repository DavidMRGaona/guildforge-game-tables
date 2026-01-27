<?php

declare(strict_types=1);

namespace Modules\GameTables\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;

final class PublishersSeeder extends Seeder
{
    public function run(): void
    {
        $publishers = [
            [
                'name' => 'Wizards of the Coast',
                'slug' => 'wizards-of-the-coast',
                'country' => 'Estados Unidos',
                'website_url' => 'https://company.wizards.com/',
            ],
            [
                'name' => 'Paizo',
                'slug' => 'paizo',
                'country' => 'Estados Unidos',
                'website_url' => 'https://paizo.com/',
            ],
            [
                'name' => 'Cubicle 7',
                'slug' => 'cubicle-7',
                'country' => 'Reino Unido',
                'website_url' => 'https://cubicle7games.com/',
            ],
            [
                'name' => 'Free League Publishing',
                'slug' => 'free-league',
                'country' => 'Suecia',
                'website_url' => 'https://freeleaguepublishing.com/',
            ],
            [
                'name' => 'Chaosium',
                'slug' => 'chaosium',
                'country' => 'Estados Unidos',
                'website_url' => 'https://www.chaosium.com/',
            ],
            [
                'name' => 'Arc Dream Publishing',
                'slug' => 'arc-dream',
                'country' => 'Estados Unidos',
                'website_url' => 'https://www.delta-green.com/',
            ],
            [
                'name' => 'The Impossible Dream',
                'slug' => 'impossible-dream',
                'country' => 'Estados Unidos',
                'website_url' => null,
            ],
            [
                'name' => 'Renegade Game Studios',
                'slug' => 'renegade',
                'country' => 'Estados Unidos',
                'website_url' => 'https://renegadegamestudios.com/',
            ],
            [
                'name' => 'Tuesday Knight Games',
                'slug' => 'tuesday-knight',
                'country' => 'Estados Unidos',
                'website_url' => 'https://www.tuesdayknightgames.com/',
            ],
            [
                'name' => 'R. Talsorian Games',
                'slug' => 'r-talsorian',
                'country' => 'Estados Unidos',
                'website_url' => 'https://rtalsoriangames.com/',
            ],
            [
                'name' => 'Mongoose Publishing',
                'slug' => 'mongoose',
                'country' => 'Reino Unido',
                'website_url' => 'https://www.mongoosepublishing.com/',
            ],
            [
                'name' => 'Sine Nomine Publishing',
                'slug' => 'sine-nomine',
                'country' => 'Estados Unidos',
                'website_url' => null,
            ],
            [
                'name' => 'Evil Hat Productions',
                'slug' => 'evil-hat',
                'country' => 'Estados Unidos',
                'website_url' => 'https://www.evilhat.com/',
            ],
            [
                'name' => 'Lumpley Games',
                'slug' => 'lumpley',
                'country' => 'Estados Unidos',
                'website_url' => 'http://lumpley.com/',
            ],
            [
                'name' => 'Sage Kobold Productions',
                'slug' => 'sage-kobold',
                'country' => 'Estados Unidos',
                'website_url' => 'https://dungeon-world.com/',
            ],
            [
                'name' => 'Bully Pulpit Games',
                'slug' => 'bully-pulpit',
                'country' => 'Estados Unidos',
                'website_url' => 'https://bullypulpitgames.com/',
            ],
            [
                'name' => 'Nosolorol',
                'slug' => 'nosolorol',
                'country' => 'España',
                'website_url' => 'https://nosolorol.com/',
            ],
            [
                'name' => 'Goblinoid Games',
                'slug' => 'goblinoid',
                'country' => 'Estados Unidos',
                'website_url' => null,
            ],
            [
                'name' => 'Steve Jackson Games',
                'slug' => 'steve-jackson',
                'country' => 'Estados Unidos',
                'website_url' => 'https://www.sjgames.com/',
            ],
            [
                'name' => 'Pinnacle Entertainment',
                'slug' => 'pinnacle',
                'country' => 'Estados Unidos',
                'website_url' => 'https://peginc.com/',
            ],
            [
                'name' => 'Pelgrane Press',
                'slug' => 'pelgrane',
                'country' => 'Reino Unido',
                'website_url' => 'https://pelgranepress.com/',
            ],
        ];

        foreach ($publishers as $publisher) {
            PublisherModel::query()->updateOrCreate(
                ['slug' => $publisher['slug']],
                array_merge($publisher, ['is_active' => true]),
            );
        }
    }
}
