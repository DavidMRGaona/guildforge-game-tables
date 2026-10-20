<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Http\Support\AuthorLockRedirect;
use Modules\GameTables\Http\Support\FormAttributes;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final class FrontendCreateCampaignRequest extends FormRequest
{
    private ?RedirectResponse $lockedAuthorRedirect = null;

    /**
     * Runs before validation: the author of a campaign that is no longer theirs to change gets the
     * reason, as the controller gives it, instead of the errors of a form they cannot save.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $id = $this->route('id');
        $campaign = is_string($id) && Str::isUuid($id) ? CampaignModel::query()->find($id) : null;
        $this->lockedAuthorRedirect = app(AuthorLockRedirect::class)->forCampaign($campaign, (string) $user->getAuthIdentifier());

        return $this->lockedAuthorRedirect === null;
    }

    protected function failedAuthorization(): void
    {
        if ($this->lockedAuthorRedirect !== null) {
            throw new HttpResponseException($this->lockedAuthorRedirect);
        }

        parent::failedAuthorization();
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

    /**
     * The form's labels, so every message reads «El campo sistema de juego es obligatorio.» and
     * never names a key
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return FormAttributes::for([
            'game_system_id', 'title', 'synopsis', 'frequency', 'schedule_notes', 'min_players',
            'max_players', 'table_format', 'location', 'online_url', 'language', 'genres', 'genres.*',
            'tone', 'experience_level', 'character_creation', 'safety_tools', 'safety_tools.*',
            'content_warning_ids', 'content_warning_ids.*', 'custom_warnings', 'custom_warnings.*',
        ]);
    }
}
