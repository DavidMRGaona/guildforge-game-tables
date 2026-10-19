<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Application\Services\AccountLookupInterface;
use Tests\Support\Modules\ModuleTestCase;

final class UserModelAccountLookupTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_it_finds_an_account_ignoring_case_and_spaces(): void
    {
        $user = $this->userWithRawEmail('Ana@Example.com');

        $account = app(AccountLookupInterface::class)->findByEmail('ana@example.com ');

        $this->assertNotNull($account);
        $this->assertSame($user->id, $account->userId);
        $this->assertSame('Ana@Example.com', $account->email);
    }

    public function test_it_finds_nothing_for_an_unknown_email(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com']);

        $this->assertNull(app(AccountLookupInterface::class)->findByEmail('otra@example.com'));
    }

    public function test_it_ignores_anonymized_accounts(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com', 'anonymized_at' => now()]);

        $this->assertNull(app(AccountLookupInterface::class)->findByEmail('ana@example.com'));
    }

    public function test_it_ignores_soft_deleted_accounts(): void
    {
        $user = UserModel::factory()->create(['email' => 'ana@example.com']);
        $user->delete();

        $this->assertNull(app(AccountLookupInterface::class)->findByEmail('ana@example.com'));
    }

    public function test_it_reports_whether_the_email_is_verified(): void
    {
        UserModel::factory()->create(['email' => 'verified@example.com', 'email_verified_at' => now()]);
        UserModel::factory()->create(['email' => 'unverified@example.com', 'email_verified_at' => null]);
        $lookup = app(AccountLookupInterface::class);

        $this->assertTrue($lookup->findByEmail('verified@example.com')?->emailVerified);
        $this->assertFalse($lookup->findByEmail('unverified@example.com')?->emailVerified);
    }

    public function test_find_by_id_returns_the_stored_email(): void
    {
        $user = $this->userWithRawEmail('Ana@Example.com');
        $lookup = app(AccountLookupInterface::class);

        $this->assertSame('Ana@Example.com', $lookup->findById($user->id)?->email);
        $this->assertNull($lookup->findById('00000000-0000-0000-0000-000000000000'));
    }

    /**
     * The model lowercases the email it is given; older rows may keep capitals.
     */
    private function userWithRawEmail(string $email): UserModel
    {
        $user = UserModel::factory()->create();
        DB::table($user->getTable())->where('id', $user->id)->update(['email' => $email]);

        return $user;
    }
}
