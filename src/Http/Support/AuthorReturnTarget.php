<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the author lands after acting on a draft (send to review, delete): «Mis mesas» /
 * «Mis campañas» by default, or the profile when the action came from its drafts list. Only the
 * whitelisted `return_to=profile` is honoured, never a URL, so it cannot become an open redirect.
 * The profile keeps its active tab on the client (the visit preserves the page's state).
 */
final class AuthorReturnTarget
{
    public const PROFILE = 'profile';

    private const PROFILE_ROUTE = 'profile.show';

    public function __construct(private readonly Request $request) {}

    public function toProfile(): bool
    {
        return $this->request->input('return_to') === self::PROFILE;
    }

    public function redirect(string $defaultRoute): RedirectResponse
    {
        return redirect()->route($this->toProfile() ? self::PROFILE_ROUTE : $defaultRoute);
    }

    /**
     * The profile when asked for, otherwise the previous page.
     */
    public function back(): RedirectResponse
    {
        return $this->toProfile() ? redirect()->route(self::PROFILE_ROUTE) : back();
    }
}
