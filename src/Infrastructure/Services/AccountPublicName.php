<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * How the module names an account to other people: its public name, or its account name when it has none
 * (an account without a public name has no public profile to tie it to). Directors, inviters and players
 * all go by this rule.
 */
final class AccountPublicName
{
    public static function of(UserModel $account): string
    {
        return self::from($account->display_name, $account->name);
    }

    public static function from(?string $publicName, ?string $accountName): string
    {
        $publicName = trim((string) $publicName);

        return $publicName !== '' ? $publicName : (string) $accountName;
    }

    /**
     * The names of several accounts in one read, keyed by account id; unknown ids are left out.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public static function forIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (UserModel::query()->whereKey(array_values(array_unique($ids)))->get(['id', 'name', 'display_name']) as $account) {
            $names[(string) $account->id] = self::of($account);
        }

        return $names;
    }
}
