<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\WarningSeverity;
use Modules\GameTables\Domain\ValueObjects\ContentWarningId;

final class ContentWarning
{
    public function __construct(
        public readonly ContentWarningId $id,
        public string $name,
        public string $label,
        public WarningSeverity $severity,
        public bool $isActive,
        public ?string $description = null,
        public ?string $icon = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function updateInfo(
        string $name,
        string $label,
        ?string $description,
        WarningSeverity $severity,
        ?string $icon = null,
    ): void {
        $this->name = $name;
        $this->label = $label;
        $this->description = $description;
        $this->severity = $severity;
        $this->icon = $icon;
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

    public function isSevere(): bool
    {
        return $this->severity === WarningSeverity::Severe;
    }
}
