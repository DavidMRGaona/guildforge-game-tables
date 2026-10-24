<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * One registration as a table's director sees it on the «Gestionar inscritos» page. It never
 * carries the email, phone or notes: the director answers requests, the organisation keeps the
 * contact details.
 *
 * @phpstan-type TableRegistrationRow array{id: string, name: string, is_guest: bool, role: string, role_label: string, status: string, status_label: string, requested_at: string|null, awaiting_email_confirmation: bool, can_approve: bool, can_reject: bool}
 */
final readonly class TableRegistrationRowDTO
{
    public function __construct(
        public string $id,
        // The account name, or a guest's first and last name
        public string $name,
        public bool $isGuest,
        // player or spectator
        public string $role,
        public string $roleLabel,
        public string $status,
        public string $statusLabel,
        // ISO 8601
        public ?string $requestedAt,
        // A guest who has not followed the link of their confirmation email yet
        public bool $awaitingEmailConfirmation,
        // Pending, and not awaiting the email: approving must never vouch for it
        public bool $canApprove,
        // Pending
        public bool $canReject,
    ) {}

    /**
     * @return TableRegistrationRow
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_guest' => $this->isGuest,
            'role' => $this->role,
            'role_label' => $this->roleLabel,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'requested_at' => $this->requestedAt,
            'awaiting_email_confirmation' => $this->awaitingEmailConfirmation,
            'can_approve' => $this->canApprove,
            'can_reject' => $this->canReject,
        ];
    }
}
