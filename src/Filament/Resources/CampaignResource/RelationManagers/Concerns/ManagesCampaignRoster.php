<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\Concerns;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;

/**
 * Shared by the roster and request tabs of a campaign. Everything on them follows one rule: the
 * person may update the campaign. The related model's own policy (users) never decides, so the tabs
 * do not ask for the users permission.
 */
trait ManagesCampaignRoster
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('update', $ownerRecord);
    }

    /**
     * Filament asks this for the table and its stock actions: seeing the tab (viewAny, view)
     * follows the campaign's update permission instead of the related model's policy; anything
     * else (create, edit, delete...) is denied, so a stock action added later grants nothing. The
     * tab's own actions authorise themselves with canManage().
     */
    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (in_array($action, ['viewAny', 'view'], true)) {
            return Gate::inspect('update', $this->getOwnerRecord());
        }

        return Response::deny();
    }

    private function canManage(): bool
    {
        return Gate::allows('update', $this->getOwnerRecord());
    }

    /**
     * Runs a roster change: a refusal becomes a danger notification, success the matching copy.
     *
     * @param  Closure(): void  $change
     */
    private function run(Closure $change, string $success): void
    {
        try {
            $change();
        } catch (RosterActionRefusedException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('game-tables::messages.roster.success.'.$success))->success()->send();
    }
}
