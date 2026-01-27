<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;

final class GameSystem
{
    public function __construct(
        public readonly GameSystemId $id,
        public string $name,
        public string $slug,
        public bool $isActive,
        public ?string $description = null,
        public ?string $publisher = null,
        public ?string $edition = null,
        public ?int $year = null,
        public ?string $logoUrl = null,
        public ?string $websiteUrl = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function updateInfo(
        string $name,
        string $slug,
        ?string $description = null,
        ?string $publisher = null,
        ?string $edition = null,
        ?int $year = null,
    ): void {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->publisher = $publisher;
        $this->edition = $edition;
        $this->year = $year;
    }

    public function updateMedia(?string $logoUrl = null, ?string $websiteUrl = null): void
    {
        $this->logoUrl = $logoUrl;
        $this->websiteUrl = $websiteUrl;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
