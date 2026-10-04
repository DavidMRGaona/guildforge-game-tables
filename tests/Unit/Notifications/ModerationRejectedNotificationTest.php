<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Modules\GameTables\Notifications\ModerationRejectedNotification;
use Tests\TestCase;

final class ModerationRejectedNotificationTest extends TestCase
{
    public function test_edit_button_links_to_the_rejected_table_edit_page(): void
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
}
