<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Lang;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * The waiting list and the cancellation link end at the start of the table: the copy says so.
 * Literal texts, so a missing or misplaced key fails.
 */
final class StartClosesRegistrationsCopyTest extends TestCase
{
    private function lang(string $locale, string $file): array
    {
        return Arr::dot(require dirname(__DIR__, 3)."/lang/{$locale}/{$file}.php");
    }

    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/'.$file);
    }

    public function test_the_spanish_waiting_list_copy_ends_at_the_start(): void
    {
        $messages = $this->lang('es', 'messages');
        $when = 'antes del inicio de la partida (o del cierre de inscripciones, si es anterior)';

        $this->assertSame(
            'Te has apuntado a la lista de espera (posición :position). Si se libera una plaza '.$when.', pasarás a la mesa automáticamente y te avisaremos por correo.',
            $messages['success.waiting_list']
        );
        $this->assertSame(
            'Te has apuntado a la lista de espera (posición :position). Si se libera una plaza '.$when.', pasarás a la mesa pendiente de que el director te confirme y te avisaremos por correo.',
            $messages['success.waiting_list_manual']
        );
        $this->assertSame(
            'Te has apuntado a la lista de espera (posición :position). Recibirás un email de confirmación y otro si se libera una plaza '.$when.'.',
            $messages['success.guest_waiting_list']
        );
        $this->assertStringContainsString(
            'pasa automáticamente al primero de la lista hasta el inicio de la partida (o hasta el cierre de inscripciones, si es anterior); después, la promoción automática se detiene',
            $messages['settings.fields.waiting_list_enabled_help']
        );
    }

    public function test_the_english_waiting_list_copy_ends_at_the_start(): void
    {
        $messages = $this->lang('en', 'messages');
        $when = 'before the table starts (or before registrations close, if that comes first)';

        $this->assertSame(
            'You have joined the waiting list (position :position). If a seat is freed '.$when.', you will take it automatically and we will let you know by email.',
            $messages['success.waiting_list']
        );
        $this->assertSame(
            'You have joined the waiting list (position :position). If a seat is freed '.$when.', you will take it pending confirmation by the game master and we will let you know by email.',
            $messages['success.waiting_list_manual']
        );
        $this->assertSame(
            'You have joined the waiting list (position :position). You will receive a confirmation email and another one if a seat is freed '.$when.'.',
            $messages['success.guest_waiting_list']
        );
        $this->assertStringContainsString(
            'goes automatically to the first in the list until the table starts (or until registrations close, if that comes first); after that, automatic promotion stops',
            $messages['settings.fields.waiting_list_enabled_help']
        );
    }

    public function test_the_page_texts_of_the_waiting_list_end_at_the_start(): void
    {
        $es = $this->source('locales/es.ts');
        $this->assertStringContainsString(
            "waitingListHelp: 'La mesa está completa. Si se libera una plaza antes del inicio de la partida (o del cierre de inscripciones, si es anterior), pasarás a ella automáticamente por orden de lista y te avisaremos por correo.',",
            $es
        );
        $this->assertStringContainsString(
            "waitingListHelpManual: 'La mesa está completa. Si se libera una plaza antes del inicio de la partida (o del cierre de inscripciones, si es anterior), pasarás a ella por orden de lista, pendiente de que el director te confirme, y te avisaremos por correo.',",
            $es
        );

        $en = $this->source('locales/en.ts');
        $this->assertStringContainsString(
            "waitingListHelp: 'The table is full. If a seat is freed before the table starts (or before registrations close, if that comes first), you will take it automatically, in waiting list order, and we will let you know by email.',",
            $en
        );
        $this->assertStringContainsString(
            "waitingListHelpManual: 'The table is full. If a seat is freed before the table starts (or before registrations close, if that comes first), you will take it in waiting list order, pending confirmation by the game master, and we will let you know by email.',",
            $en
        );
    }

    public function test_the_cancellation_email_says_the_link_works_before_the_start(): void
    {
        $this->assertSame(
            'Si necesitas cancelar tu inscripción antes del inicio de la partida, usa el siguiente enlace:',
            $this->lang('es', 'emails')[$this->keyEndingWith($this->lang('es', 'emails'), 'cancel_intro')]
        );
        $this->assertSame(
            'If you need to cancel your registration before the table starts, use the following link:',
            $this->lang('en', 'emails')[$this->keyEndingWith($this->lang('en', 'emails'), 'cancel_intro')]
        );
    }

    /**
     * @param  array<string, mixed>  $dotted
     */
    private function keyEndingWith(array $dotted, string $suffix): string
    {
        $keys = array_filter(array_keys($dotted), fn (string $key): bool => str_ends_with($key, $suffix));
        $this->assertCount(1, $keys, "exactly one {$suffix} key expected");

        return (string) array_values($keys)[0];
    }
}
