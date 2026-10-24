<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Modules\GameTables\Notifications\ParticipantRejectedNotification;
use Tests\TestCase;

final class ParticipantRejectedNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['translator']->addNamespace(
            'game-tables',
            base_path('modules/game-tables/lang'),
        );
    }

    public function test_the_reason_is_a_line_with_its_text_escaped(): void
    {
        $lines = $this->lines(new ParticipantRejectedNotification(
            participantName: 'John Doe',
            tableTitle: 'Test Table',
            tableDate: null,
            tableLocation: null,
            reason: 'Sin plazas <b>para novatos</b>',
        ));

        $this->assertContains('<p>Motivo: Sin plazas &lt;b&gt;para novatos&lt;/b&gt;</p>', $lines);
    }

    public function test_without_a_reason_there_is_no_reason_line(): void
    {
        $lines = $this->lines(new ParticipantRejectedNotification(
            participantName: 'John Doe',
            tableTitle: 'Test Table',
            tableDate: null,
            tableLocation: null,
        ));

        foreach ($lines as $line) {
            $this->assertStringNotContainsString('Motivo', $line);
        }
    }

    /**
     * @return list<string>
     */
    private function lines(ParticipantRejectedNotification $notification): array
    {
        $mail = $notification->toMail(new AnonymousNotifiable);

        return array_map(static fn (mixed $line): string => (string) $line, $mail->introLines);
    }
}
