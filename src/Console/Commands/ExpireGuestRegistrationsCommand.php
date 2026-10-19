<?php

declare(strict_types=1);

namespace Modules\GameTables\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\GameTables\Infrastructure\Services\GuestRegistrationExpirer;

final class ExpireGuestRegistrationsCommand extends Command
{
    protected $signature = 'game-tables:expire-guest-registrations';

    protected $description = 'Expire unconfirmed guest registrations and clear the fingerprints of past tables';

    public function handle(GuestRegistrationExpirer $expirer): int
    {
        // Not gated by automation.auto_status_enabled: held places must lapse anyway
        $counts = $expirer->run(CarbonImmutable::now()->toDateTimeImmutable());

        // A row that failed is in the log; the others have been expired anyway
        $this->info(sprintf(
            'Expired %d registration(s), closed %d, cleared %d fingerprint(s), failed %d.',
            $counts['expired'],
            $counts['closed'],
            $counts['fingerprintsCleared'],
            $counts['failed'],
        ));

        return self::SUCCESS;
    }
}
