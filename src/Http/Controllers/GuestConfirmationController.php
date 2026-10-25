<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\GuestConfirmationOutcome;
use Modules\GameTables\Domain\Enums\ParticipantStatus;

/**
 * The link of the guest confirmation email. Opening it only shows the page; its button (POST)
 * confirms, so the link scanners of mail providers never confirm a registration on their own,
 * and the same page then shows what confirming did.
 */
final class GuestConfirmationController extends Controller
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly GameTableQueryServiceInterface $gameTableQueryService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(string $token): Response
    {
        $participant = $this->registrationService->findByConfirmationToken($token);
        $gameTable = $participant instanceof \Modules\GameTables\Application\DTOs\ParticipantResponseDTO && $participant->status->isActive()
            ? $this->gameTableQueryService->findPublished($participant->gameTableId)
            : null;

        // Expired, cancelled, unknown or taken off the web: the page says so (also on a refresh
        // after the button expired it, since expiring clears the token)
        if (!$participant instanceof \Modules\GameTables\Application\DTOs\ParticipantResponseDTO || !$gameTable instanceof \Modules\GameTables\Application\DTOs\GameTableResponseDTO) {
            return $this->messagePage($token, (string) __('game-tables::messages.errors.invalid_confirmation'));
        }

        return $this->page($token, $participant, $gameTable, result: null);
    }

    /**
     * Answers on the confirmation page itself, with what confirming did to the registration.
     */
    public function store(string $token): Response
    {
        // A table taken off the web confirms nothing, as its page shows nothing
        $found = $this->registrationService->findByConfirmationToken($token);

        if ($found instanceof \Modules\GameTables\Application\DTOs\ParticipantResponseDTO && !$this->gameTableQueryService->findPublished($found->gameTableId) instanceof \Modules\GameTables\Application\DTOs\GameTableResponseDTO) {
            return $this->messagePage($token, (string) __('game-tables::messages.errors.invalid_confirmation'));
        }

        $result = $this->registrationService->confirmGuestEmail($token);
        $gameTable = $result->gameTableId !== null
            ? $this->gameTableQueryService->findPublished($result->gameTableId)
            : null;

        return $this->page(
            $token,
            $gameTable instanceof \Modules\GameTables\Application\DTOs\GameTableResponseDTO ? $result->participant : null,
            $gameTable,
            $this->resultOf($result->outcome, $result->participant),
        );
    }

    /**
     * The page with only an error message: no registration, no table, a link to the list of tables.
     * Also the answer over the link's rate limit.
     */
    public function messagePage(string $token, string $message): Response
    {
        return $this->page($token, null, null, ['tone' => 'error', 'message' => $message]);
    }

    /**
     * @param  array{tone: string, message: string}|null  $result
     */
    private function page(string $token, ?ParticipantResponseDTO $participant, ?GameTableResponseDTO $gameTable, ?array $result): Response
    {
        return Inertia::render('GameTables/ConfirmRegistration', [
            'participant' => $participant instanceof \Modules\GameTables\Application\DTOs\ParticipantResponseDTO ? [
                'first_name' => $participant->firstName,
                'role_label' => $participant->role->label(),
                'status_label' => $this->stateLabel($participant),
            ] : null,
            'gameTable' => $gameTable instanceof \Modules\GameTables\Application\DTOs\GameTableResponseDTO ? [
                'title' => $gameTable->title,
                'startsAt' => $gameTable->startsAt,
                'slug' => $gameTable->slug ?? $gameTable->id,
            ] : null,
            'token' => $token,
            'alreadyConfirmed' => $participant instanceof \Modules\GameTables\Application\DTOs\ParticipantResponseDTO && ! $participant->awaitingEmailConfirmation,
            'result' => $result,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.confirm_registration_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Until the link is followed what is pending is the email, whatever seat the guest holds
     * meanwhile; then the registration's own state.
     */
    private function stateLabel(ParticipantResponseDTO $participant): string
    {
        return $participant->awaitingEmailConfirmation
            ? (string) __('game-tables::messages.confirm_registration.awaiting_email')
            : $participant->status->label();
    }

    /**
     * The message for what confirming did: a seat, the organisation's approval still pending,
     * a place on the waiting list, or why nothing was confirmed.
     *
     * @return array{tone: string, message: string}
     */
    private function resultOf(GuestConfirmationOutcome $outcome, ?ParticipantResponseDTO $participant): array
    {
        if ($outcome === GuestConfirmationOutcome::Confirmed && $participant instanceof \Modules\GameTables\Application\DTOs\ParticipantResponseDTO) {
            return match ($participant->status) {
                ParticipantStatus::Pending => ['tone' => 'info', 'message' => (string) __('game-tables::messages.success.guest_email_confirmed_pending')],
                // The place among those waiting, not the stored position (which may have gaps)
                ParticipantStatus::WaitingList => ['tone' => 'info', 'message' => (string) __('game-tables::messages.success.guest_email_confirmed_waiting_list', [
                    'position' => $participant->waitingListRank ?? $participant->waitingListPosition,
                ])],
                default => ['tone' => 'success', 'message' => (string) __('game-tables::messages.success.guest_email_confirmed')],
            };
        }

        return match ($outcome) {
            GuestConfirmationOutcome::Confirmed => ['tone' => 'success', 'message' => (string) __('game-tables::messages.success.guest_email_confirmed')],
            GuestConfirmationOutcome::AlreadyConfirmed => ['tone' => 'success', 'message' => (string) __('game-tables::messages.success.guest_email_already_confirmed')],
            GuestConfirmationOutcome::Closed => ['tone' => 'error', 'message' => (string) __('game-tables::messages.errors.confirmation_closed')],
            GuestConfirmationOutcome::FilledUp => ['tone' => 'error', 'message' => (string) __('game-tables::messages.errors.confirmation_filled_up')],
            GuestConfirmationOutcome::Invalid => ['tone' => 'error', 'message' => (string) __('game-tables::messages.errors.invalid_confirmation')],
        };
    }
}
