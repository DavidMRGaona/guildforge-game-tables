<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;

final readonly class DatabaseCampaignInvitationSendLog implements CampaignInvitationSendLogInterface
{
    private const string TABLE = 'gametables_campaign_invitation_sends';

    public function recordSend(string $campaignId, string $email, DateTimeImmutable $at): void
    {
        $this->record($campaignId, $email, 'sent', $at);
    }

    public function recordRejection(string $campaignId, string $email, DateTimeImmutable $at): void
    {
        $this->record($campaignId, $email, 'rejected', $at);
    }

    public function sendsSince(string $campaignId, string $email, DateTimeImmutable $since): int
    {
        return $this->query($campaignId, $email, 'sent')
            ->where('occurred_at', '>=', $this->stored($since))
            ->count();
    }

    public function lastSendAt(string $campaignId, string $email): ?DateTimeImmutable
    {
        $at = $this->query($campaignId, $email, 'sent')->max('occurred_at');

        return $at === null ? null : new DateTimeImmutable((string) $at);
    }

    public function rejectedSince(string $campaignId, string $email, DateTimeImmutable $since): bool
    {
        return $this->query($campaignId, $email, 'rejected')
            ->where('occurred_at', '>=', $this->stored($since))
            ->exists();
    }

    public function pruneBefore(DateTimeImmutable $before): int
    {
        return DB::table(self::TABLE)
            ->where('occurred_at', '<', $this->stored($before))
            ->delete();
    }

    private function record(string $campaignId, string $email, string $kind, DateTimeImmutable $at): void
    {
        DB::table(self::TABLE)->insert([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaignId,
            'email_hash' => $this->hash($campaignId, $email),
            'kind' => $kind,
            'occurred_at' => $this->stored($at),
        ]);
    }

    /**
     * The query builder skips the model's conversion: instants go in the storage zone.
     */
    private function stored(DateTimeImmutable $instant): DateTimeImmutable
    {
        return app(TimezoneServiceInterface::class)->toStorage($instant);
    }

    private function query(string $campaignId, string $email, string $kind): Builder
    {
        return DB::table(self::TABLE)
            ->where('campaign_id', $campaignId)
            ->where('email_hash', $this->hash($campaignId, $email))
            ->where('kind', $kind);
    }

    private function hash(string $campaignId, string $email): string
    {
        return hash_hmac('sha256', $campaignId.'|'.$email, (string) config('app.key'));
    }
}
