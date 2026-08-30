<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Concerns;

use Illuminate\Http\Request;

trait ReadsListFilters
{
    /**
     * Read a multi-valued listing filter.
     *
     * Both shapes are accepted: the comma-separated form the listings link to
     * (`?systems=id1,id2`) and the bracketed array a client may send instead
     * (`?systems[]=id1&systems[]=id2`). Accepting only one of them meant an
     * unrecognised shape was dropped without a trace, and the listing silently
     * returned everything.
     *
     * Returns null when nothing usable is present, which is what the query
     * services read as "do not filter by this".
     *
     * @return array<int, string>|null
     */
    protected function readMultiValueFilter(Request $request, string $key): ?array
    {
        $raw = $request->query($key);

        $candidates = match (true) {
            is_string($raw) => explode(',', $raw),
            is_array($raw) => $raw,
            default => [],
        };

        $values = [];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values !== [] ? $values : null;
    }
}
