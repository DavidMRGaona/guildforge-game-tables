<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Listeners\SendConfirmationNotification;
use Tests\TestCase;

final class SendConfirmationNotificationTest extends TestCase
{
    public function test_skips_notification_when_confirmed_automatically(): void
    {
        Notification::fake();

        $gameTableRepository = $this->createMock(GameTableRepositoryInterface::class);
        $gameTableRepository->expects($this->never())->method('find');

        $listener = new SendConfirmationNotification(
            $gameTableRepository,
            $this->app->make(NotificationRecipientResolver::class),
        );

        $listener->handle(new ParticipantConfirmed(
            participantId: 'participant-uuid',
            gameTableId: 'table-uuid',
            userId: 'user-uuid',
            automatic: true,
        ));

        Notification::assertNothingSent();
    }
}
