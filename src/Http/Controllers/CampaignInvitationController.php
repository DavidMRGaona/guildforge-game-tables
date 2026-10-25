<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\CampaignInvitationViewDTO;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;

/**
 * The page behind the link of the invitation email. Viewing and rejecting need only the token, so
 * a person without an account can delete their email; accepting is an explicit click of an account
 * with the invited email (routes: auth and the verification alias). What the page shows depends on
 * the session, and another account never learns the invited email.
 */
final class CampaignInvitationController extends Controller
{
    /** Flashed by reject(): the page it redirects to shows the rejected state */
    private const string REJECTED_FLASH = 'game_tables_invitation_rejected';

    public function __construct(
        private readonly CampaignInvitationServiceInterface $invitations,
        private readonly EmailVerificationPolicyInterface $verification,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly AuthServiceInterface $auth,
    ) {}

    public function show(string $token): Response
    {
        $invitation = $this->invitations->viewByToken($token, now()->toDateTimeImmutable());

        if (!$invitation instanceof \Modules\GameTables\Application\DTOs\CampaignInvitationViewDTO) {
            // Right after rejecting, the page says so instead of calling the invitation invalid
            return $this->page(session()->get(self::REJECTED_FLASH) === true ? 'rejected' : 'invalid', null, $token);
        }

        $user = auth()->user();

        if (! $user instanceof UserModel) {
            return $this->page('guest', $invitation, $token);
        }

        if (mb_strtolower(trim($user->email)) !== $invitation->email) {
            return $this->page('mismatch', $invitation, $token);
        }

        return $this->page($this->verification->mustVerify($user) ? 'must_verify' : 'match', $invitation, $token);
    }

    /**
     * Remembers the invitation as the page to come back to, then sends the visitor to sign in.
     * An invalid token does the same: the invitation page it returns to says so.
     */
    public function login(string $token): RedirectResponse
    {
        return $this->viaAuthPage($token, 'login');
    }

    public function register(string $token): RedirectResponse
    {
        return $this->viaAuthPage($token, 'register');
    }

    /**
     * The account must verify before accepting: the core comes back to the invitation once verified.
     */
    public function verify(string $token): RedirectResponse
    {
        return $this->viaAuthPage($token, 'verification.notice');
    }

    public function accept(string $token): RedirectResponse
    {
        /** @var UserModel $user */
        $user = auth()->user();
        // The slug is read first: accepting deletes the invitation
        $invitation = $this->invitations->viewByToken($token, now()->toDateTimeImmutable());

        try {
            $this->invitations->accept($token, (string) $user->id);
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (RosterActionRefusedException $e) {
            return redirect()->route('campaigns.invitation.show', $token)->with('error', $e->getMessage());
        }

        return $invitation instanceof \Modules\GameTables\Application\DTOs\CampaignInvitationViewDTO
            ? redirect()->route('campaigns.show', $invitation->campaignSlug)
                ->with('success', __('game-tables::messages.roster.success.invitation_accepted'))
            : redirect()->route('campaigns.index')
                ->with('success', __('game-tables::messages.roster.success.invitation_accepted'));
    }

    /**
     * Signs out like the core does, but comes back to the invitation instead of the home page, so
     * the invitee can sign in with the invited account right away.
     */
    public function logout(Request $request, string $token): RedirectResponse
    {
        $this->auth->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('campaigns.invitation.show', $token);
    }

    public function reject(string $token): RedirectResponse
    {
        try {
            $this->invitations->reject($token);
        } catch (CampaignNotFoundException) {
            abort(404);
        } catch (RosterActionRefusedException $e) {
            return redirect()->route('campaigns.invitation.show', $token)->with('error', $e->getMessage());
        }

        return redirect()->route('campaigns.invitation.show', $token)
            ->with('success', __('game-tables::messages.roster.success.invitation_rejected'))
            ->with(self::REJECTED_FLASH, true);
    }

    /**
     * From the profile tab: the service checks that the invitation is addressed to the account's
     * verified email, whatever the tab showed.
     */
    public function profileAccept(string $invitationId): RedirectResponse
    {
        return $this->fromProfile(
            fn (string $userId) => $this->invitations->acceptById($invitationId, $userId),
            'invitation_accepted',
        );
    }

    public function profileReject(string $invitationId): RedirectResponse
    {
        return $this->fromProfile(
            fn (string $userId) => $this->invitations->rejectById($invitationId, $userId),
            'invitation_rejected',
        );
    }

    /**
     * The page without an invitation, with an error message. The answer over the rate limit.
     */
    public function limitedPage(string $message, string $token): Response
    {
        session()->now('error', $message);

        return $this->page('invalid', null, $token);
    }

    /**
     * @param  callable(string): void  $action
     */
    private function fromProfile(callable $action, string $successKey): RedirectResponse
    {
        /** @var UserModel $user */
        $user = auth()->user();
        $back = redirect('/perfil?tab=gametables-invitations');

        try {
            $action((string) $user->id);
        } catch (RosterActionRefusedException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('success', __('game-tables::messages.roster.success.'.$successKey));
    }

    private function viaAuthPage(string $token, string $route): RedirectResponse
    {
        session()->put('url.intended', route('campaigns.invitation.show', $token));

        return redirect()->route($route);
    }

    /**
     * Only what each state needs: another account gets no email, and nobody gets the invitation's
     * id or its send count.
     *
     * @param  'invalid'|'rejected'|'guest'|'match'|'mismatch'|'must_verify'  $state
     */
    private function page(string $state, ?CampaignInvitationViewDTO $invitation, string $token): Response
    {
        return Inertia::render('Campaigns/Invitation', [
            'state' => $state,
            'invitation' => $this->props($state, $invitation),
            'token' => $token,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.campaign_invitation_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * @return array{campaign_title: string, campaign_slug: string, inviter_name: string, email?: string, expires_at?: string}|null
     */
    private function props(string $state, ?CampaignInvitationViewDTO $invitation): ?array
    {
        if (!$invitation instanceof \Modules\GameTables\Application\DTOs\CampaignInvitationViewDTO || in_array($state, ['invalid', 'rejected'], true)) {
            return null;
        }

        $props = [
            'campaign_title' => $invitation->campaignTitle,
            'campaign_slug' => $invitation->campaignSlug,
            'inviter_name' => $invitation->inviterName,
        ];

        if ($state === 'mismatch') {
            return $props;
        }

        return $props + ['email' => $invitation->email, 'expires_at' => $invitation->expiresAt];
    }
}
