<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventCreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Http\Requests\FrontendCreateGameTableRequest;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class FrontendGameTableController extends Controller
{
    public function __construct(
        private readonly FrontendCreationServiceInterface $creationService,
        private readonly CreationEligibilityServiceInterface $eligibilityService,
        private readonly EventCreationEligibilityServiceInterface $eventEligibilityService,
        private readonly EventGameTableConfigServiceInterface $eventConfigService,
        private readonly GameTableServiceInterface $gameTableService,
    ) {}

    /**
     * Show the table creation form.
     */
    public function create(Request $request): Response
    {
        $user = auth()->user();
        $eventSlug = $request->query('event');

        // Resolve event by slug if provided
        $event = null;
        if ($eventSlug !== null && is_string($eventSlug)) {
            $event = EventModel::where('slug', $eventSlug)->first();
        }
        $eventId = $event?->id;

        // Check eligibility - use event-specific if event ID provided
        if ($eventId !== null) {
            $eligibility = $this->eventEligibilityService->canCreateTableForEvent($eventId, $user?->id);
        } else {
            $eligibility = $this->eligibilityService->canCreateTable($user?->id);
        }

        if (! $eligibility->eligible) {
            // Show a page explaining why they can't create
            return Inertia::render('GameTables/CreateNotEligible', [
                'reason' => $eligibility->reason,
                'canCreateAt' => $eligibility->canCreateAt?->format('c'),
            ]);
        }

        // Get form data
        $formData = $this->creationService->getCreateFormData();

        // Get event context if creating table for a specific event
        $eventContext = null;
        if ($eventId !== null) {
            $eventContext = $this->eventConfigService->getCreationContext($eventId);
        }

        // Prepare current user data for GM section
        $currentUser = $user !== null ? [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null;

        return Inertia::render('GameTables/Create', [
            'formData' => $formData,
            'eligibility' => $eligibility->toArray(),
            'eventContext' => $eventContext?->toArray(),
            'currentUser' => $currentUser,
        ]);
    }

    /**
     * Store a new table.
     */
    public function store(FrontendCreateGameTableRequest $request): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\User $user */
        $user = auth()->user();

        $dto = FrontendCreateGameTableDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        $this->creationService->createGameTable($dto);

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.table_created'));
    }

    /**
     * Show user's tables (drafts, pending review, rejected).
     */
    public function myTables(): Response
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\User $user */
        $user = auth()->user();
        $tables = $this->creationService->getUserDrafts((string) $user->id);

        $eligibility = $this->eligibilityService->canCreateTable((string) $user->id);

        return Inertia::render('GameTables/MyTables', [
            'tables' => array_map(
                fn (GameTableResponseDTO $dto) => $dto->toArray(),
                $tables
            ),
            'canCreate' => $eligibility->eligible,
        ]);
    }

    /**
     * Show edit form for a draft table.
     */
    public function edit(GameTableModel $gameTable): Response
    {
        $this->authorize('update', $gameTable);

        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        // Get the table DTO using the service (consistent with architecture)
        $table = $this->gameTableService->findOrFail($gameTable->id);

        // Get form data for the edit form
        $formData = $this->creationService->getCreateFormData();

        // Get event context if table is associated with an event
        $eventContext = null;
        if ($gameTable->event_id !== null) {
            $eventContext = $this->eventConfigService->getCreationContext($gameTable->event_id);
        }

        // Prepare current user data for GM section
        $currentUser = [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return Inertia::render('GameTables/Edit', [
            'table' => $table->toArray(),
            'formData' => $formData,
            'eventContext' => $eventContext?->toArray(),
            'currentUser' => $currentUser,
        ]);
    }

    /**
     * Update a draft table.
     */
    public function update(GameTableModel $gameTable, FrontendCreateGameTableRequest $request): RedirectResponse
    {
        $this->authorize('update', $gameTable);

        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        $dto = FrontendCreateGameTableDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        $this->creationService->updateDraft($gameTable->id, $dto);

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.table_updated'));
    }

    /**
     * Submit a draft for review.
     */
    public function submitForReview(GameTableModel $gameTable): RedirectResponse
    {
        $this->authorize('submitForReview', $gameTable);

        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        $this->creationService->submitForReview($gameTable->id, (string) $user->id);

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.submitted_for_review'));
    }

    /**
     * Delete a draft table.
     */
    public function destroy(GameTableModel $gameTable): RedirectResponse
    {
        $this->authorize('delete', $gameTable);

        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        $this->creationService->deleteDraft($gameTable->id, (string) $user->id);

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.table_deleted'));
    }
}
