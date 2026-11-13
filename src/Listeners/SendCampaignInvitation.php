<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Application\Services\TimezoneServiceInterface;
use Modules\GameTables\Application\DTOs\AccountDTO;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Application\Services\AccountLookupInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Notifications\CampaignInvitationNotification;

/**
 * Mails the invitation. It does not depend on the notice settings: it is the mechanism itself.
 */
final readonly class SendCampaignInvitation
{
    public function __construct(
        private CampaignInvitationRepositoryInterface $invitations,
        private CampaignRepositoryInterface $campaigns,
        private AccountLookupInterface $accounts,
        private MailRecipientNotifier $notifier,
    ) {}

    public function handle(CampaignInvitationSent $event): void
    {
        $invitation = $this->invitations->find(new CampaignInvitationId($event->invitationId));

        // Gone (declined, cancelled, expired) or replaced by a newer resend: this token is dead.
        if (! $invitation instanceof CampaignInvitation || ! hash_equals($invitation->tokenHash, hash('sha256', $event->token))) {
            return;
        }

        $campaign = $this->campaigns->find(new CampaignId($event->campaignId));

        if (! $campaign instanceof Campaign) {
            return;
        }

        // The address of an active account is the account: it sees the invitation in its profile
        // too, and its mail choices apply. Anyone else gets it at the address
        $account = $this->accounts->findByEmail($event->email);
        $recipient = $account instanceof AccountDTO
            ? MailRecipientDTO::account($account->userId, $account->email, $account->name)
            : MailRecipientDTO::address($event->email);

        $this->notifier->send($recipient, new CampaignInvitationNotification(
            inviterName: $invitation->inviterName,
            campaignTitle: $campaign->title,
            campaignSlug: $campaign->slug,
            token: $event->token,
            expiresAt: app(TimezoneServiceInterface::class)->format($invitation->expiresAt, 'd/m/Y'),
            invitationId: $event->invitationId,
            campaignId: $event->campaignId,
            inviterId: $invitation->invitedBy,
        ));
    }
}
