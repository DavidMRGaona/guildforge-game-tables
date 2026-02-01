<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Http\Requests\FrontendCreateGameTableRequest;

final class FrontendGameTableController extends Controller
{
    public function __construct(
        private readonly FrontendCreationServiceInterface $creationService,
        private readonly CreationEligibilityServiceInterface $eligibilityService,
    ) {}

    /**
     * Show the table creation form.
     */
    public function create(): Response
    {
        $user = auth()->user();

        // Check eligibility
        $eligibility = $this->eligibilityService->canCreateTable($user?->id);

        if (! $eligibility->eligible) {
            // Show a page explaining why they can't create
            return Inertia::render('GameTables/CreateNotEligible', [
                'reason' => $eligibility->reason,
                'canCreateAt' => $eligibility->canCreateAt?->format('c'),
            ]);
        }

        // Get form data
        $formData = $this->creationService->getCreateFormData();

        return Inertia::render('GameTables/Create', [
            'formData' => $formData,
            'eligibility' => $eligibility->toArray(),
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
            'tables' => $tables,
            'canCreate' => $eligibility->eligible,
        ]);
    }

    /**
     * Show edit form for a draft table.
     */
    public function edit(string $id): Response
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\User $user */
        $user = auth()->user();

        // Get user's drafts and find the one with matching ID
        $drafts = $this->creationService->getUserDrafts((string) $user->id);
        $table = null;

        foreach ($drafts as $draft) {
            if ($draft->id === $id) {
                $table = $draft;
                break;
            }
        }

        if ($table === null) {
            abort(404);
        }

        // Get form data for the edit form
        $formData = $this->creationService->getCreateFormData();

        return Inertia::render('GameTables/Edit', [
            'table' => $table->toArray(),
            'formData' => $formData,
        ]);
    }

    /**
     * Update a draft table.
     */
    public function update(string $id, FrontendCreateGameTableRequest $request): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\User $user */
        $user = auth()->user();

        $dto = FrontendCreateGameTableDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        $this->creationService->updateDraft($id, $dto);

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.table_updated'));
    }

    /**
     * Submit a draft for review.
     */
    public function submitForReview(string $id): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\User $user */
        $user = auth()->user();

        $this->creationService->submitForReview($id, (string) $user->id);

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.submitted_for_review'));
    }

    /**
     * Delete a draft table.
     */
    public function destroy(string $id): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\User $user */
        $user = auth()->user();

        $this->creationService->deleteDraft($id, (string) $user->id);

        return redirect()
            ->route('gametables.my-tables')
            ->with('success', __('game-tables::messages.frontend.table_deleted'));
    }
}
