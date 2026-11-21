<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    /**
     * Read a multi-valued filter of ids: only the values that are UUIDs are kept. Ids reach uuid
     * columns, where PostgreSQL refuses to compare anything else, so a malformed value is ignored
     * like any other malformed filter instead of failing the whole page.
     *
     * @return array<int, string>|null
     */
    protected function readUuidListFilter(Request $request, string $key): ?array
    {
        $ids = array_values(array_filter(
            $this->readMultiValueFilter($request, $key) ?? [],
            static fn (string $value): bool => Str::isUuid($value),
        ));

        return $ids !== [] ? $ids : null;
    }

    /**
     * Read a single id filter: null unless it is one UUID (see readUuidListFilter())
     */
    protected function readUuidFilter(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
