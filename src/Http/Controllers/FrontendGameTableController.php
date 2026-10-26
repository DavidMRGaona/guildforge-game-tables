<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\MyPublishedTableDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Domain\Exceptions\TimeSlotUnavailableException;
use Modules\GameTables\Http\Requests\FrontendCreateGameTableRequest;
use Modules\GameTables\Http\Support\AuthorLockRedirect;
use Modules\GameTables\Http\Support\AuthorReturnTarget;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\CampaignSessionCreationGate;

final class FrontendGameTableController extends Controller
{
    public function __construct(
        private readonly FrontendCreationServiceInterface $creationService,
        private readonly CreationEligibilityServiceInterface $eligibilityService,
        private readonly EventGameTableConfigServiceInterface $eventConfigService,
        private readonly GameTableServiceInterface $gameTableService,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly CampaignSessionCreationGate $sessionGate,
        private readonly AuthorLockRedirect $authorLock,
        private readonly AuthorReturnTarget $returnTarget,
    ) {}

    /**
     * Show the table creation form.
     */
    public function create(Request $request): Response
    {
        $timezone = app(TimezoneServiceInterface::class);

        $user = auth()->user();
        $eventSlug = $request->query('event');

        // Resolve event by slug if provided
        $event = null;
        if (is_string($eventSlug)) {
            $event = EventModel::where('slug', $eventSlug)->first();
        }
        $eventId = $event?->id;

        // Check eligibility - use event-specific if event ID provided (a closed event says so
        // before the form)
        $eligibility = $eventId !== null
            ? $this->creationService->eventCreationEligibility($eventId, $user?->id)
            : $this->eligibilityService->canCreateTable($user?->id);

        // Someone the general rules refuse may still create sessions of the campaigns they direct
        $sessionOnly = ! $eligibility->eligible
            && $eventId === null
            && $user !== null
            && $this->sessionGate->canCreateSessionsOnly((string) $user->id);

        if (! $eligibility->eligible && ! $sessionOnly) {
            // Show a page explaining why they can't create
            return Inertia::render('GameTables/CreateNotEligible', [
                'reason' => $eligibility->reason,
                'canCreateAt' => $timezone->iso($eligibility->canCreateAt),
                'seo' => $this->seo->forPage(new SeoPageDTO(
                    title: (string) __('game-tables::messages.seo.create_table_title'),
                    noindex: true,
                ))->toArray(),
            ]);
        }

        // Get form data
        $formData = $this->creationService->getCreateFormData();

        // Get event context if creating table for a specific event
        $eventContext = null;
        if ($eventId !== null) {
            $eventContext = $this->eventConfigService->getCreationContext($eventId);
        }

        // Prepare current user data for GM section: their custom title, phone and name visibility
        // come from their director record, shared by every table they direct (saving writes them there)
        $currentUser = $user !== null ? [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            ...$this->creationService->getAuthorGameMasterProfile((string) $user->id),
        ] : null;

        // «Añadir sesión» arrives with its campaign; any other id is ignored
        $campaignId = $request->query('campaign');
        $prefill = is_string($campaignId) && in_array($campaignId, array_column($formData['campaigns'], 'id'), true)
            ? ['table_type' => TableType::CampaignSession->value, 'campaign_id' => $campaignId]
            : null;

        return Inertia::render('GameTables/Create', [
            'formData' => $formData,
            'eligibility' => $eligibility->toArray(),
            'eventContext' => $eventContext?->toArray(),
            'currentUser' => $currentUser,
            'sessionOnly' => $sessionOnly,
            'prefill' => $prefill,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.create_table_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Store a new table.
     */
    public function store(FrontendCreateGameTableRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = auth()->user();

        $dto = FrontendCreateGameTableDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        try {
            $this->creationService->createGameTable($dto);
        } catch (NotEligibleToCreateException) {
            abort(403);
        } catch (SessionBelowRosterException $e) {
            // Published at once but with fewer seats than the roster: nothing was saved
            return back()->withErrors(['max_players' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.table_created'));
    }

    /**
     * Show user's tables: drafts (pending review, rejected) and published tables they create or direct.
     */
    public function myTables(): Response
    {
        /** @var UserModel $user */
        $user = auth()->user();
        $tables = $this->creationService->getMyTables((string) $user->id);

        $eligibility = $this->eligibilityService->canCreateTable((string) $user->id);

        return Inertia::render('GameTables/MyTables', [
            'drafts' => array_map(
                static fn (GameTableResponseDTO $dto) => $dto->toArray(),
                $tables['drafts']
            ),
            'published' => array_map(
                static fn (MyPublishedTableDTO $dto) => $dto->toArray(),
                $tables['published']
            ),
            'canCreate' => $eligibility->eligible,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.my_tables_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Show an edit form for a draft table.
     */
    public function edit(string $gameTable): Response|RedirectResponse
    {
        $gameTable = $this->findAuthorTable($gameTable);
        if (($locked = $this->sendLockedAuthorBack($gameTable)) instanceof RedirectResponse) {
            return $locked;
        }
        Gate::authorize('update', $gameTable);

        /** @var UserModel $user */
        $user = auth()->user();

        // Get the table DTO using the service (consistent with architecture)
        $table = $this->gameTableService->findOrFail($gameTable->id);

        // Get form data for the edit form, keeping the campaign and the event stored on the table
        $formData = $this->creationService->getCreateFormData($gameTable->campaign_id, $gameTable->event_id);

        // Get event context if a table is associated with an event
        $eventContext = null;
        if ($gameTable->event_id !== null) {
            // The table's own place in its time slot counts as free
            $eventContext = $this->eventConfigService->getCreationContext($gameTable->event_id, $gameTable->id);
        }

        // Prepare current user data for GM section
        $currentUser = [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        // As on the create form: someone the general rules refuse only keeps sessions of the
        // campaigns they direct (saving applies the same rule)
        $sessionOnly = $gameTable->event_id === null
            && ! $this->eligibilityService->canCreateTable((string) $user->id)->eligible
            && $this->sessionGate->canCreateSessionsOnly((string) $user->id);

        return Inertia::render('GameTables/Edit', [
            'table' => $table->toArray(),
            // The value of the datetime-local field: the site's wall time
            'startsAtWallTime' => $table->startsAt instanceof \DateTimeInterface
                ? app(TimezoneServiceInterface::class)->toDisplayWallTime($table->startsAt)
                : null,
            // The author's own table: the directors come with their email and phone
            'gameMasters' => $this->creationService->getDraftGameMasters($gameTable->id, (string) $user->id),
            'formData' => $formData,
            'eventContext' => $eventContext?->toArray(),
            'currentUser' => $currentUser,
            'sessionOnly' => $sessionOnly,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.edit_table_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Update a draft table.
     */
    public function update(string $gameTable, FrontendCreateGameTableRequest $request): RedirectResponse
    {
        $gameTable = $this->findAuthorTable($gameTable);
        if (($locked = $this->sendLockedAuthorBack($gameTable)) instanceof RedirectResponse) {
            return $locked;
        }
        Gate::authorize('update', $gameTable);

        /** @var UserModel $user */
        $user = auth()->user();

        $dto = FrontendCreateGameTableDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        try {
            $this->creationService->updateDraft($gameTable->id, $dto);
        } catch (NotEditableException) {
            // The policy let an admin permission through, but the service only edits the author's own draft
            abort(403);
        } catch (NotEligibleToCreateException) {
            // The edited table is one the author may not create
            abort(403);
        }

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.table_updated'));
    }

    /**
     * Submit a draft for review.
     */
    public function submitForReview(string $gameTable): RedirectResponse
    {
        $gameTable = $this->findAuthorTable($gameTable);
        if (($locked = $this->sendLockedAuthorBack($gameTable)) instanceof RedirectResponse) {
            return $locked;
        }
        Gate::authorize('submitForReview', $gameTable);

        /** @var UserModel $user */
        $user = auth()->user();

        try {
            $this->creationService->submitForReview($gameTable->id, (string) $user->id);
        } catch (NotEligibleToCreateException) {
            abort(403);
        } catch (SessionBelowRosterException $e) {
            // Published at once (automatic mode) but with fewer seats than the roster
            return $this->returnTarget->back(AuthorReturnTarget::TABLES_TAB)->with('error', $e->getMessage());
        } catch (TimeSlotUnavailableException $e) {
            // A rejected table whose time slot filled up meanwhile: it stays a draft
            return $this->returnTarget->back(AuthorReturnTarget::TABLES_TAB)->with('error', $e->getMessage());
        }

        // Without review (automatic mode, or an author who may publish directly) it is already published
        $published = (bool) $gameTable->refresh()->is_published;

        return $this->returnTarget
            ->redirect('gametables.my-tables', AuthorReturnTarget::TABLES_TAB)
            ->with('success', __($published
                ? 'game-tables::messages.frontend.table_published'
                : 'game-tables::messages.frontend.submitted_for_review'));
    }

    /**
     * Delete a draft table.
     */
    public function destroy(string $gameTable): RedirectResponse
    {
        $gameTable = $this->findAuthorTable($gameTable);
        if (($locked = $this->sendLockedAuthorBack($gameTable)) instanceof RedirectResponse) {
            return $locked;
        }
        Gate::authorize('delete', $gameTable);

        /** @var UserModel $user */
        $user = auth()->user();

        try {
            $this->creationService->deleteDraft($gameTable->id, (string) $user->id);
        } catch (NotEditableException) {
            abort(403);
        }

        return $this->returnTarget
            ->redirect('gametables.my-tables', AuthorReturnTarget::TABLES_TAB)
            ->with('success', __('game-tables::messages.frontend.table_deleted'));
    }

    /**
     * The author of a table that is no longer theirs to change (sent to review or published) goes
     * back to «Mis mesas» with the reason instead of a bare 403. Anyone else still meets the policy.
     */
    private function sendLockedAuthorBack(GameTableModel $table): ?RedirectResponse
    {
        return $this->authorLock->forTable($table, (string) auth()->id());
    }

    /**
     * The {gameTable} of the author's routes (Mis mesas): found by its slug, the canonical form
     * of every link, or else by its id, which rejection emails sent before 2.8.0 carry. Who may
     * act on it is still decided by the policy.
     */
    private function findAuthorTable(string $key): GameTableModel
    {
        $table = GameTableModel::findBySlugOrId($key);

        if (! $table instanceof GameTableModel) {
            throw (new ModelNotFoundException)->setModel(GameTableModel::class, [$key]);
        }

        return $table;
    }
}
