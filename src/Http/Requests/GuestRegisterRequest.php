<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GameTables\Domain\Enums\ParticipantRole;

final class GuestRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Anyone can register as a guest
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => [
                'sometimes',
                'string',
                Rule::in([ParticipantRole::Player->value, ParticipantRole::Spectator->value]),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'gdpr_consent' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('game-tables::messages.validation.first_name_required'),
            'first_name.max' => __('game-tables::messages.validation.first_name_max'),
            'email.required' => __('game-tables::messages.validation.email_required'),
            'email.email' => __('game-tables::messages.validation.email_invalid'),
            'phone.max' => __('game-tables::messages.validation.phone_max'),
            'role.in' => __('game-tables::messages.validation.invalid_role'),
            'notes.max' => __('game-tables::messages.validation.notes_max'),
            'gdpr_consent.required' => __('game-tables::messages.validation.gdpr_consent_required'),
            'gdpr_consent.accepted' => __('game-tables::messages.validation.gdpr_consent_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => __('game-tables::messages.fields.first_name'),
            'email' => __('game-tables::messages.fields.email'),
            'phone' => __('game-tables::messages.fields.phone'),
            'role' => __('game-tables::messages.fields.role'),
            'notes' => __('game-tables::messages.fields.notes'),
            'gdpr_consent' => __('game-tables::messages.fields.gdpr_consent'),
        ];
    }
}
