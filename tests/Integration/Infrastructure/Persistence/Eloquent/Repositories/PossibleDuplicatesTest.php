<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Posible duplicado»: active rows of a table that share a fingerprint, at least one of them a
 * guest, made within the window (the last confirmation email sent, else the registration).
 */
final class PossibleDuplicatesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const string FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private GameTableModel $table;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = $this->createTable();
    }

    public function test_two_guests_with_the_same_fingerprint_three_hours_apart_are_both_marked(): void
    {
        $first = $this->guest('2026-11-02 10:00:00');
        $second = $this->guest('2026-11-02 13:00:00');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->duplicates());
    }

    public function test_two_guests_further_apart_than_the_window_are_not_marked(): void
    {
        $this->guest('2026-11-02 10:00:00');
        $this->guest('2026-11-03 11:00:00');

        $this->assertSame([], $this->duplicates());
    }

    public function test_exactly_the_window_apart_is_still_marked(): void
    {
        $first = $this->guest('2026-11-02 10:00:00');
        $second = $this->guest('2026-11-03 10:00:00');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->duplicates());
    }

    public function test_two_user_rows_are_not_marked(): void
    {
        $this->user('2026-11-02 10:00:00');
        $this->user('2026-11-02 11:00:00');

        $this->assertSame([], $this->duplicates());
    }

    public function test_a_guest_and_a_later_user_are_both_marked(): void
    {
        $guest = $this->guest('2026-11-02 10:00:00');
        $user = $this->user('2026-11-02 12:00:00');

        $this->assertEqualsCanonicalizing([$guest->id, $user->id], $this->duplicates());
    }

    public function test_another_fingerprint_another_table_or_an_inactive_row_are_not_marked(): void
    {
        $this->guest('2026-11-02 10:00:00');
        $this->guest('2026-11-02 10:30:00', ['fingerprint' => str_repeat('b', 64)]);
        $this->guest('2026-11-02 11:00:00', ['game_table_id' => $this->createTable()->id]);
        $this->guest('2026-11-02 11:30:00', ['status' => 'cancelled']);
        $this->guest('2026-11-02 12:00:00', ['status' => 'expired']);
        $this->guest('2026-11-02 12:30:00', ['fingerprint' => null]);

        $this->assertSame([], $this->duplicates());
    }

    // A reactivated row keeps its old registration date: the last email sent is what counts
    public function test_the_last_confirmation_email_counts_over_the_registration_date(): void
    {
        $reactivated = $this->guest('2026-10-20 10:00:00', ['confirmation_sent_at' => '2026-11-02 09:00:00']);
        $other = $this->guest('2026-11-02 10:00:00');

        $this->assertEqualsCanonicalizing([$reactivated->id, $other->id], $this->duplicates());
    }

    /**
     * @return list<string>
     */
    private function duplicates(): array
    {
        return app(ParticipantRepositoryInterface::class)->possibleDuplicateIds(new GameTableId($this->table->id), 24);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guest(string $createdAt, array $attributes = []): ParticipantModel
    {
        return $this->participant($createdAt, [
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => Str::lower(Str::random(8)).'@example.test',
            ...$attributes,
        ]);
    }

    private function user(string $createdAt): ParticipantModel
    {
        return $this->participant($createdAt, ['user_id' => UserModel::factory()->create()->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(string $createdAt, array $attributes): ParticipantModel
    {
        $this->travelTo(CarbonImmutable::parse($createdAt));

        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'fingerprint' => self::FINGERPRINT,
            ...$attributes,
        ]);
    }

    private function createTable(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa-'.Str::lower(Str::random(6)),
            'starts_at' => CarbonImmutable::parse('2026-12-01 18:00:00'),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => true,
        ]);
    }
}
