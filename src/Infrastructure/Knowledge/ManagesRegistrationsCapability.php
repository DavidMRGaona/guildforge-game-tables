<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * The person directs a published single table that delegates its registrations: the tables whose
 * registrations page they may open (GameTablePolicy::manageRegistrations).
 */
final readonly class ManagesRegistrationsCapability implements KnowledgeCapabilityInterface
{
    use ReadsUserId;

    public function __construct(private TableDirectionInterface $direction) {}

    public function key(): string
    {
        return 'game-tables.manages_registrations';
    }

    public function passes(object $user): bool
    {
        $userId = $this->userId($user);

        if ($userId === null) {
            return false;
        }

        $directed = $this->direction->tableIdsDirectedBy($userId);

        return $directed !== [] && GameTableModel::query()
            ->whereKey($directed)
            ->whereNull('campaign_id')
            ->where('delegates_registrations', true)
            ->where('is_published', true)
            ->exists();
    }
}
