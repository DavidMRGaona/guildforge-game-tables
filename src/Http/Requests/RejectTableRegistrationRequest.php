<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The optional reason a director gives when rejecting a registration, capped as the panel caps it:
 * the registration service emails it as it comes. The controller checks who may
 * (manageRegistrations).
 */
final class RejectTableRegistrationRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => __('game-tables::messages.validation.rejection_reason_max'),
        ];
    }
}
