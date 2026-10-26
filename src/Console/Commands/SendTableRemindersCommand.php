<?php

declare(strict_types=1);

namespace Modules\GameTables\Console\Commands;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use DateInterval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\TableReminderNotification;

/**
 * Reminds confirmed participants of published tables that start within the configured hours:
 * accounts, and guests whose email is confirmed. Each registration is reminded once; nothing
 * runs while the reminder is off.
 */
final class SendTableRemindersCommand extends Command
{
    protected $signature = 'game-tables:send-reminders';

    protected $description = 'Remind confirmed participants of tables that start soon';

    public function handle(GameTableSettingsReader $settings): int
    {
        if (! $settings->isReminderEnabled()) {
            $this->info('Table reminders are disabled.');

            return self::SUCCESS;
        }

        $timezone = app(TimezoneServiceInterface::class);
        // Bound in the storage zone: bindings skip the model's conversion
        $now = $timezone->toStorage(CarbonImmutable::now());
        $until = $now->add(new DateInterval(sprintf('PT%dH', $settings->reminderHoursBefore())));
        $sent = 0;

        $participants = ParticipantModel::query()
            ->where('status', ParticipantStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            // Accounts still in use, or guests whose email is confirmed
            ->where(fn ($who) => $who
                ->whereHas('user', fn ($user) => $user->whereNull('anonymized_at'))
                ->orWhere(fn ($guest) => $guest->whereNull('user_id')->whereNotNull('email_confirmed_at')))
            ->whereHas('gameTable', fn ($table) => $table
                ->where('is_published', true)
                ->whereIn('status', [TableStatus::Scheduled->value, TableStatus::Full->value])
                ->where('starts_at', '>', $now)
                ->where('starts_at', '<=', $until))
            ->with(['gameTable', 'user'])
            ->get();

        foreach ($participants as $participant) {
            $table = $participant->gameTable;
            $email = $participant->user_id !== null ? $participant->user?->email : $participant->email;

            // The query already requires the table (whereHas)
            if ($email === null || $email === '') {
                continue;
            }

            // Claimed before sending, so a run started by hand next to the scheduled one never doubles it
            $claimed = ParticipantModel::query()
                ->whereKey($participant->id)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => $now]) === 1;

            if (! $claimed) {
                continue;
            }

            $notification = new TableReminderNotification(
                participantName: $participant->user_id !== null ? (string) $participant->user?->name : (string) $participant->first_name,
                tableTitle: $table->title,
                // HasSlug always sets one; the id keeps a row without it from stopping the run
                tableSlug: $table->slug ?? $table->id,
                tableDate: $timezone->format($table->starts_at),
                tableLocation: $table->location,
                cancellationToken: $participant->user_id === null ? $participant->cancellation_token : null,
            );

            if ($participant->user !== null) {
                $participant->user->notify($notification);
            } else {
                Notification::route('mail', $email)->notify($notification);
            }

            $sent++;
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
