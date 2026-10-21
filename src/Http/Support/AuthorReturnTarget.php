<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the author lands after acting on a draft (send to review, delete): «Mis mesas» /
 * «Mis campañas» by default, or the profile when the action came from its drafts list. Only the
 * whitelisted `return_to=profile` is honoured, never a URL, so it cannot become an open redirect.
 * The profile opens on the tab of the item's kind (`/perfil?tab=…`).
 */
final class AuthorReturnTarget
{
    public const PROFILE = 'profile';

    /** Profile tabs of the module (GameTablesServiceProvider's profile sections) */
    public const TABLES_TAB = 'gametables-created';

    public const CAMPAIGNS_TAB = 'gametables-campaigns';

    private const PROFILE_ROUTE = 'profile.show';

    public function __construct(private readonly Request $request) {}

    public function toProfile(): bool
    {
        return $this->request->input('return_to') === self::PROFILE;
    }

    public function redirect(string $defaultRoute, string $profileTab): RedirectResponse
    {
        return $this->toProfile() ? $this->profile($profileTab) : redirect()->route($defaultRoute);
    }

    /**
     * The profile when asked for, otherwise the previous page.
     */
    public function back(string $profileTab): RedirectResponse
    {
        return $this->toProfile() ? $this->profile($profileTab) : back();
    }

    private function profile(string $tab): RedirectResponse
    {
        return redirect()->route(self::PROFILE_ROUTE, ['tab' => $tab]);
    }
}
