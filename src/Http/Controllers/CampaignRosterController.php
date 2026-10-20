<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\Services\CampaignRosterQueryInterface;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;
use Modules\GameTables\Http\Requests\AddRosterPlayerRequest;
use Modules\GameTables\Http\Requests\RejectJoinRequestRequest;
use Modules\GameTables\Http\Requests\ToggleRosterAbsenceRequest;
use Modules\GameTables\Http\Requests\UpdateRosterSettingsRequest;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * The direction's roster page and its actions. Every route loads the campaign by id (404) and
 * asks the policy (manageRoster, 403): the roster service does not check who acts. The service
 * calls take their own locks and dispatch after commit, so none is wrapped in a transaction here.
 */
final class CampaignRosterController extends Controller
{
    public function __construct(
        private readonly CampaignRosterServiceInterface $roster,
        private readonly CampaignRosterSessionSyncInterface $sessions,
        private readonly CampaignRosterQueryInterface $query,
        private readonly CampaignJoinRequestRepositoryInterface $requests,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(string $id): Response
    {
        $campaign = $this->authorizedCampaign($id);
        $page = $this->query->rosterPage($campaign->id, CarbonImmutable::now()->toDateTimeImmutable());

        return Inertia::render('Campaigns/Roster', [
            ...$page->toArray(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.roster_title', ['title' => $campaign->title]),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function updateSettings(UpdateRosterSettingsRequest $request, string $id): RedirectResponse
    {
        $campaign = $this->authorizedCampaign($id);
        $maxPlayers = $request->validated('max_players');

        return $this->attempt(fn () => $this->roster->updateSettings(
            $campaign->id,
            $request->boolean('accepts_new_players'),
            RosterAccess::from((string) $request->validated('roster_access')),
            $maxPlayers !== null ? (int) $maxPlayers : null,
        ), 'settings_saved');
    }

    public function add(AddRosterPlayerRequest $request, string $id): RedirectResponse
    {
        $campaign = $this->authorizedCampaign($id);

        return $this->attempt(
            fn () => $this->roster->addByEmail($campaign->id, (string) $request->validated('email'), $this->actorId()),
            'added',
        );
    }

    public function remove(string $id, string $userId): RedirectResponse
    {
        $campaign = $this->authorizedCampaign($id);

        if (! Str::isUuid($userId)) {
            abort(404);
        }

        return $this->attempt(
            fn () => $this->roster->removeMember($campaign->id, $userId, $this->actorId()),
            'removed',
        );
    }

    public function accept(string $id, string $requestId): RedirectResponse
    {
        $campaign = $this->authorizedCampaign($id);
        $this->ensureRequestOf($campaign, $requestId);

        return $this->attempt(fn () => $this->roster->acceptRequest($requestId, $this->actorId()), 'accepted');
    }

    public function reject(RejectJoinRequestRequest $request, string $id, string $requestId): RedirectResponse
    {
        $campaign = $this->authorizedCampaign($id);
        $this->ensureRequestOf($campaign, $requestId);
        $reason = $request->validated('reason');

        return $this->attempt(
            fn () => $this->roster->rejectRequest($requestId, $this->actorId(), is_string($reason) ? $reason : null),
            'rejected',
        );
    }

    public function toggleAbsence(ToggleRosterAbsenceRequest $request, string $id): RedirectResponse
    {
        $campaign = $this->authorizedCampaign($id);
        $gameTableId = (string) $request->validated('game_table_id');
        $userId = (string) $request->validated('user_id');

        return $request->boolean('absent')
            ? $this->attempt(fn () => $this->sessions->markAbsence($campaign->id, $gameTableId, $userId), 'absence_marked')
            : $this->attempt(fn () => $this->sessions->clearAbsence($campaign->id, $gameTableId, $userId), 'absence_cleared');
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 404 for an unknown campaign, 403 when the user does not direct it
     */
    private function authorizedCampaign(string $id): CampaignModel
    {
        $campaign = Str::isUuid($id) ? CampaignModel::query()->find($id) : null;

        if ($campaign === null) {
            abort(404);
        }

        Gate::authorize('manageRoster', $campaign);

        return $campaign;
    }

    /**
     * A request of another campaign is not found here, so the policy of one campaign never
     * decides on another's requests.
     */
    private function ensureRequestOf(CampaignModel $campaign, string $requestId): void
    {
        $request = Str::isUuid($requestId) ? $this->requests->find(new JoinRequestId($requestId)) : null;

        if ($request === null || $request->campaignId->value !== $campaign->id) {
            abort(404);
        }
    }

    /**
     * Runs the roster action and answers with its success, or with the refusal it met.
     *
     * @param  Closure(): void  $action
     */
    private function attempt(Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (RosterActionRefusedException|RegistrationNotAllowedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (TableFullException $e) {
            return back()->with('error', __('game-tables::messages.errors.'.$e->reason));
        }

        return back()->with('success', __('game-tables::messages.roster.success.'.$success));
    }

    private function actorId(): string
    {
        /** @var UserModel $user */
        $user = auth()->user();

        return (string) $user->id;
    }
}
