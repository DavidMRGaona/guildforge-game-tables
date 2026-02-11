<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Listeners;

use DateTimeImmutable;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnGuestRegistration;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Tests\TestCase;

final class NotifyOnGuestRegistrationTest extends TestCase
{
    private NotificationRecipientResolverInterface $recipientResolver;
    private GameTableRepositoryInterface $gameTableRepository;
    private NotifyOnGuestRegistration $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipientResolver = $this->createMock(NotificationRecipientResolverInterface::class);
        $this->gameTableRepository = $this->createMock(GameTableRepositoryInterface::class);

        $this->listener = new NotifyOnGuestRegistration(
            new GameTableSettingsReader(),
            $this->recipientResolver,
            $this->gameTableRepository,
        );

        Notification::fake();
    }

    public function test_sends_notification_to_gms_and_table_email(): void
    {
        config()->set('game-tables.notifications.notify_on_registration', true);

        $gameTableId = GameTableId::generate();
        $gameTable = $this->createGameTable($gameTableId);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->equalTo($gameTableId))
            ->willReturn($gameTable);

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

        $event = new GuestRegistered(
            participantId: 'participant-uuid',
            gameTableId: $gameTableId->value,
            email: 'guest@example.com',
            firstName: 'Juan',
            role: 'player',
            cancellationToken: 'token-123',
        );

        $this->listener->handle($event);

        Notification::assertSentOnDemand(
            ParticipantRegisteredNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'gm@example.com';
            }
        );

        Notification::assertSentOnDemand(
            ParticipantRegisteredNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'table@example.com';
            }
        );
    }

    public function test_does_not_send_when_setting_disabled(): void
    {
        config()->set('game-tables.notifications.notify_on_registration', false);

        $gameTableId = GameTableId::generate();

        $this->gameTableRepository
            ->expects($this->never())
            ->method('find');

        $event = new GuestRegistered(
            participantId: 'participant-uuid',
            gameTableId: $gameTableId->value,
            email: 'guest@example.com',
            firstName: 'Juan',
            role: 'player',
            cancellationToken: 'token-123',
        );

        $this->listener->handle($event);

        Notification::assertNothingSent();
    }

    public function test_does_not_send_when_game_table_not_found(): void
    {
        config()->set('game-tables.notifications.notify_on_registration', true);

        $gameTableId = GameTableId::generate();

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->equalTo($gameTableId))
            ->willReturn(null);

        $this->recipientResolver
            ->expects($this->never())
            ->method('getGameMasterEmails');

        $event = new GuestRegistered(
            participantId: 'participant-uuid',
            gameTableId: $gameTableId->value,
            email: 'guest@example.com',
            firstName: 'Juan',
            role: 'player',
            cancellationToken: 'token-123',
        );

        $this->listener->handle($event);

        Notification::assertNothingSent();
    }

    private function createGameTable(GameTableId $gameTableId): GameTable
    {
        return new GameTable(
            id: $gameTableId,
            gameSystemId: GameSystemId::generate(),
            createdBy: 'user-uuid-123',
            title: 'La Tumba de la Aniquilacion',
            slug: 'la-tumba-de-la-aniquilacion',
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
