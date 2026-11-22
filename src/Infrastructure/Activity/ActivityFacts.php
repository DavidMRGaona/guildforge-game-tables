<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Activity;

use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Domain\Activity\ActivityRole;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * The facts of one session or campaign: one per account and role (somebody may hold several, a
 * creator who also ran it), all at the same moment and with the same attributes. Anything that is
 * not an account id (a guest, a game master without an account) is left out.
 */
final class ActivityFacts
{
    /**
     * @param  array<string, list<string|null>>  $accountsByRole  ActivityRole value → account ids
     * @param  array<string, ActivityAttributeValueDTO>  $attributes
     * @return list<ActivityFactDTO>
     */
    public static function of(array $accountsByRole, DateTimeImmutable $occurredAt, array $attributes): array
    {
        $facts = [];

        foreach ($accountsByRole as $role => $userIds) {
            $accounts = array_unique(array_map(
                strtolower(...),
                array_filter($userIds, static fn (?string $id): bool => is_string($id) && Str::isUuid($id)),
            ));

            foreach ($accounts as $userId) {
                $facts[] = new ActivityFactDTO($userId, ActivityRole::from($role), $occurredAt, $attributes);
            }
        }

        return $facts;
    }
}
