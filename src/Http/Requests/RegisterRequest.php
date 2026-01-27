<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GameTables\Domain\Enums\ParticipantRole;

final class RegisterRequest extends FormRequest
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
            'role' => [
                'sometimes',
                'string',
                Rule::in(array_column(ParticipantRole::cases(), 'value')),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => __('game-tables::messages.validation.invalid_role'),
            'notes.max' => __('game-tables::messages.validation.notes_max'),
        ];
    }
}
