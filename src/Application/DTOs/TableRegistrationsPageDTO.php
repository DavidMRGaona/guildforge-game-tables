<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * The «Gestionar inscritos» page of a table: the pending requests first (oldest first), then the
 * confirmed, the waiting list in its order and the rest.
 *
 * @phpstan-import-type TableRegistrationRow from TableRegistrationRowDTO
 *
 * @phpstan-type TableRegistrationsTable array{title: string, slug: string, starts_at: string, auto_confirm: bool, max_players: int, confirmed_players: int}
 */
final readonly class TableRegistrationsPageDTO
{
    /**
     * @param  TableRegistrationsTable  $table
     * @param  list<TableRegistrationRowDTO>  $rows
     */
    public function __construct(
        public array $table,
        public array $rows,
        public int $pendingCount,
    ) {}

    /**
     * @return array{table: TableRegistrationsTable, rows: list<TableRegistrationRow>, pending_count: int}
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'rows' => array_map(static fn (TableRegistrationRowDTO $row): array => $row->toArray(), $this->rows),
            'pending_count' => $this->pendingCount,
        ];
    }
}
