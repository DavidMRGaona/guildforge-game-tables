<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Route;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;
use Modules\GameTables\Notifications\RegistrationConfirmation;
use Tests\TestCase;

final class RegistrationConfirmationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['translator']->addNamespace(
            'game-tables',
            base_path('modules/game-tables/lang'),
        );

        Route::get('/test/cancel/{token}', fn (): string => '')->name('gametables.cancel-confirmation');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_confirmed_registration_says_the_spot_is_confirmed(): void
    {
        $mail = $this->userMail(ParticipantStatus::Confirmed);

        $this->assertSame($this->trans('subject_confirmed'), $mail->subject);
        $this->assertContains($this->trans('confirmed'), $mail->introLines);
        $this->assertNotContains($this->trans('pending'), $mail->introLines);
    }

    public function test_pending_registration_does_not_claim_to_be_confirmed(): void
    {
        $mail = $this->userMail(ParticipantStatus::Pending);

        $this->assertSame($this->trans('subject_pending'), $mail->subject);
        $this->assertContains($this->trans('pending'), $mail->introLines);
        $this->assertNotContains($this->trans('confirmed'), $mail->introLines);
    }

    public function test_waiting_list_registration_includes_position(): void
    {
        $mail = $this->userMail(ParticipantStatus::WaitingList, waitingListPosition: 3);

        $this->assertSame($this->trans('subject_waiting_list'), $mail->subject);
        $this->assertContains($this->trans('waiting_list', ['position' => 3]), $mail->introLines);
    }

    public function test_guest_pending_registration_does_not_claim_to_be_confirmed(): void
    {
        $mail = $this->guestMail(ParticipantStatus::Pending);

        $this->assertSame($this->trans('subject_pending'), $mail->subject);
        $this->assertContains($this->trans('pending'), $mail->introLines);
    }

    public function test_guest_confirmed_registration_says_the_spot_is_confirmed(): void
    {
        $mail = $this->guestMail(ParticipantStatus::Confirmed);

        $this->assertSame($this->trans('subject_confirmed'), $mail->subject);
        $this->assertContains($this->trans('confirmed'), $mail->introLines);
    }

    private function userMail(ParticipantStatus $status, ?int $waitingListPosition = null): MailMessage
    {
        $notification = new RegistrationConfirmation(
            participantName: 'John Doe',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
            role: 'player',
            status: $status,
            waitingListPosition: $waitingListPosition,
        );

        return $notification->toMail(new AnonymousNotifiable);
    }

    private function guestMail(ParticipantStatus $status): MailMessage
    {
        $notification = new GuestRegistrationConfirmation(
            firstName: 'Bob',
            tableId: 'table-uuid-123',
            tableTitle: 'Test Table',
            tableDate: '15/02/2026 18:00',
            tableLocation: 'Sala de juegos',
            cancellationToken: 'token-123',
            role: 'player',
            status: $status,
        );

        return $notification->toMail(new AnonymousNotifiable);
    }

    /**
     * @param  array<string, int>  $replace
     */
    private function trans(string $key, array $replace = []): string
    {
        return __("game-tables::emails.registration_status.{$key}", ['tableTitle' => 'Test Table', ...$replace]);
    }
}
