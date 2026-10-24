<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GameTables\Application\DTOs\TableRegistrationRowDTO;
use Modules\GameTables\Application\Services\ParticipantActorContextInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Application\Services\TableRegistrationsQueryInterface;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\ParticipantAwaitingEmailException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotPendingException;
use Modules\GameTables\Domain\Exceptions\TableClosedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Http\Requests\RejectTableRegistrationRequest;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * The «Gestionar inscritos» page of a table that delegates its registrations, and its two
 * actions. Every route asks the policy (manageRegistrations, 403) and every action loads the
 * participant from the table of the URL (404). A director only answers pending requests, never
 * approves a guest whose email is unconfirmed (only the organisation vouches for it) and never
 * takes the table over its seats: the service's director methods check all of it under the lock;
 * the checks here only spare the visit.
 */
final class TableRegistrationsController extends Controller
{
    public function __construct(
        private readonly TableRegistrationsQueryInterface $query,
        private readonly RegistrationServiceInterface $registrations,
        private readonly ParticipantActorContextInterface $actor,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(GameTableModel $gameTable): Response
    {
        Gate::authorize('manageRegistrations', $gameTable);

        return Inertia::render('GameTables/Registrations', [
            ...$this->query->page($gameTable->id)->toArray(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('game-tables::messages.seo.registrations_title', ['title' => $gameTable->title]),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function approve(GameTableModel $gameTable, string $participantId): RedirectResponse
    {
        $row = $this->authorizedRow($gameTable, $participantId);

        if ($row->status !== ParticipantStatus::Pending->value) {
            return $this->notPending();
        }

        if ($row->awaitingEmailConfirmation) {
            return $this->awaitingEmail();
        }

        return $this->attempt(
            fn () => $this->registrations->approveAsDirector($row->id),
            null,
            (string) __('game-tables::messages.registrations.success.approved', ['name' => $row->name]),
        );
    }

    public function reject(RejectTableRegistrationRequest $request, GameTableModel $gameTable, string $participantId): RedirectResponse
    {
        $row = $this->authorizedRow($gameTable, $participantId);

        // The service also rejects from the waiting list; a director only answers requests
        if (! $row->canReject) {
            return $this->notPending();
        }

        // Blank is no reason, also in the status history
        $reason = $request->validated('reason');
        $reason = is_string($reason) && trim($reason) !== '' ? trim($reason) : null;

        return $this->attempt(
            fn () => $this->registrations->rejectAsDirector($row->id, $reason),
            $reason,
            (string) __('game-tables::messages.registrations.success.rejected', ['name' => $row->name]),
        );
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 when the user may not manage the table, 404 for a participant of another table
     */
    private function authorizedRow(GameTableModel $gameTable, string $participantId): TableRegistrationRowDTO
    {
        Gate::authorize('manageRegistrations', $gameTable);

        $row = $this->query->row($gameTable->id, $participantId);

        if ($row === null) {
            abort(404);
        }

        return $row;
    }

    /**
     * Runs the action in the director's name (the status history credits them) and answers with
     * its success, or with the refusal it met.
     *
     * @param  Closure(): mixed  $action
     */
    private function attempt(Closure $action, ?string $reason, string $success): RedirectResponse
    {
        try {
            $this->actor->run($this->actorId(), $reason, $action);
        } catch (ParticipantNotPendingException) {
            return $this->notPending();
        } catch (ParticipantAwaitingEmailException) {
            return $this->awaitingEmail();
        } catch (TableFullException $e) {
            return back()->with('error', __('game-tables::messages.errors.'.$e->reason));
        } catch (TableClosedException $e) {
            return back()->with('error', __('game-tables::messages.registrations.errors.'.$e->reason));
        }

        return back()->with('success', $success);
    }

    private function notPending(): RedirectResponse
    {
        return back()->with('error', __('game-tables::messages.errors.not_pending'));
    }

    private function awaitingEmail(): RedirectResponse
    {
        return back()->with('error', __('game-tables::messages.registrations.errors.awaiting_email'));
    }

    private function actorId(): string
    {
        /** @var UserModel $user */
        $user = auth()->user();

        return (string) $user->id;
    }
}
