<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The closing date cannot pass the moment the server closes registrations anyway: the start of
 * the table, or its end when it admits registrations in play.
 */
final class RegistrationClosingDateTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $admin = $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);

        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => '2030-01-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
        ]);
    }

    public function test_the_closing_date_cannot_pass_the_start(): void
    {
        $this->assertClosingError($this->save(['registration_closes_at' => '2030-01-10 18:00:01']), 'El cierre de inscripciones no puede ser posterior al inicio de la mesa (10/01/2030 18:00)');
    }

    public function test_the_closing_date_may_be_the_start(): void
    {
        $this->save(['registration_closes_at' => '2030-01-10 18:00:00'])
            ->assertHasNoFormErrors(['registration_closes_at']);
    }

    public function test_in_play_the_closing_date_may_be_the_end(): void
    {
        $this->save(['registration_closes_at' => '2030-01-10 22:00:00', 'accepts_registrations_in_progress' => true])
            ->assertHasNoFormErrors(['registration_closes_at']);
    }

    public function test_in_play_the_closing_date_cannot_pass_the_end(): void
    {
        $this->assertClosingError($this->save(['registration_closes_at' => '2030-01-10 22:00:01', 'accepts_registrations_in_progress' => true]), 'El cierre de inscripciones no puede ser posterior al final de la partida (10/01/2030 22:00)');
    }

    public function test_without_a_closing_date_there_is_nothing_to_check(): void
    {
        $this->save(['registration_closes_at' => null])
            ->assertHasNoFormErrors(['registration_closes_at']);
    }

    public function test_the_limit_follows_the_start_and_duration_submitted_with_it(): void
    {
        $this->assertClosingError(
            $this->save(['starts_at' => '2030-01-09 18:00:00', 'registration_closes_at' => '2030-01-10 18:00:00']),
            'El cierre de inscripciones no puede ser posterior al inicio de la mesa (09/01/2030 18:00)',
        );

        $this->save(['starts_at' => '2030-01-09 18:00:00', 'duration_minutes' => 1440, 'registration_closes_at' => '2030-01-10 18:00:00', 'accepts_registrations_in_progress' => true])
            ->assertHasNoFormErrors(['registration_closes_at']);
    }

    public function test_unparseable_dates_give_a_validation_error_instead_of_an_exception(): void
    {
        $this->save(['starts_at' => 'garbage', 'registration_closes_at' => 'garbage'])
            ->assertHasFormErrors(['starts_at']);
    }

    public function test_an_unparseable_closing_date_alone_does_not_throw(): void
    {
        $this->save(['registration_closes_at' => 'garbage'])
            ->assertHasFormErrors(['registration_closes_at']);
    }

    public function test_an_empty_or_absurd_duration_in_play_does_not_throw(): void
    {
        foreach ([null, '', -5, 'abc'] as $duration) {
            $this->save(['duration_minutes' => $duration, 'accepts_registrations_in_progress' => true, 'registration_closes_at' => '2030-01-10 22:00:00'])
                ->assertHasNoFormErrors(['registration_closes_at']);
        }
    }

    public function test_the_message_is_shown_in_english_under_the_en_locale(): void
    {
        app()->setLocale('en');

        $this->assertClosingError($this->save(['registration_closes_at' => '2030-01-10 18:00:01']), 'Registrations cannot close after the table starts (10/01/2030 18:00)');
    }

    public function test_the_english_end_message_is_shown_in_play(): void
    {
        app()->setLocale('en');

        $this->assertClosingError($this->save(['registration_closes_at' => '2030-01-10 22:00:01', 'accepts_registrations_in_progress' => true]), 'Registrations cannot close after the session ends (10/01/2030 22:00)');
    }

    public function test_the_new_keys_resolve_in_both_languages(): void
    {
        $expected = [
            'es' => [
                'validation.registration_closes_after_start' => 'El cierre de inscripciones no puede ser posterior al inicio de la mesa (:date)',
                'validation.registration_closes_after_end' => 'El cierre de inscripciones no puede ser posterior al final de la partida (:date)',
                'fields.registration_closes_at_help' => 'Como muy tarde, el inicio de la mesa (o su final, si admite inscripciones durante la partida). Las bajas de los participantes se cierran siempre al inicio.',
            ],
            'en' => [
                'validation.registration_closes_after_start' => 'Registrations cannot close after the table starts (:date)',
                'validation.registration_closes_after_end' => 'Registrations cannot close after the session ends (:date)',
                'fields.registration_closes_at_help' => 'At the latest, the start of the table (or its end, if it admits registrations in play). Participants can always cancel only until the start.',
            ],
        ];

        foreach ($expected as $locale => $keys) {
            foreach ($keys as $key => $text) {
                $this->assertSame($text, trans('game-tables::messages.'.$key, [], $locale), "$locale $key");
            }
        }
    }

    /**
     * Livewire splits expected messages on ":" (rule:parameter), which the time in the message
     * contains, so the literal text is compared against the error bag instead.
     *
     * @param  Testable<EditGameTable>  $component
     */
    private function assertClosingError(Testable $component, string $message): void
    {
        $component->assertHasFormErrors(['registration_closes_at']);

        $this->assertSame([$message], $component->errors()->get('data.registration_closes_at'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Testable<EditGameTable>
     */
    private function save(array $data): Testable
    {
        return Livewire::test(EditGameTable::class, ['record' => $this->table->getRouteKey()])
            ->fillForm([
                'starts_at' => '2030-01-10 18:00:00',
                'duration_minutes' => 240,
                'accepts_registrations_in_progress' => false,
                ...$data,
            ])
            ->call('save');
    }
}
