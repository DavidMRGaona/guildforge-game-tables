<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

interface NotificationRecipientResolverInterface
{
    /**
     * Get the creator's email address by user ID.
     */
    public function getCreatorEmail(string $userId): ?string;

    /**
     * Get the creator's display name by user ID.
     */
    public function getCreatorDisplayName(string $userId): string;

    /**
     * Get emails of the users who can approve or reject game tables in the panel.
     *
     * @return list<string>
     */
    public function getTableModeratorEmails(): array;

    /**
     * Get emails of the users who can approve or reject campaigns in the panel.
     *
     * @return list<string>
     */
    public function getCampaignModeratorEmails(): array;

    /**
     * Get emails of game masters that have notify_by_email enabled.
     *
     * @return array<string>
     */
    public function getGameMasterEmails(string $gameTableId): array;

    /**
     * Get the account emails of the linked directors that have notify_by_email enabled.
     *
     * @return list<string>
     */
    public function getDirectorEmails(string $gameTableId): array;

    /**
     * Get the table's notification email address.
     */
    public function getTableNotificationEmail(string $gameTableId): ?string;

    /**
     * Get the participant's email address.
     */
    public function getParticipantEmail(string $participantId): ?string;

    /**
     * Get the participant's display name.
     */
    public function getParticipantDisplayName(string $participantId): string;
}
