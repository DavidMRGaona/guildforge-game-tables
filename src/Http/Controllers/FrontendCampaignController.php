<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Application\DTOs\FrontendCreateCampaignDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Http\Requests\FrontendCreateCampaignRequest;

final class FrontendCampaignController extends Controller
{
    public function __construct(
        private readonly FrontendCreationServiceInterface $creationService,
        private readonly CreationEligibilityServiceInterface $eligibilityService,
        private readonly SeoMetadataFactoryInterface $seo,
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
                'seo' => $this->seo->forPage(new SeoPageDTO(
                    title: (string) __('game-tables::messages.seo.create_campaign_title'),
                    noindex: true,
                ))->toArray(),
            ]);
        }

        // Get form data
        $formData = $this->creationService->getCreateFormData();

        return Inertia::render('Campaigns/Create', [
            'formData' => $formData,
            'eligibility' => $eligibility->toArray(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.create_campaign_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Store a new campaign.
     */
    public function store(FrontendCreateCampaignRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
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
        /** @var UserModel $user */
        $user = auth()->user();
        $campaigns = $this->creationService->getUserCampaignDrafts((string) $user->id);

        $eligibility = $this->eligibilityService->canCreateCampaign((string) $user->id);

        return Inertia::render('Campaigns/MyCampaigns', [
            'campaigns' => $campaigns,
            'canCreate' => $eligibility->eligible,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.my_campaigns_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Show edit form for a draft campaign.
     */
    public function edit(string $id): Response
    {
        /** @var UserModel $user */
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
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.edit_campaign_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Update a draft campaign.
     */
    public function update(string $id, FrontendCreateCampaignRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
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
        /** @var UserModel $user */
        $user = auth()->user();

        try {
            $this->creationService->submitCampaignForReview($id, (string) $user->id);
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (NotEditableException) {
            // Not the author's, or not a draft or rejected campaign (as the table policy answers)
            abort(403);
        }

        return redirect()
            ->route('campaigns.my-campaigns')
            ->with('success', __('game-tables::messages.frontend.submitted_for_review'));
    }

    /**
     * Delete a draft campaign.
     */
    public function destroy(string $id): RedirectResponse
    {
        /** @var UserModel $user */
        $user = auth()->user();

        $this->creationService->deleteCampaignDraft($id, (string) $user->id);

        return redirect()
            ->route('campaigns.my-campaigns')
            ->with('success', __('game-tables::messages.frontend.campaign_deleted'));
    }
}
