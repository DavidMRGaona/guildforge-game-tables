<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\Services\RegistrationServiceInterface;

final readonly class ProfileGameTablesDataProvider
{
    public function __construct(
        private RegistrationServiceInterface $registrationService,
    ) {}

    /**
     * Get game tables data for a user's profile page.
     *
     * @return array{upcoming: array<array<string, mixed>>, past: array<array<string, mixed>>, total: int}|null
     */
    public function getDataForUser(?string $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        $participations = $this->registrationService->getByUserForProfile($userId);

        if (count($participations) === 0) {
            return null;
        }

        $upcoming = [];
        $past = [];

        foreach ($participations as $participation) {
            if ($participation->isUpcoming) {
                $upcoming[] = $participation->toArray();
            } else {
                $past[] = $participation->toArray();
            }
        }

        return [
            'upcoming' => $upcoming,
            'past' => $past,
            'total' => count($participations),
        ];
    }
}
