<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Support;

use Illuminate\Validation\ValidationException;

/**
 * A table whose director manages the registrations needs every director linked to an account:
 * the panel refuses to save it otherwise, before anything is written.
 */
final class DelegatedDirectors
{
    /**
     * @param  array<string, mixed>  $data  the table's form data, directors under `gameMasters`
     *
     * @throws ValidationException when the flag is on and a director has no account
     */
    public static function ensureLinked(array $data): void
    {
        if (! (bool) ($data['delegates_registrations'] ?? false) || filled($data['campaign_id'] ?? null)) {
            return;
        }

        $rows = $data['gameMasters'] ?? [];
        $name = is_array($rows) ? self::firstWithoutAccount($rows) : null;

        if ($name !== null) {
            throw ValidationException::withMessages([
                'data.gameMasters' => __('game-tables::messages.validation.delegated_directors_need_accounts', ['name' => $name]),
            ]);
        }
    }

    /**
     * The display name of the first director without an account, or null when all are linked.
     * The type selector is not part of the saved data, so a row counts by its `user_id`; excluded
     * and inherited rows belong to the campaign and are skipped.
     *
     * @param  array<array-key, mixed>  $rows
     */
    public static function firstWithoutAccount(array $rows): ?string
    {
        foreach ($rows as $row) {
            if (! is_array($row) || ! empty($row['excluded']) || ! empty($row['is_inherited'])) {
                continue;
            }

            if (blank($row['user_id'] ?? null)) {
                $name = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));

                return $name !== '' ? $name : (string) ($row['email'] ?? '');
            }
        }

        return null;
    }
}
