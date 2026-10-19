<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use Illuminate\Http\Request;

/**
 * A keyed hash of the connection (IP and browser) a web registration came from, for the
 * possible-duplicate signal of the panel. Never stored for registrations made from the panel.
 */
final class GuestFingerprint
{
    public static function of(Request $request): string
    {
        return hash_hmac('sha256', ($request->ip() ?? '').'|'.($request->userAgent() ?? ''), (string) config('app.key'));
    }
}
