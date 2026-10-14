<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Modules\GameTables\Http\Requests\FrontendCreateGameTableRequest;
use Tests\TestCase;

/**
 * The public table form validates against the limits of the panel settings.
 */
final class FrontendCreateGameTableRequestLimitsTest extends TestCase
{
    private const array LIMITED_FIELDS = ['min_players', 'max_players', 'max_spectators', 'duration_minutes'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', []);
    }

    public function test_the_limits_of_the_settings_bound_the_public_form(): void
    {
        config()->set('modules.settings.game-tables.limits', [
            'max_players_limit' => 8,
            'max_spectators_limit' => 2,
            'max_duration_minutes' => 120,
        ]);

        $errors = $this->validate(['min_players' => 8, 'max_players' => 9, 'max_spectators' => 3, 'duration_minutes' => 150]);

        $this->assertEqualsCanonicalizing(['max_players', 'max_spectators', 'duration_minutes'], array_keys($errors));
        $this->assertSame([], $this->validate(['min_players' => 8, 'max_players' => 8, 'max_spectators' => 2, 'duration_minutes' => 120]));
    }

    public function test_the_default_limits_keep_the_previous_bounds(): void
    {
        $this->assertSame([], $this->validate(['min_players' => 20, 'max_players' => 20, 'max_spectators' => 50, 'duration_minutes' => 720]));

        $errors = $this->validate(['min_players' => 21, 'max_players' => 21, 'max_spectators' => 51, 'duration_minutes' => 721]);

        $this->assertEqualsCanonicalizing(self::LIMITED_FIELDS, array_keys($errors));
    }

    /**
     * @param  array<string, int>  $data
     * @return array<string, array<string>>
     */
    private function validate(array $data): array
    {
        $rules = Arr::only((new FrontendCreateGameTableRequest())->rules(), self::LIMITED_FIELDS);

        return Validator::make($data, $rules)->errors()->toArray();
    }
}
