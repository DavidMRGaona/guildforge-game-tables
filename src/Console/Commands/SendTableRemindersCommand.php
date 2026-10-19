<?php

declare(strict_types=1);

namespace Modules\GameTables\Console\Commands;

use Carbon\CarbonImmutable;
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

        $now = CarbonImmutable::now();
        $until = $now->addHours($settings->reminderHoursBefore());
        $sent = 0;

        $participants = ParticipantModel::query()
            ->where('status', ParticipantStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->where(fn ($who) => $who->whereNotNull('user_id')->orWhereNotNull('email_confirmed_at'))
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

            if ($table === null || $email === null || $email === '') {
                continue;
            }

            $notification = new TableReminderNotification(
                participantName: $participant->user_id !== null ? (string) $participant->user?->name : (string) $participant->first_name,
                tableTitle: $table->title,
                tableSlug: $table->slug,
                tableDate: $table->starts_at->format('d/m/Y H:i'),
                tableLocation: $table->location,
                cancellationToken: $participant->user_id === null ? $participant->cancellation_token : null,
            );

            if ($participant->user !== null) {
                $participant->user->notify($notification);
            } else {
                Notification::route('mail', $email)->notify($notification);
            }

            $participant->forceFill(['reminder_sent_at' => $now])->save();
            $sent++;
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
