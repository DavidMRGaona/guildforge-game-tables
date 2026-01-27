<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Entities\ContentWarning;

final readonly class ContentWarningDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $label,
        public string $severity,
        public string $severityLabel,
        public string $severityColor,
        public bool $isActive,
        public ?string $description,
        public ?string $icon,
        public ?string $createdAt,
    ) {
    }

    public static function fromEntity(ContentWarning $contentWarning): self
    {
        return new self(
            id: $contentWarning->id->value,
            name: $contentWarning->name,
            label: $contentWarning->label,
            severity: $contentWarning->severity->value,
            severityLabel: $contentWarning->severity->label(),
            severityColor: $contentWarning->severity->color(),
            isActive: $contentWarning->isActive,
            description: $contentWarning->description,
            icon: $contentWarning->icon,
            createdAt: $contentWarning->createdAt?->format('c'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'severity' => $this->severity,
            'severity_label' => $this->severityLabel,
            'severity_color' => $this->severityColor,
            'is_active' => $this->isActive,
            'description' => $this->description,
            'icon' => $this->icon,
            'created_at' => $this->createdAt,
        ];
    }
}
