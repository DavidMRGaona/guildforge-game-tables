<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * A published table or campaign session the person created or directs, as «Mis mesas» lists it.
 */
final readonly class MyPublishedTableDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public ?string $startsAt,
        public string $status,
        public string $statusLabel,
        public string $statusColor,
        public bool $managesRegistrations,
        // Pending requests; null when the person does not manage the registrations
        public ?int $pendingRegistrations,
        // The campaign of a session, to link to it; null for a single table
        public ?string $campaignTitle = null,
        public ?string $campaignSlug = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'starts_at' => $this->startsAt,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'status_color' => $this->statusColor,
            'manages_registrations' => $this->managesRegistrations,
            'pending_registrations' => $this->pendingRegistrations,
            'campaign' => $this->campaignTitle === null ? null : [
                'title' => $this->campaignTitle,
                'slug' => $this->campaignSlug,
            ],
        ];
    }
}
