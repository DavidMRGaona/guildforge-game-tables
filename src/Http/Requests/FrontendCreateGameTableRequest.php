<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\CampaignSessionCreationGate;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;

final class FrontendCreateGameTableRequest extends FormRequest
{
    private ?GameTableModel $editableTable = null;

    private bool $editableTableResolved = false;

    /** @var list<string>|null */
    private ?array $allowedCampaignIds = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Only a campaign session belongs to a campaign: any other type drops it.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('table_type') !== TableType::CampaignSession->value) {
            $this->merge(['campaign_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $settings = app(GameTableSettingsReader::class);
        $maxPlayers = $settings->maxPlayersLimit();

        return [
            'game_system_id' => ['required', 'uuid', 'exists:gametables_game_systems,id'],
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:'.$settings->maxDurationMinutes()],
            'table_type' => ['required', 'string', Rule::in(TableType::values())],
            'table_format' => ['required', 'string', Rule::in(TableFormat::values())],
            'min_players' => ['required', 'integer', 'min:1', 'max:'.$maxPlayers],
            'max_players' => [
                'required',
                'integer',
                'min:1',
                'max:'.$maxPlayers,
                'gte:min_players',
                // The whole roster of the campaign always fits in its session
                function (string $attribute, mixed $value, Closure $fail): void {
                    $campaignId = $this->input('campaign_id');

                    if (! is_numeric($value) || ! is_string($campaignId) || ! in_array($campaignId, $this->allowedCampaignIds(), true)) {
                        return;
                    }

                    $rosterSize = app(CampaignRosterRepositoryInterface::class)->count(new CampaignId($campaignId));

                    if ((int) $value < $rosterSize) {
                        $fail(__('game-tables::messages.validation.max_players_below_roster', ['count' => $rosterSize]));
                    }
                },
            ],
            'max_spectators' => ['sometimes', 'integer', 'min:0', 'max:'.$settings->maxSpectatorsLimit()],
            // Only the events the form offers (published, not ended, tables enabled). On edit, the
            // event already stored on the table is kept even if it is no longer offered: an admin
            // may have linked it from the panel, or the event may have closed since.
            'event_id' => [
                'nullable',
                'uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! in_array($value, $this->allowedEventIds(), true)) {
                        $fail(__('game-tables::messages.validation.event_not_open'));
                    }
                },
            ],
            // A session needs its campaign, one the author directs and may create sessions of: the
            // ones the form offers (create and edit both validate here; the panel has its own form
            // and may link any campaign). On edit, the campaign already stored on the table is kept
            // even if the author does not direct it: an admin may have linked it from the panel.
            'campaign_id' => [
                'nullable',
                'required_if:table_type,'.TableType::CampaignSession->value,
                'uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! in_array($value, $this->allowedCampaignIds(), true)) {
                        $fail(__('game-tables::messages.validation.campaign_not_owned'));
                    }
                },
            ],
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
            'campaign_id.required_if' => __('game-tables::messages.validation.campaign_required'),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedEventIds(): array
    {
        $eventIds = app(FrontendCreationServiceInterface::class)->selectableEventIds();

        $storedEventId = $this->editableTable()?->event_id;
        if ($storedEventId !== null) {
            $eventIds[] = $storedEventId;
        }

        return $eventIds;
    }

    /**
     * Campaigns the author may create sessions of, plus the one stored on the table being edited.
     *
     * @return list<string>
     */
    private function allowedCampaignIds(): array
    {
        if ($this->allowedCampaignIds !== null) {
            return $this->allowedCampaignIds;
        }

        $userId = (string) $this->user()?->getAuthIdentifier();
        $campaignIds = $userId !== ''
            ? app(CampaignSessionCreationGate::class)->sessionCampaignIds($userId)
            : [];

        $storedCampaignId = $this->editableTable()?->campaign_id;
        if ($storedCampaignId !== null) {
            $campaignIds[] = $storedCampaignId;
        }

        return $this->allowedCampaignIds = $campaignIds;
    }

    /**
     * Table being edited (null on create), resolved like the controller does. Only for someone
     * who may edit that table, so validation never reveals the campaign or event of another
     * user's draft.
     */
    private function editableTable(): ?GameTableModel
    {
        if ($this->editableTableResolved) {
            return $this->editableTable;
        }

        $this->editableTableResolved = true;

        $key = $this->route('gameTable');

        if (! is_string($key) || $key === '') {
            return null;
        }

        $table = GameTableModel::findBySlugOrId($key);

        if ($table === null || $this->user()?->can('update', $table) !== true) {
            return null;
        }

        return $this->editableTable = $table;
    }
}
