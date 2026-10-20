<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A request to join a campaign, with an optional message for the direction.
 */
final class RequestToJoinCampaignRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.max' => __('game-tables::messages.validation.join_message_max'),
        ];
    }
}
