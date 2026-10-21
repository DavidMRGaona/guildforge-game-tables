<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The direction invites an email to the roster, saying the person agreed to be invited; the
 * controller checks who may (manageRoster).
 */
final class InviteRosterPlayerRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('game-tables::messages.validation.email_required'),
            'email.email' => __('game-tables::messages.validation.email_invalid'),
            'consent.accepted' => __('game-tables::messages.validation.invitation_consent_required'),
        ];
    }
}
