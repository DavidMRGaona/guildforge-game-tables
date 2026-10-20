<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Http\RedirectResponse;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Http\Requests\RequestToJoinCampaignRequest;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

/**
 * The player's side of the roster, from the campaign page: ask to join, withdraw the request,
 * leave. The service applies every rule (and takes its own lock): nothing is wrapped here.
 */
final class CampaignJoinController extends Controller
{
    public function __construct(
        private readonly CampaignRosterServiceInterface $roster,
    ) {}

    public function request(RequestToJoinCampaignRequest $request, string $slug): RedirectResponse
    {
        $campaignId = $this->campaignId($slug);
        $message = $request->validated('message');

        return $this->attempt(
            fn () => $this->roster->requestToJoin($campaignId, $this->userId(), is_string($message) ? $message : null),
            'requested',
        );
    }

    public function withdraw(string $slug): RedirectResponse
    {
        $campaignId = $this->campaignId($slug);

        return $this->attempt(fn () => $this->roster->withdrawRequest($campaignId, $this->userId()), 'withdrawn');
    }

    public function leave(string $slug): RedirectResponse
    {
        $campaignId = $this->campaignId($slug);

        return $this->attempt(fn () => $this->roster->leave($campaignId, $this->userId()), 'left', [
            // The service speaks of "that person": here it is the player themselves
            'not_member' => 'not_member_self',
        ]);
    }

    private function campaignId(string $slug): string
    {
        $campaign = CampaignModel::query()->where('slug', $slug)->first();

        if ($campaign === null) {
            abort(404);
        }

        return $campaign->id;
    }

    /**
     * @param  Closure(): void  $action
     * @param  array<string, string>  $ownCopy  refusal reasons answered with another roster error key
     */
    private function attempt(Closure $action, string $success, array $ownCopy = []): RedirectResponse
    {
        try {
            $action();
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (RosterActionRefusedException $e) {
            return back()->with('error', isset($ownCopy[$e->reason])
                ? __('game-tables::messages.roster.errors.'.$ownCopy[$e->reason])
                : $e->getMessage());
        }

        return back()->with('success', __('game-tables::messages.roster.success.'.$success));
    }

    private function userId(): string
    {
        /** @var UserModel $user */
        $user = auth()->user();

        return (string) $user->id;
    }
}
