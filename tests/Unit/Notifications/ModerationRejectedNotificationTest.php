<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Modules\GameTables\Notifications\ModerationRejectedNotification;
use Tests\TestCase;

final class ModerationRejectedNotificationTest extends TestCase
{
    public function test_edit_button_links_to_the_rejected_table_edit_page_by_its_slug(): void
    {
        $notification = new ModerationRejectedNotification(
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            userName: 'John Doe',
            reason: 'Falta descripción',
            tableSlug: 'test-table',
        );

        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertSame(url('/mesas/mis-mesas/test-table/editar'), $mail->actionUrl);
    }

    public function test_edit_button_falls_back_to_the_id_without_a_slug(): void
    {
        $notification = new ModerationRejectedNotification(
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            userName: 'John Doe',
            reason: 'Falta descripción',
        );

        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertSame(url('/mesas/mis-mesas/table-uuid-123/editar'), $mail->actionUrl);
    }

    public function test_a_notification_queued_before_it_carried_the_slug_still_links_by_id(): void
    {
        // Payload of a notification queued by 2.7.0, which had no tableSlug property
        $legacy = 'O:'.strlen(ModerationRejectedNotification::class).':"'.ModerationRejectedNotification::class.'":4:{'
            .$this->privateProperty('tableId', 'table-uuid-123')
            .$this->privateProperty('tableTitle', 'Test Table')
            .$this->privateProperty('userName', 'John Doe')
            .$this->privateProperty('reason', 'Falta descripción')
            .'}';

        $notification = unserialize($legacy);
        $this->assertInstanceOf(ModerationRejectedNotification::class, $notification);

        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertSame(url('/mesas/mis-mesas/table-uuid-123/editar'), $mail->actionUrl);
    }

    private function privateProperty(string $name, string $value): string
    {
        $key = "\0".ModerationRejectedNotification::class."\0".$name;

        return 's:'.strlen($key).':"'.$key.'";s:'.strlen($value).':"'.$value.'";';
    }
}
