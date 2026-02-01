<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\Tone;

final class FrontendCreateCampaignRequest extends FormRequest
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
            'game_system_id' => ['required', 'uuid', 'exists:gametables_game_systems,id'],
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'frequency' => ['required', 'string', Rule::in(CampaignFrequency::values())],
            'schedule_notes' => ['nullable', 'string', 'max:500'],
            'min_players' => ['required', 'integer', 'min:1', 'max:12'],
            'max_players' => ['required', 'integer', 'min:1', 'max:12', 'gte:min_players'],
            'table_format' => ['required', 'string', Rule::in(TableFormat::values())],
            'location' => ['nullable', 'string', 'max:500'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'language' => ['sometimes', 'string', 'size:2'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['string', Rule::in(Genre::values())],
            'tone' => ['nullable', 'string', Rule::in(Tone::values())],
            'experience_level' => ['nullable', 'string', Rule::in(ExperienceLevel::values())],
            'character_creation' => ['nullable', 'string', Rule::in(CharacterCreation::values())],
            'safety_tools' => ['nullable', 'array'],
            'safety_tools.*' => ['string', Rule::in(SafetyTool::values())],
            'content_warning_ids' => ['nullable', 'array'],
            'content_warning_ids.*' => ['uuid', 'exists:gametables_content_warnings,id'],
            'custom_warnings' => ['nullable', 'array'],
            'custom_warnings.*' => ['string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('game-tables::messages.validation.title_required'),
            'title.min' => __('game-tables::messages.validation.title_min'),
            'max_players.gte' => __('game-tables::messages.validation.max_players_gte_min'),
        ];
    }
}
