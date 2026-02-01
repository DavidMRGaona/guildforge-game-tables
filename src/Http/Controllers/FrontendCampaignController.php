<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\FrontendCreateCampaignDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Http\Requests\FrontendCreateCampaignRequest;

final class FrontendCampaignController extends Controller
{
    public function __construct(
        private readonly FrontendCreationServiceInterface $creationService,
        private readonly CreationEligibilityServiceInterface $eligibilityService,
    ) {}

    /**
     * Show the campaign creation form.
     */
    public function create(): Response
    {
        $user = auth()->user();

        // Check eligibility
        $eligibility = $this->eligibilityService->canCreateCampaign($user?->id);

        if (! $eligibility->eligible) {
            return Inertia::render('Campaigns/CreateNotEligible', [
                'reason' => $eligibility->reason,
                'canCreateAt' => $eligibility->canCreateAt?->format('c'),
            ]);
        }

        // Get form data
        $formData = $this->creationService->getCreateFormData();

        return Inertia::render('Campaigns/Create', [
            'formData' => $formData,
            'eligibility' => $eligibility->toArray(),
        ]);
    }

    /**
     * Store a new campaign.
     */
    public function store(FrontendCreateCampaignRequest $request): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        $dto = FrontendCreateCampaignDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        $this->creationService->createCampaign($dto);

        return redirect()
            ->route('campaigns.my-campaigns')
            ->with('success', __('game-tables::messages.frontend.campaign_created'));
    }

    /**
     * Show user's campaigns (drafts, pending review, rejected).
     */
    public function myCampaigns(): Response
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();
        $campaigns = $this->creationService->getUserCampaignDrafts((string) $user->id);

        $eligibility = $this->eligibilityService->canCreateCampaign((string) $user->id);

        return Inertia::render('Campaigns/MyCampaigns', [
            'campaigns' => $campaigns,
            'canCreate' => $eligibility->eligible,
        ]);
    }

    /**
     * Show edit form for a draft campaign.
     */
    public function edit(string $id): Response
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        // Get user's drafts and find the one with matching ID
        $drafts = $this->creationService->getUserCampaignDrafts((string) $user->id);
        $campaign = null;

        foreach ($drafts as $draft) {
            if ($draft->id === $id) {
                $campaign = $draft;
                break;
            }
        }

        if ($campaign === null) {
            abort(404);
        }

        // Get form data for the edit form
        $formData = $this->creationService->getCreateFormData();

        return Inertia::render('Campaigns/Edit', [
            'campaign' => $campaign->toArray(),
            'formData' => $formData,
        ]);
    }

    /**
     * Update a draft campaign.
     */
    public function update(string $id, FrontendCreateCampaignRequest $request): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        $dto = FrontendCreateCampaignDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        $this->creationService->updateCampaignDraft($id, $dto);

        return redirect()
            ->route('campaigns.my-campaigns')
            ->with('success', __('game-tables::messages.frontend.campaign_updated'));
    }

    /**
     * Submit a draft for review.
     */
    public function submitForReview(string $id): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        $this->creationService->submitCampaignForReview($id, (string) $user->id);

        return redirect()
            ->route('campaigns.my-campaigns')
            ->with('success', __('game-tables::messages.frontend.submitted_for_review'));
    }

    /**
     * Delete a draft campaign.
     */
    public function destroy(string $id): RedirectResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\Models\UserModel $user */
        $user = auth()->user();

        $this->creationService->deleteCampaignDraft($id, (string) $user->id);

        return redirect()
            ->route('campaigns.my-campaigns')
            ->with('success', __('game-tables::messages.frontend.campaign_deleted'));
    }
}
