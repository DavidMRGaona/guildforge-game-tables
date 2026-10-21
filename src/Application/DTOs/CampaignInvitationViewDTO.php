<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

final readonly class CampaignInvitationViewDTO
{
    public function __construct(
        public string $id,
        public string $campaignId,
        public string $campaignTitle,
        public string $campaignSlug,
        public string $inviterName,
        public string $email,
        public string $expiresAt,
        public int $sendCount,
        public bool $canResend,
    ) {}

    /**
     * @return array{id: string, campaign_id: string, campaign_title: string, campaign_slug: string, inviter_name: string, email: string, expires_at: string, send_count: int, can_resend: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaignId,
            'campaign_title' => $this->campaignTitle,
            'campaign_slug' => $this->campaignSlug,
            'inviter_name' => $this->inviterName,
            'email' => $this->email,
            'expires_at' => $this->expiresAt,
            'send_count' => $this->sendCount,
            'can_resend' => $this->canResend,
        ];
    }
}
