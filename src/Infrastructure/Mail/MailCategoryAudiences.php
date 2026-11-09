<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Mail;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;

/**
 * Who sees each restricted category in the profile. It never decides whether a mail goes out.
 */
final readonly class MailCategoryAudiences
{
    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private CreationEligibilityServiceInterface $eligibility,
    ) {}

    /**
     * Game master with this account of some table or campaign, or web creator of a campaign:
     * the people the direction notices go to.
     */
    public function directs(object $user): bool
    {
        $id = $this->idOf($user);

        if ($id === null) {
            return false;
        }

        return GameMasterModel::query()->where('user_id', $id)->exists()
            || CampaignModel::query()->where('created_by', $id)->whereNotNull('frontend_creation_status')->exists();
    }

    /**
     * May propose tables or campaigns on the web, or already did.
     */
    public function proposes(object $user): bool
    {
        $id = $this->idOf($user);

        if ($id === null) {
            return false;
        }

        return $this->eligibility->canCreateTable($id)->eligible
            || $this->eligibility->canCreateCampaign($id)->eligible
            || GameTableModel::query()->where('created_by', $id)->whereNotNull('frontend_creation_status')->exists()
            || CampaignModel::query()->where('created_by', $id)->whereNotNull('frontend_creation_status')->exists();
    }

    /**
     * Same rule as the moderation mail: panel access plus the permission the panel's approve
     * and reject actions check, for tables or for campaigns.
     */
    public function moderates(object $user): bool
    {
        if (! $user instanceof UserModel) {
            return false;
        }

        return $this->authorization->canAll($user, [NotificationRecipientResolver::PANEL_ACCESS_PERMISSION, NotificationRecipientResolver::TABLES_MODERATE_PERMISSION])
            || $this->authorization->canAll($user, [NotificationRecipientResolver::PANEL_ACCESS_PERMISSION, NotificationRecipientResolver::CAMPAIGNS_MODERATE_PERMISSION]);
    }

    private function idOf(object $user): ?string
    {
        return $user instanceof UserModel ? (string) $user->id : null;
    }
}
