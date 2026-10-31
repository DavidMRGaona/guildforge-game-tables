<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use App\Application\Surveys\Contracts\SurveySubjectLinksInterface;
use App\Application\Surveys\DTOs\SurveyLinkDTO;

/**
 * What a signed-in person reaches from a table, session or campaign page, through the core's
 * contract: the subject's surveys page when they manage it, and the surveys sent to them. A
 * surveys module answers; without one (the core's null contract) the page gets null.
 */
final readonly class SubjectSurveyLinks
{
    public function __construct(
        private SurveySubjectLinksInterface $links,
    ) {}

    /**
     * @return array{manageUrl: string|null, surveys: list<array{title: string, url: string, action: string}>}|null
     */
    public function for(string $sourceType, string $subjectId, string $userId): ?array
    {
        $links = $this->links->forSubject($sourceType, $subjectId, $userId);
        if ($links->isEmpty()) {
            return null;
        }

        return [
            'manageUrl' => $links->manageUrl,
            'surveys' => array_map(static fn (SurveyLinkDTO $link): array => [
                'title' => $link->title,
                'url' => $link->url,
                'action' => $link->action->value,
            ], $links->surveys),
        ];
    }
}
