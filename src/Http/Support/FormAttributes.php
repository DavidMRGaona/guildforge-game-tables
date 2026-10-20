<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

/**
 * Names of the web table and campaign form fields inside validation messages, as the form labels
 * them (`game-tables::messages.form_attributes`). A wildcard key takes the name of its item:
 * `genres.*` reads `genres_item`, `game_masters.*.email` reads `game_masters_email`.
 */
final class FormAttributes
{
    /**
     * @param  list<string>  $keys  rule keys of the request
     * @return array<string, string>
     */
    public static function for(array $keys): array
    {
        $attributes = [];

        foreach ($keys as $key) {
            $name = str_ends_with($key, '.*') ? substr($key, 0, -2).'_item' : str_replace('.*.', '_', $key);
            $attributes[$key] = (string) __('game-tables::messages.form_attributes.'.$name);
        }

        return $attributes;
    }
}
