<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use App\Application\Discussions\Contracts\SubjectDiscussionInterface;
use App\Application\Services\TimezoneServiceInterface;
use Throwable;

/**
 * The activity of a table's or campaign's forum discussion for its page, through the core's
 * contract: a forum module answers; without one (the core's null contract), or when it fails, the
 * page gets null and shows the same without the block.
 */
final readonly class SubjectDiscussionActivity
{
    public function __construct(
        private SubjectDiscussionInterface $discussions,
        private TimezoneServiceInterface $timezone,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function for(string $type, string $id, ?string $userId): ?array
    {
        try {
            return $this->discussions->forSubject($type, $id, $userId)?->toArray($this->timezone);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
