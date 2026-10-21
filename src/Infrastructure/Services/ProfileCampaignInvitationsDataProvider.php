<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;

/**
 * The pending invitations of the account's email for the profile tab. Nothing for an email that is
 * not verified, so a person who registers with someone else's address learns nothing from the tab.
 */
final readonly class ProfileCampaignInvitationsDataProvider
{
    public function __construct(
        private CampaignInvitationServiceInterface $invitations,
    ) {}

    /**
     * Only what the section shows: no email, send count or resend flag.
     *
     * @return array{invitations: list<array{id: string, campaign_title: string, campaign_slug: string, inviter_name: string, expires_at: string}>, total: int}|null
     */
    public function getDataForUser(UserModel $user): ?array
    {
        if ($user->email_verified_at === null || $user->anonymized_at !== null) {
            return null;
        }

        $items = [];
        foreach ($this->invitations->pendingForEmail($user->email, CarbonImmutable::now()->toDateTimeImmutable()) as $invitation) {
            $items[] = [
                'id' => $invitation->id,
                'campaign_title' => $invitation->campaignTitle,
                'campaign_slug' => $invitation->campaignSlug,
                'inviter_name' => $invitation->inviterName,
                'expires_at' => $invitation->expiresAt,
            ];
        }

        return ['invitations' => $items, 'total' => count($items)];
    }
}
