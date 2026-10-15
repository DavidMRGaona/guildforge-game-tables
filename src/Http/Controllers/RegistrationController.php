<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Http\Requests\GuestRegisterRequest;
use Modules\GameTables\Http\Requests\RegisterRequest;
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
            ));
        } catch (RegistrationNotAllowedException $e) {
            throw $this->refusal($e->getMessage());
        } catch (AlreadyRegisteredException) {
            throw $this->refusal(__('game-tables::messages.errors.already_registered'));
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
            $this->registrationService->cancelByUser($gameTable->id, (string) $user->id);

            return back()->with('success', __('game-tables::messages.success.cancelled'));
        } catch (ParticipantNotFoundException) {
            return back()->with('error', __('game-tables::messages.errors.not_found'));
        } catch (CannotCancelException) {
            return back()->with('error', __('game-tables::messages.errors.cannot_cancel'));
        }
    }

    /**
     * Register as a guest (no authentication required).
     *
     * @throws ValidationException when the table does not admit the registration
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
                lastName: null,
                email: $validated['email'],
                phone: $validated['phone'] ?? null,
            ));
        } catch (RegistrationNotAllowedException $e) {
            throw $this->refusal($e->getMessage());
        } catch (AlreadyRegisteredException) {
            throw $this->refusal(__('game-tables::messages.errors.guest_already_registered'));
        }

        return back()->with('success', match (true) {
            $registration->status === ParticipantStatus::WaitingList => __('game-tables::messages.success.guest_waiting_list', ['position' => $registration->waitingListRank]),
            $registration->role === ParticipantRole::Spectator => __('game-tables::messages.success.guest_spectator_registered'),
            default => __('game-tables::messages.success.guest_registered'),
        });
    }

    /**
     * A refused registration: a form error on the table page, a 422 for JSON clients.
     */
    private function refusal(string $message): ValidationException
    {
        return ValidationException::withMessages(['registration' => $message]);
    }

    /**
     * Show the cancellation confirmation page.
     */
    public function showCancelConfirmation(string $token): Response|RedirectResponse
    {
        $participant = $this->registrationService->findByToken($token);

        if ($participant === null) {
            return redirect()->route('gametables.index')
                ->with('error', __('game-tables::messages.errors.invalid_token'));
        }

        $gameTable = $this->gameTableQueryService->findPublished($participant->gameTableId);

        if ($gameTable === null) {
            return redirect()->route('gametables.index')
                ->with('error', __('game-tables::messages.errors.table_not_found'));
        }

        return Inertia::render('GameTables/CancelRegistration', [
            'participant' => $participant->toArray(),
            'gameTable' => [
                'id' => $gameTable->id,
                'title' => $gameTable->title,
                'startsAt' => $gameTable->startsAt,
            ],
            'token' => $token,
            'canCancel' => ! in_array($participant->status->value, ['cancelled', 'rejected', 'no_show'], true),
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
        } catch (CannotCancelException) {
            return redirect()->route('gametables.index')
                ->with('error', __('game-tables::messages.errors.cannot_cancel'));
        }
    }
}
