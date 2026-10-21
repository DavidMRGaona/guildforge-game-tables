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
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Http\Requests\FrontendCreateCampaignRequest;
use Modules\GameTables\Http\Support\AuthorLockRedirect;
use Modules\GameTables\Http\Support\AuthorReturnTarget;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final class FrontendCampaignController extends Controller
{
    public function __construct(
        private readonly FrontendCreationServiceInterface $creationService,
        private readonly CreationEligibilityServiceInterface $eligibilityService,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly AuthorLockRedirect $authorLock,
        private readonly AuthorReturnTarget $returnTarget,
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
     * Show user's campaigns: drafts, pending review, rejected and the ones they direct.
     */
    public function myCampaigns(): Response
    {
        /** @var UserModel $user */
        $user = auth()->user();
        $campaigns = $this->creationService->getMyCampaigns((string) $user->id);

        $eligibility = $this->eligibilityService->canCreateCampaign((string) $user->id);

        return Inertia::render('Campaigns/MyCampaigns', [
            // As arrays: the page reads the snake_case keys of toArray(), not the DTO properties
            'campaigns' => array_map(
                static fn (CampaignResponseDTO $dto): array => $dto->toArray(),
                $campaigns,
            ),
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
    public function edit(string $id): Response|RedirectResponse
    {
        if (($locked = $this->sendLockedAuthorBack(CampaignModel::query()->find($id))) !== null) {
            return $locked;
        }

        /** @var UserModel $user */
        $user = auth()->user();

        // Only the author's own drafts: anyone else gets 404, so the page never reveals that a campaign exists
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
        if (($locked = $this->sendLockedAuthorBack(CampaignModel::query()->find($id))) !== null) {
            return $locked;
        }

        /** @var UserModel $user */
        $user = auth()->user();

        $dto = FrontendCreateCampaignDTO::fromArray([
            ...$request->validated(),
            'created_by' => (string) $user->id,
        ]);

        try {
            $this->creationService->updateCampaignDraft($id, $dto);
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (NotEditableException) {
            // Not the author's, or not an unpublished draft or rejected campaign (as the table policy answers)
            abort(403);
        }

        return redirect()
            ->route('campaigns.my-campaigns')
            ->with('success', __('game-tables::messages.frontend.campaign_updated'));
    }

    /**
     * Submit a draft for review.
     */
    public function submitForReview(string $id): RedirectResponse
    {
        if (($locked = $this->sendLockedAuthorBack(CampaignModel::query()->find($id))) !== null) {
            return $locked;
        }

        /** @var UserModel $user */
        $user = auth()->user();

        try {
            $this->creationService->submitCampaignForReview($id, (string) $user->id);
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (NotEditableException) {
            // Not the author's, or not an unpublished draft or rejected campaign (as the table policy answers)
            abort(403);
        }

        // Without review (automatic mode, or an author who may publish directly) it is already published
        $published = (bool) CampaignModel::query()->find($id)?->is_published;

        return $this->returnTarget
            ->redirect('campaigns.my-campaigns', AuthorReturnTarget::CAMPAIGNS_TAB)
            ->with('success', __($published
                ? 'game-tables::messages.frontend.campaign_published'
                : 'game-tables::messages.frontend.campaign_submitted_for_review'));
    }

    /**
     * Delete a draft campaign.
     */
    public function destroy(string $id): RedirectResponse
    {
        if (($locked = $this->sendLockedAuthorBack(CampaignModel::query()->find($id))) !== null) {
            return $locked;
        }

        /** @var UserModel $user */
        $user = auth()->user();

        try {
            $this->creationService->deleteCampaignDraft($id, (string) $user->id);
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (NotEditableException) {
            // Not the author's, or not an unpublished draft or rejected campaign (as the table policy answers)
            abort(403);
        }

        return $this->returnTarget
            ->redirect('campaigns.my-campaigns', AuthorReturnTarget::CAMPAIGNS_TAB)
            ->with('success', __('game-tables::messages.frontend.campaign_deleted'));
    }

    /**
     * The author of a campaign that is no longer theirs to change (sent to review or published)
     * goes back to «Mis campañas» with the reason instead of a bare 403, as on tables. Anyone else
     * still meets the usual refusal.
     */
    private function sendLockedAuthorBack(?CampaignModel $campaign): ?RedirectResponse
    {
        return $this->authorLock->forCampaign($campaign, (string) auth()->id());
    }
}
