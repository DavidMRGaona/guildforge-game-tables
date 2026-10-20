<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
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
use Modules\GameTables\Http\Support\AuthorLockRedirect;
use Modules\GameTables\Http\Support\FormAttributes;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\CampaignSessionCreationGate;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;

final class FrontendCreateGameTableRequest extends FormRequest
{
    private ?GameTableModel $routeTable = null;

    private bool $routeTableResolved = false;

    /** @var list<string>|null */
    private ?array $allowedCampaignIds = null;

    private ?RedirectResponse $lockedAuthorRedirect = null;

    /**
     * Runs before validation: the author of a table that is no longer theirs to change gets the
     * reason, as the controller gives it, instead of the errors of a form they cannot save.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $this->lockedAuthorRedirect = app(AuthorLockRedirect::class)->forTable($this->routeTable(), (string) $user->getAuthIdentifier());

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
            // Only a co-director without an account already on the edited table keeps its id (FrontendCreationService)
            'game_masters.*.id' => ['nullable', 'uuid'],
            'game_masters.*.user_id' => ['nullable', 'uuid'],
            'game_masters.*.first_name' => ['required_without:game_masters.*.user_id', 'nullable', 'string', 'max:100'],
            'game_masters.*.last_name' => ['nullable', 'string', 'max:100'],
            'game_masters.*.email' => ['required_without:game_masters.*.user_id', 'nullable', 'email', 'max:255'],
            'game_masters.*.phone' => ['nullable', 'string', 'max:30'],
            'game_masters.*.custom_title' => ['nullable', 'string', 'max:100'],
            'game_masters.*.is_name_public' => ['boolean'],
            'game_masters.*.role' => ['required', 'string', Rule::in(GameMasterRole::values())],
            // Asked only when the save adds a co-director: the ones already on the table were consented to
            'gm_gdpr_consent' => [Rule::when(fn (): bool => $this->addsCoDirector(), ['accepted'], ['nullable', 'boolean'])],

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
            'game_masters.*.first_name.required_without' => __('game-tables::messages.validation.co_director_first_name_required'),
            'game_masters.*.email.required_without' => __('game-tables::messages.validation.co_director_email_required'),
            'gm_gdpr_consent.accepted' => __('game-tables::messages.validation.co_directors_consent_required'),
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
        $keys = [
            'game_system_id', 'title', 'starts_at', 'duration_minutes', 'table_type', 'table_format',
            'min_players', 'max_players', 'max_spectators', 'event_id', 'campaign_id', 'synopsis',
            'location', 'online_url', 'minimum_age', 'language', 'genres', 'genres.*', 'tone',
            'experience_level', 'character_creation', 'safety_tools', 'safety_tools.*',
            'content_warning_ids', 'content_warning_ids.*', 'custom_warnings', 'custom_warnings.*',
            'game_masters', 'game_masters.*.id', 'game_masters.*.user_id', 'game_masters.*.first_name',
            'game_masters.*.last_name', 'game_masters.*.email', 'game_masters.*.phone',
            'game_masters.*.custom_title', 'game_masters.*.is_name_public', 'game_masters.*.role',
            'gm_gdpr_consent', 'notes',
        ];

        return FormAttributes::for($keys);
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
     * Whether the save adds a co-director: a row other than the author's main row whose id is not
     * one of the table's own co-directors the form keeps (on create, every such row). Only these
     * need the co-directors' consent again.
     */
    private function addsCoDirector(): bool
    {
        $rows = $this->input('game_masters');

        if (! is_array($rows)) {
            return false;
        }

        $table = $this->editableTable();
        $keptIds = $table !== null
            ? app(FrontendCreationServiceInterface::class)->editableCoDirectorIds($table->id)
            : [];
        $mainSeen = false;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // The first main row is the author (FrontendCreationService); any other row is a co-director
            if (! $mainSeen && ($row['role'] ?? null) === GameMasterRole::Main->value) {
                $mainSeen = true;

                continue;
            }

            if (! in_array($row['id'] ?? null, $keptIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Table being edited (null on create), resolved like the controller does. Only for someone
     * who may edit that table, so validation never reveals the campaign or event of another
     * user's draft.
     */
    private function editableTable(): ?GameTableModel
    {
        $table = $this->routeTable();

        return $table !== null && $this->user()?->can('update', $table) === true ? $table : null;
    }

    /**
     * The {gameTable} of the route, found as the controller finds it (null on create)
     */
    private function routeTable(): ?GameTableModel
    {
        if ($this->routeTableResolved) {
            return $this->routeTable;
        }

        $this->routeTableResolved = true;

        $key = $this->route('gameTable');

        if (! is_string($key) || $key === '') {
            return null;
        }

        return $this->routeTable = GameTableModel::findBySlugOrId($key);
    }
}
