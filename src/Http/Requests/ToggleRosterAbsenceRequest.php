<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A chip of the roster page: mark (absent) or clear a member's absence from one session. The
 * controller checks who may (manageRoster).
 */
final class ToggleRosterAbsenceRequest extends FormRequest
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
            'game_table_id' => ['required', 'uuid'],
            'user_id' => ['required', 'uuid'],
            'absent' => ['required', 'boolean'],
        ];
    }
}
