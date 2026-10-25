<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\AccountEmailRequiresLoginException;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Http\Requests\GuestRegisterRequest;
use Modules\GameTables\Http\Requests\RegisterRequest;
use Modules\GameTables\Http\Support\GuestFingerprint;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly GameTableQueryServiceInterface $gameTableQueryService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    /**
     * Register to a game table.
     *
     * @throws ValidationException when the table does not admit the registration
     */
    public function register(RegisterRequest $request, GameTableModel $gameTable): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', __('game-tables::messages.errors.unauthenticated'));
        }

        $role = ParticipantRole::from((string) $request->validated('role', ParticipantRole::Player->value));

        try {
            // Checks, under the table lock, the same rules the table page applies before offering the button
            $registration = $this->registrationService->register(new RegisterParticipantDTO(
                gameTableId: $gameTable->id,
                userId: (string) $user->id,
                role: $role,
                notes: $request->validated('notes'),
                fingerprint: GuestFingerprint::of($request),
            ));
        } catch (RegistrationNotAllowedException $e) {
            throw $this->refusal($e->getMessage());
        } catch (AlreadyRegisteredException $e) {
            // A guest registration with the account's email, which an unverified account cannot claim
            throw $this->refusal($e->reason === 'email_registered_as_guest'
                ? $e->getMessage()
                : __('game-tables::messages.errors.already_registered'));
        } catch (TableFullException $e) {
            // A guest registration taken over that still needed a place, and found none
            throw $this->refusal(__('game-tables::messages.errors.'.$e->reason));
        }

        if ($registration->fromRoster) {
            // A roster member taking their seat back: always a confirmed seat
            return back()->with('success', __('game-tables::messages.roster.success.rejoined'));
        }

        if ($registration->linkedFromGuest) {
            // The place among those waiting, not the stored position (which may have gaps)
            return back()->with('success', $registration->status === ParticipantStatus::WaitingList
                ? __('game-tables::messages.success.registration_linked_waiting_list', ['position' => $registration->waitingListRank])
                : __('game-tables::messages.success.registration_linked'));
        }

        if ($registration->status === ParticipantStatus::WaitingList) {
            // On tables with manual confirmation the promoted player still waits for the director
            $key = $gameTable->auto_confirm ? 'waiting_list' : 'waiting_list_manual';

            // The place among those waiting, not the stored position (which may have gaps)
            return back()->with('success', __('game-tables::messages.success.'.$key, ['position' => $registration->waitingListRank]));
        }

        return back()->with('success', __('game-tables::messages.success.registered'));
    }

    /**
     * Cancel registration.
     */
    public function cancel(Request $request, GameTableModel $gameTable): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', __('game-tables::messages.errors.unauthenticated'));
        }

        try {
            $cancelled = $this->registrationService->cancelByUser($gameTable->id, (string) $user->id);

            // A roster seat given up is an absence from this session only
            return back()->with('success', $cancelled->fromRoster
                ? __('game-tables::messages.roster.success.session_absence')
                : __('game-tables::messages.success.cancelled'));
        } catch (ParticipantNotFoundException) {
            return back()->with('error', __('game-tables::messages.errors.not_found'));
        } catch (CannotCancelException $e) {
            return back()->with('error', $this->cancellationRefusal($e));
        }
    }

    /**
     * Register as a guest (no authentication required). The registration waits for the guest to
     * confirm the email: the answer says what happens to the place meanwhile.
     *
     * @throws ValidationException when the table does not admit the registration, or the email
     *                             belongs to an account that should sign in (error `account`)
     */
    public function registerGuest(GuestRegisterRequest $request, GameTableModel $gameTable): RedirectResponse
    {
        $validated = $request->validated();
        $role = ParticipantRole::from((string) ($validated['role'] ?? ParticipantRole::Player->value));

        try {
            // Checks, under the table lock, the same rules the table page applies to guests
            $registration = $this->registrationService->registerGuest(new RegisterParticipantDTO(
                gameTableId: $gameTable->id,
                userId: null,
                role: $role,
                notes: $validated['notes'] ?? null,
                firstName: $validated['first_name'],
                email: $validated['email'],
                phone: $validated['phone'] ?? null,
                fingerprint: GuestFingerprint::of($request),
            ));
        } catch (RegistrationNotAllowedException $e) {
            throw $this->refusal($e->getMessage());
        } catch (AlreadyRegisteredException) {
            throw $this->refusal(__('game-tables::messages.errors.guest_already_registered'));
        } catch (AccountEmailRequiresLoginException $e) {
            throw $this->signInRequired($request, $e->getMessage());
        }

        return back()->with('success', $this->guestAnswer($registration));
    }

    /**
     * What a web guest is told once the form is accepted: the email was already sent (the
     * registration is the one they had), or it was sent now and the place is held until the
     * deadline (on the waiting list, the position) or not guaranteed until they confirm.
     */
    private function guestAnswer(ParticipantResponseDTO $registration): string
    {
        if ($registration->confirmationResent) {
            return (string) __('game-tables::messages.success.guest_confirmation_pending');
        }

        $deadline = $registration->confirmationExpiresAt;

        if ($registration->confirmationMode !== GuestConfirmationMode::Hold || ! $deadline instanceof \DateTimeInterface) {
            return (string) __('game-tables::messages.success.guest_confirmation_sent_unguaranteed');
        }

        return (string) ($registration->status === ParticipantStatus::WaitingList
            ? __('game-tables::messages.success.guest_confirmation_sent_waiting_list', [
                'deadline' => app(TimezoneServiceInterface::class)->format($deadline),
                // The place among those waiting, not the stored position (which may have gaps)
                'position' => $registration->waitingListRank,
            ])
            : __('game-tables::messages.success.guest_confirmation_sent', ['deadline' => app(TimezoneServiceInterface::class)->format($deadline)]));
    }

    /**
     * The email belongs to an account: a form error under `account` (the form links to the log
     * in page), and for JSON clients the same 422 with the log in URL.
     */
    private function signInRequired(Request $request, string $message): ValidationException
    {
        $exception = ValidationException::withMessages(['account' => $message]);

        if ($request->expectsJson()) {
            $exception->response = response()->json([
                'message' => $message,
                'errors' => ['account' => [$message]],
                'login_url' => route('login'),
            ], 422);
        }

        return $exception;
    }

    /**
     * A refused registration: a form error on the table page, a 422 for JSON clients.
     */
    private function refusal(string $message): ValidationException
    {
        return ValidationException::withMessages(['registration' => $message]);
    }

    /**
     * Why a participant's own cancellation was refused: the table has started, or the
     * registration is no longer active.
     */
    private function cancellationRefusal(CannotCancelException $e): string
    {
        return (string) __($e->reason === 'table_started'
            ? 'game-tables::messages.errors.cannot_cancel_started'
            : 'game-tables::messages.errors.cannot_cancel');
    }

    /**
     * Show the cancellation confirmation page.
     */
    public function showCancelConfirmation(string $token): Response|RedirectResponse
    {
        $participant = $this->registrationService->findByToken($token);

        if (! $participant instanceof ParticipantResponseDTO) {
            return redirect()->route('gametables.index')
                ->with('error', __('game-tables::messages.errors.invalid_token'));
        }

        $gameTable = $this->gameTableQueryService->findPublished($participant->gameTableId);

        if (! $gameTable instanceof GameTableResponseDTO) {
            return redirect()->route('gametables.index')
                ->with('error', __('game-tables::messages.errors.table_not_found'));
        }

        // The rule the cancellation applies: an active registration on a table that has not started
        $refusal = $this->registrationService->withdrawalRefusal($participant->id);

        return Inertia::render('GameTables/CancelRegistration', [
            'participant' => $participant->toArray(),
            'gameTable' => [
                'id' => $gameTable->id,
                'title' => $gameTable->title,
                'startsAt' => $gameTable->startsAt,
            ],
            'token' => $token,
            'canCancel' => $refusal === null,
            'tableStarted' => $refusal === 'table_started',
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.cancel_registration_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    /**
     * Cancel a registration by token.
     */
    public function cancelByToken(string $token): RedirectResponse
    {
        try {
            $this->registrationService->cancelByToken($token);

            return redirect()->route('gametables.index')
                ->with('success', __('game-tables::messages.success.cancelled'));
        } catch (ParticipantNotFoundException) {
            return redirect()->route('gametables.index')
                ->with('error', __('game-tables::messages.errors.invalid_token'));
        } catch (CannotCancelException $e) {
            return redirect()->route('gametables.index')
                ->with('error', $this->cancellationRefusal($e));
        }
    }
}
