<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;

final class FrontendCreateGameTableRequest extends FormRequest
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
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:720'],
            'table_type' => ['required', 'string', Rule::in(TableType::values())],
            'table_format' => ['required', 'string', Rule::in(TableFormat::values())],
            'min_players' => ['required', 'integer', 'min:1', 'max:20'],
            'max_players' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_players'],
            'max_spectators' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'event_id' => ['nullable', 'uuid', 'exists:events,id'],
            'campaign_id' => ['nullable', 'uuid', 'exists:gametables_campaigns,id'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:500'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'minimum_age' => ['nullable', 'integer', 'min:0', 'max:21'],
            'language' => ['required', 'string', 'size:2'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['string', Rule::in(Genre::values())],
            'tone' => ['nullable', 'string', Rule::in(Tone::values())],
            'experience_level' => ['required', 'string', Rule::in(ExperienceLevel::values())],
            'character_creation' => ['required', 'string', Rule::in(CharacterCreation::values())],
            'safety_tools' => ['nullable', 'array'],
            'safety_tools.*' => ['string', Rule::in(SafetyTool::values())],
            'content_warning_ids' => ['nullable', 'array'],
            'content_warning_ids.*' => ['uuid', 'exists:gametables_content_warnings,id'],
            'custom_warnings' => ['nullable', 'array'],
            'custom_warnings.*' => ['string', 'max:200'],

            // Game Masters
            'game_masters' => ['nullable', 'array', 'min:1'],
            'game_masters.*.user_id' => ['nullable', 'uuid'],
            'game_masters.*.first_name' => ['required_without:game_masters.*.user_id', 'nullable', 'string', 'max:100'],
            'game_masters.*.last_name' => ['nullable', 'string', 'max:100'],
            'game_masters.*.email' => ['required_without:game_masters.*.user_id', 'nullable', 'email', 'max:255'],
            'game_masters.*.custom_title' => ['nullable', 'string', 'max:100'],
            'game_masters.*.is_name_public' => ['boolean'],
            'game_masters.*.role' => ['required', 'string', Rule::in(GameMasterRole::values())],

            'notes' => ['nullable', 'string', 'max:2000'],
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
            'starts_at.after' => __('game-tables::messages.validation.starts_at_future'),
            'max_players.gte' => __('game-tables::messages.validation.max_players_gte_min'),
            'language.required' => __('game-tables::messages.validation.language_required'),
            'experience_level.required' => __('game-tables::messages.validation.experience_level_required'),
            'character_creation.required' => __('game-tables::messages.validation.character_creation_required'),
        ];
    }
}
