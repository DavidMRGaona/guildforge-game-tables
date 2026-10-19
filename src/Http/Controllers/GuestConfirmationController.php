<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\GuestConfirmationOutcome;

/**
 * The link of the guest confirmation email. Opening it only shows the page; its button (POST)
 * confirms, so the link scanners of mail providers never confirm a registration on their own.
 */
final class GuestConfirmationController extends Controller
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly GameTableQueryServiceInterface $gameTableQueryService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(string $token): Response|RedirectResponse
    {
        $participant = $this->registrationService->findByConfirmationToken($token);
        $gameTable = $participant !== null && $participant->status->isActive()
            ? $this->gameTableQueryService->findPublished($participant->gameTableId)
            : null;

        if ($participant === null || $gameTable === null) {
            return $this->invalid();
        }

        return Inertia::render('GameTables/ConfirmRegistration', [
            'participant' => [
                'first_name' => $participant->firstName,
                'role_label' => $participant->role->label(),
                'status_label' => $participant->status->label(),
            ],
            'gameTable' => [
                'title' => $gameTable->title,
                'startsAt' => $gameTable->startsAt,
                'slug' => $gameTable->slug ?? $gameTable->id,
            ],
            'token' => $token,
            'alreadyConfirmed' => ! $participant->awaitingEmailConfirmation,
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.confirm_registration_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function store(string $token): RedirectResponse
    {
        $result = $this->registrationService->confirmGuestEmail($token);

        return match ($result->outcome) {
            GuestConfirmationOutcome::Invalid => $this->invalid(),
            GuestConfirmationOutcome::Confirmed => $this->toTable($result->gameTableId)->with('success', __('game-tables::messages.success.guest_email_confirmed')),
            GuestConfirmationOutcome::AlreadyConfirmed => $this->toTable($result->gameTableId)->with('success', __('game-tables::messages.success.guest_email_already_confirmed')),
            GuestConfirmationOutcome::Closed => $this->toTable($result->gameTableId)->with('error', __('game-tables::messages.errors.confirmation_closed')),
            GuestConfirmationOutcome::FilledUp => $this->toTable($result->gameTableId)->with('error', __('game-tables::messages.errors.confirmation_filled_up')),
        };
    }

    /**
     * The table page by its slug, or by its id (which redirects to the slug); the list without one.
     */
    private function toTable(?string $gameTableId): RedirectResponse
    {
        if ($gameTableId === null) {
            return redirect()->route('gametables.index');
        }

        $slug = $this->gameTableQueryService->findPublished($gameTableId)?->slug ?? $gameTableId;

        return redirect()->route('gametables.show', $slug);
    }

    private function invalid(): RedirectResponse
    {
        return redirect()->route('gametables.index')
            ->with('error', __('game-tables::messages.errors.invalid_confirmation'));
    }
}
