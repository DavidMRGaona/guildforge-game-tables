<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\AccountDTO;
use Modules\GameTables\Application\Services\AccountLookupInterface;

final readonly class UserModelAccountLookup implements AccountLookupInterface
{
    public function findByEmail(string $email): ?AccountDTO
    {
        $user = UserModel::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->whereNull('anonymized_at')
            ->first();

        return $user === null ? null : $this->toDto($user);
    }

    public function findById(string $userId): ?AccountDTO
    {
        $user = UserModel::query()->find($userId);

        return $user === null ? null : $this->toDto($user);
    }

    public function findActiveById(string $userId): ?AccountDTO
    {
        // A malformed id would make PostgreSQL reject the query on the uuid column
        if (! Str::isUuid($userId)) {
            return null;
        }

        $user = UserModel::query()
            ->whereKey($userId)
            ->whereNull('anonymized_at')
            ->first();

        return $user === null ? null : $this->toDto($user);
    }

    private function toDto(UserModel $user): AccountDTO
    {
        return new AccountDTO(
            userId: (string) $user->id,
            email: $user->email,
            emailVerified: $user->email_verified_at !== null,
        );
    }
}
