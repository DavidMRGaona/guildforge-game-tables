<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Listeners;

use DateTimeImmutable;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnWaitingListPromotion;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use Tests\TestCase;

final class NotifyOnWaitingListPromotionTest extends TestCase
{
    private NotificationRecipientResolver $recipientResolver;
    private GameTableRepositoryInterface $gameTableRepository;
    private NotifyOnWaitingListPromotion $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipientResolver = $this->createMock(NotificationRecipientResolver::class);
        $this->gameTableRepository = $this->createMock(GameTableRepositoryInterface::class);

        $this->listener = new NotifyOnWaitingListPromotion(
            new GameTableSettingsReader(),
            $this->recipientResolver,
            $this->gameTableRepository,
        );

        Notification::fake();
    }

    public function test_sends_notification_to_participant_and_gms(): void
    {
        config()->set('game-tables.notifications.notify_waiting_list_promotion', true);

        $gameTableId = GameTableId::generate();
        $gameTable = $this->createGameTable($gameTableId);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->equalTo($gameTableId))
            ->willReturn($gameTable);

        $this->recipientResolver
            ->expects($this->once())
            ->method('getParticipantDisplayName')
            ->with('participant-uuid')
            ->willReturn('John Doe');

        $this->recipientResolver
            ->expects($this->once())
            ->method('getParticipantEmail')
            ->with('participant-uuid')
            ->willReturn('participant@example.com');

        $this->recipientResolver
            ->expects($this->once())
            ->method('getGameMasterEmails')
            ->with($gameTableId->value)
            ->willReturn(['gm@example.com']);

        $this->recipientResolver
            ->expects($this->once())
            ->method('getTableNotificationEmail')
            ->with($gameTableId->value)
            ->willReturn('table@example.com');

        $event = new ParticipantPromotedFromWaitingList(
            participantId: 'participant-uuid',
            gameTableId: $gameTableId->value,
            userId: 'user-uuid',
        );

        $this->listener->handle($event);

        Notification::assertSentOnDemand(
            WaitingListPromotionNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'participant@example.com';
            }
        );

        Notification::assertSentOnDemand(
            WaitingListPromotionGmNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'gm@example.com';
            }
        );

        Notification::assertSentOnDemand(
            WaitingListPromotionGmNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'table@example.com';
            }
        );
    }

    public function test_does_not_send_to_participant_when_no_email(): void
    {
        config()->set('game-tables.notifications.notify_waiting_list_promotion', true);

        $gameTableId = GameTableId::generate();
        $gameTable = $this->createGameTable($gameTableId);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->equalTo($gameTableId))
            ->willReturn($gameTable);

        $this->recipientResolver
            ->expects($this->once())
            ->method('getParticipantDisplayName')
            ->with('participant-uuid')
            ->willReturn('John Doe');

        $this->recipientResolver
            ->expects($this->once())
            ->method('getParticipantEmail')
            ->with('participant-uuid')
            ->willReturn(null);

        $this->recipientResolver
            ->expects($this->once())
            ->method('getGameMasterEmails')
            ->with($gameTableId->value)
            ->willReturn(['gm@example.com']);

        $this->recipientResolver
            ->expects($this->once())
            ->method('getTableNotificationEmail')
            ->with($gameTableId->value)
            ->willReturn(null);

        $event = new ParticipantPromotedFromWaitingList(
            participantId: 'participant-uuid',
            gameTableId: $gameTableId->value,
            userId: 'user-uuid',
        );

        $this->listener->handle($event);

        Notification::assertNotSentOnDemand(WaitingListPromotionNotification::class);

        Notification::assertSentOnDemand(
            WaitingListPromotionGmNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'gm@example.com';
            }
        );
    }

    public function test_does_not_send_when_setting_disabled(): void
    {
        config()->set('game-tables.notifications.notify_waiting_list_promotion', false);

        $gameTableId = GameTableId::generate();

        $this->gameTableRepository
            ->expects($this->never())
            ->method('find');

        $event = new ParticipantPromotedFromWaitingList(
            participantId: 'participant-uuid',
            gameTableId: $gameTableId->value,
            userId: 'user-uuid',
        );

        $this->listener->handle($event);

        Notification::assertNothingSentOnDemand();
    }

    private function createGameTable(GameTableId $gameTableId): GameTable
    {
        return new GameTable(
            id: $gameTableId,
            gameSystemId: GameSystemId::generate(),
            createdBy: 'user-uuid-123',
            title: 'La Tumba de la Aniquilacion',
            timeSlot: new TimeSlot(new DateTimeImmutable('2026-02-01 18:00:00'), 240),
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: TableStatus::Draft,
            minPlayers: 3,
            maxPlayers: 5,
            location: 'Sala de juegos',
        );
    }
}
