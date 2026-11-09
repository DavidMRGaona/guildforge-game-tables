<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Mail;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Notifications\CampaignInvitationAcceptedNotification;
use Modules\GameTables\Notifications\CampaignInvitationNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestAcceptedNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestReceivedNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestRejectedNotification;
use Modules\GameTables\Notifications\CampaignModerationApprovedNotification;
use Modules\GameTables\Notifications\CampaignModerationRejectedNotification;
use Modules\GameTables\Notifications\CampaignModerationSubmittedNotification;
use Modules\GameTables\Notifications\CampaignPlayerLeftNotification;
use Modules\GameTables\Notifications\CampaignRosterAddedNotification;
use Modules\GameTables\Notifications\CampaignRosterRemovedNotification;
use Modules\GameTables\Notifications\CampaignSessionAbsenceNotification;
use Modules\GameTables\Notifications\CampaignSessionScheduledNotification;
use Modules\GameTables\Notifications\CancellationConfirmation;
use Modules\GameTables\Notifications\GuestCancellationConfirmation;
use Modules\GameTables\Notifications\GuestEmailConfirmationRequest;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;
use Modules\GameTables\Notifications\GuestRegistrationExpiredNotification;
use Modules\GameTables\Notifications\ModerationApprovedNotification;
use Modules\GameTables\Notifications\ModerationRejectedNotification;
use Modules\GameTables\Notifications\ModerationSubmittedNotification;
use Modules\GameTables\Notifications\OrganizationCancellationNotification;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;
use Modules\GameTables\Notifications\ParticipantConfirmedNotification;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Modules\GameTables\Notifications\ParticipantRejectedNotification;
use Modules\GameTables\Notifications\RegistrationConfirmation;
use Modules\GameTables\Notifications\TableCancelledNotification;
use Modules\GameTables\Notifications\TableReminderNotification;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use ReflectionClass;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every mail the module sends to an account names a registered category, so the profile lists
 * it and the core's filter applies the person's choice. Mail for guests has none: there is no
 * account to choose for.
 */
final class NotificationMailCategoriesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    /**
     * Mail that only ever goes to a guest's address.
     *
     * @var list<class-string<Notification>>
     */
    private const array GUEST_ONLY = [
        GuestCancellationConfirmation::class,
        GuestEmailConfirmationRequest::class,
        GuestRegistrationConfirmation::class,
        GuestRegistrationExpiredNotification::class,
    ];

    /**
     * The category each notification belongs to.
     *
     * @var array<class-string<Notification>, string>
     */
    private const array EXPECTED = [
        RegistrationConfirmation::class => GameTablesMailCategories::SIGN_UPS,
        CancellationConfirmation::class => GameTablesMailCategories::SIGN_UPS,
        ParticipantConfirmedNotification::class => GameTablesMailCategories::SIGN_UPS,
        ParticipantRejectedNotification::class => GameTablesMailCategories::SIGN_UPS,
        WaitingListPromotionNotification::class => GameTablesMailCategories::SIGN_UPS,
        TableCancelledNotification::class => GameTablesMailCategories::SIGN_UPS,
        OrganizationCancellationNotification::class => GameTablesMailCategories::SIGN_UPS,
        CampaignSessionAbsenceNotification::class => GameTablesMailCategories::SIGN_UPS,
        CampaignJoinRequestAcceptedNotification::class => GameTablesMailCategories::SIGN_UPS,
        CampaignJoinRequestRejectedNotification::class => GameTablesMailCategories::SIGN_UPS,
        CampaignRosterAddedNotification::class => GameTablesMailCategories::SIGN_UPS,
        CampaignRosterRemovedNotification::class => GameTablesMailCategories::SIGN_UPS,
        TableReminderNotification::class => GameTablesMailCategories::REMINDERS,
        CampaignSessionScheduledNotification::class => GameTablesMailCategories::CAMPAIGN_NEWS,
        CampaignInvitationNotification::class => GameTablesMailCategories::CAMPAIGN_NEWS,
        ParticipantRegisteredNotification::class => GameTablesMailCategories::DIRECTION,
        ParticipantCancelledNotification::class => GameTablesMailCategories::DIRECTION,
        WaitingListPromotionGmNotification::class => GameTablesMailCategories::DIRECTION,
        CampaignJoinRequestReceivedNotification::class => GameTablesMailCategories::DIRECTION,
        CampaignInvitationAcceptedNotification::class => GameTablesMailCategories::DIRECTION,
        CampaignPlayerLeftNotification::class => GameTablesMailCategories::DIRECTION,
        ModerationApprovedNotification::class => GameTablesMailCategories::PROPOSALS,
        ModerationRejectedNotification::class => GameTablesMailCategories::PROPOSALS,
        CampaignModerationApprovedNotification::class => GameTablesMailCategories::PROPOSALS,
        CampaignModerationRejectedNotification::class => GameTablesMailCategories::PROPOSALS,
        ModerationSubmittedNotification::class => GameTablesMailCategories::MODERATION,
        CampaignModerationSubmittedNotification::class => GameTablesMailCategories::MODERATION,
    ];

    public function test_every_notification_of_the_module_is_listed_here(): void
    {
        $listed = [...array_keys(self::EXPECTED), ...self::GUEST_ONLY];
        sort($listed);

        $found = $this->notificationClasses();
        sort($found);

        $this->assertSame($found, $listed, 'A new notification needs its category here (or the guest list)');
    }

    public function test_every_notification_to_an_account_names_a_registered_category(): void
    {
        $registry = app(MailCategoryRegistryInterface::class);

        foreach (self::EXPECTED as $class => $key) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue($reflection->implementsInterface(HasMailCategory::class), "{$class} must implement HasMailCategory");

            /** @var HasMailCategory $notification */
            $notification = $reflection->newInstanceWithoutConstructor();

            $this->assertSame($key, $notification->mailCategory(), "{$class} is in the wrong category");
            $this->assertNotNull($registry->find($key), "{$key} is not registered");
            $this->assertSame($key, $registry->forNotification($notification)?->key);
        }
    }

    public function test_guest_mail_has_no_category(): void
    {
        foreach (self::GUEST_ONLY as $class) {
            $this->assertFalse((new ReflectionClass($class))->implementsInterface(HasMailCategory::class), "{$class} goes to guests only");
        }
    }

    /**
     * @return list<string>
     */
    private function notificationClasses(): array
    {
        $classes = [];

        foreach (glob(dirname(__DIR__, 3).'/src/Notifications/*.php') ?: [] as $file) {
            $class = 'Modules\\GameTables\\Notifications\\'.basename($file, '.php');

            if (class_exists($class) && is_subclass_of($class, Notification::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
