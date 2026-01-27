<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;

final class GameTableCountController extends Controller
{
    public function __construct(
        private readonly GameTableQueryServiceInterface $queryService,
    ) {}

    public function count(Request $request): JsonResponse
    {
        $request->validate([
            'event' => ['required', 'uuid'],
        ]);

        $eventId = $request->query('event');

        $count = $this->queryService->getPublishedTablesTotal(
            eventId: is_string($eventId) ? $eventId : null,
        );

        return response()->json(['count' => $count]);
    }
}
