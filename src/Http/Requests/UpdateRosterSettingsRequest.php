<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;

/**
 * The three roster settings the direction changes from the roster page; the controller checks
 * who may (manageRoster).
 */
final class UpdateRosterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'accepts_new_players' => ['required', 'boolean'],
            'roster_access' => ['required', 'string', Rule::in(RosterAccess::values())],
            'max_players' => ['nullable', 'integer', 'min:1', 'max:'.app(GameTableSettingsReader::class)->maxPlayersLimit()],
        ];
    }
}
