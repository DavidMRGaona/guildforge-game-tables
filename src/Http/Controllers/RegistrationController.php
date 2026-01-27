<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\MembersOnlyException;
use Modules\GameTables\Domain\Exceptions\MinimumAgeException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Exceptions\RegistrationClosedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Http\Requests\GuestRegisterRequest;
use Modules\GameTables\Http\Requests\RegisterRequest;

final class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly EligibilityServiceInterface $eligibilityService,
        private readonly GameTableQueryServiceInterface $gameTableQueryService,
    ) {}

    /**
     * Register to a game table.
     */
    public function register(RegisterRequest $request, string $id): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', __('game-tables::messages.errors.unauthenticated'));
        }

        try {
            $roleValue = $request->validated('role', ParticipantRole::Player->value);
            $role = ParticipantRole::from($roleValue);

            $this->registrationService->register(new RegisterParticipantDTO(
                gameTableId: $id,
                userId: (string) $user->id,
                role: $role,
                notes: $request->validated('notes'),
            ));

            return back()->with('success', __('game-tables::messages.success.registered'));
        } catch (RegistrationClosedException) {
            return back()->with('error', __('game-tables::messages.errors.registration_closed'));
        } catch (TableFullException) {
            return back()->with('error', __('game-tables::messages.errors.table_full'));
        } catch (AlreadyRegisteredException) {
            return back()->with('error', __('game-tables::messages.errors.already_registered'));
        } catch (MembersOnlyException) {
            return back()->with('error', __('game-tables::messages.errors.members_only'));
        } catch (MinimumAgeException) {
            return back()->with('error', __('game-tables::messages.errors.minimum_age'));
        }
    }

    /**
     * Cancel registration.
     */
    public function cancel(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', __('game-tables::messages.errors.unauthenticated'));
        }

        try {
            $this->registrationService->cancelByUser($id, (string) $user->id);

            return back()->with('success', __('game-tables::messages.success.cancelled'));
        } catch (ParticipantNotFoundException) {
            return back()->with('error', __('game-tables::messages.errors.not_found'));
        } catch (CannotCancelException) {
            return back()->with('error', __('game-tables::messages.errors.cannot_cancel'));
        }
    }

    /**
     * Register as a guest (no authentication required).
     */
    public function registerGuest(GuestRegisterRequest $request, string $id): RedirectResponse
    {
        $validated = $request->validated();

        // Check guest eligibility
        $eligibility = $this->eligibilityService->canGuestRegister($id, $validated['email']);

        if (! $eligibility['eligible']) {
            return back()->with('error', $eligibility['message'] ?? __('game-tables::messages.errors.registration_closed'));
        }

        try {
            $roleValue = $validated['role'] ?? ParticipantRole::Player->value;
            $role = ParticipantRole::from($roleValue);

            $this->registrationService->registerGuest(new RegisterParticipantDTO(
                gameTableId: $id,
                userId: null,
                role: $role,
                notes: $validated['notes'] ?? null,
                firstName: $validated['first_name'],
                lastName: null,
                email: $validated['email'],
                phone: $validated['phone'] ?? null,
            ));

            return back()->with('success', __('game-tables::messages.success.guest_registered'));
        } catch (RegistrationClosedException) {
            return back()->with('error', __('game-tables::messages.errors.registration_closed'));
        } catch (TableFullException) {
            return back()->with('error', __('game-tables::messages.errors.table_full'));
        } catch (AlreadyRegisteredException) {
            return back()->with('error', __('game-tables::messages.errors.guest_already_registered'));
        }
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

        $gameTable = $this->gameTableQueryService->find($participant->gameTableId);

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
