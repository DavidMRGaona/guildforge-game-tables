<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Application\DTOs;

use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpdateEventGameTableConfigDTOTest extends TestCase
{
    /**
     * @return array<string, array{int|string|null, int|null}>
     */
    public static function maxTablesFromTheForm(): array
    {
        return [
            // The form sends the field as a string, and an empty one when it is left blank
            'empty' => ['', null],
            'not sent' => [null, null],
            'typed' => ['3', 3],
            'number' => [4, 4],
        ];
    }

    #[DataProvider('maxTablesFromTheForm')]
    public function test_a_blank_maximum_of_tables_per_slot_means_no_limit(int|string|null $maxTables, ?int $expected): void
    {
        $dto = UpdateEventGameTableConfigDTO::fromArray([
            'event_id' => 'event-1',
            'time_slots' => [[
                'label' => 'Mañana',
                'start_time' => '2026-11-07 10:00:00',
                'end_time' => '2026-11-07 14:00:00',
                'max_tables' => $maxTables,
            ]],
        ]);

        $this->assertCount(1, $dto->timeSlots);
        $this->assertSame($expected, $dto->timeSlots[0]->maxTables);
    }
}
