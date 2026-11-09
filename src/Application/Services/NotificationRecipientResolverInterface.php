<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\MailRecipientDTO;

/**
 * Who the module's mail goes to. Accounts come back as accounts (with their id), so the core
 * applies each person's mail choices; plain addresses only where there is no account.
 */
interface NotificationRecipientResolverInterface
{
    /**
     * The account that created a table or campaign.
     */
    public function getCreatorRecipient(string $userId): ?MailRecipientDTO;

    /**
     * Get the creator's display name by user ID.
     */
    public function getCreatorDisplayName(string $userId): string;

    /**
     * The accounts that can approve or reject game tables in the panel.
     *
     * @return list<MailRecipientDTO>
     */
    public function getTableModeratorRecipients(): array;

    /**
     * The accounts that can approve or reject campaigns in the panel.
     *
     * @return list<MailRecipientDTO>
     */
    public function getCampaignModeratorRecipients(): array;

    /**
     * Game masters that have notify_by_email enabled: their account when linked to one,
     * their own address otherwise. Unique by address.
     *
     * @return list<MailRecipientDTO>
     */
    public function getGameMasterRecipients(string $gameTableId): array;

    /**
     * The accounts of the linked directors that have notify_by_email enabled.
     *
     * @return list<MailRecipientDTO>
     */
    public function getDirectorRecipients(string $gameTableId): array;

    /**
     * Get the table's notification email address (a mailbox, never an account).
     */
    public function getTableNotificationEmail(string $gameTableId): ?string;

    /**
     * The participant's account, or the guest's address.
     */
    public function getParticipantRecipient(string $participantId): ?MailRecipientDTO;

    /**
     * Get the participant's display name.
     */
    public function getParticipantDisplayName(string $participantId): string;
}
