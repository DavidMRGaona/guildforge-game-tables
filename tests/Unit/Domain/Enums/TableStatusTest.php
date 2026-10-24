<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\TableStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TableStatusTest extends TestCase
{
    /**
     * @return array<string, array{?TableStatus, list<TableStatus>}>
     */
    public static function manualChoices(): array
    {
        return [
            'a new table' => [null, [TableStatus::Scheduled, TableStatus::Full]],
            'draft' => [TableStatus::Draft, [TableStatus::Draft, TableStatus::Scheduled]],
            'scheduled' => [TableStatus::Scheduled, [TableStatus::Scheduled, TableStatus::Full]],
            'full' => [TableStatus::Full, [TableStatus::Scheduled, TableStatus::Full]],
            'in progress' => [TableStatus::InProgress, [TableStatus::InProgress]],
            'completed' => [TableStatus::Completed, [TableStatus::Completed]],
            'cancelled' => [TableStatus::Cancelled, [TableStatus::Cancelled]],
        ];
    }

    /**
     * @param  list<TableStatus>  $expected
     */
    #[DataProvider('manualChoices')]
    public function test_by_hand_a_table_keeps_its_status_or_moves_between_scheduled_and_full(?TableStatus $current, array $expected): void
    {
        $this->assertSame($expected, TableStatus::manualChoicesFrom($current));
    }
}
