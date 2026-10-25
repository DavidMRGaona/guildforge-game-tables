<?php

declare(strict_types=1);

namespace Modules\GameTables;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Cookies\DTOs\DeclaredCookieDTO;
use App\Application\Cookies\Services\CookieDeclarationRegistryInterface;
use App\Application\Legal\DTOs\LegalMailNoticeDTO;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\ModuleServiceProvider;
use Filament\Pages\Page;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Components\Component;
use Filament\Widgets\Widget;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Livewire\Livewire;
use Modules\GameTables\Application\Services\AccountLookupInterface;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterQueryInterface;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventCreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Application\Services\EventWithTablesQueryInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Application\Services\ParticipantActorContextInterface;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Application\Services\TableRegistrationsQueryInterface;
use Modules\GameTables\Console\Commands\ExpireGuestRegistrationsCommand;
use Modules\GameTables\Console\Commands\GenerateSlugsCommand;
use Modules\GameTables\Console\Commands\PruneCampaignInvitationsCommand;
use Modules\GameTables\Console\Commands\PruneParticipantStatusLogCommand;
use Modules\GameTables\Console\Commands\SendTableRemindersCommand;
use Modules\GameTables\Console\Commands\UpdateTableStatusesCommand;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequested;
use Modules\GameTables\Domain\Events\CampaignJoinRequestRejected;
use Modules\GameTables\Domain\Events\CampaignModerationApproved;
use Modules\GameTables\Domain\Events\CampaignModerationRejected;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Domain\Events\CampaignSubmittedForReview;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Events\GameTableModerationApproved;
use Modules\GameTables\Domain\Events\GameTableModerationRejected;
use Modules\GameTables\Domain\Events\GameTableSubmittedForReview;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Events\ParticipantRejected;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ContentWarningRepositoryInterface;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Filament\RelationManagers\EventGameTableConfigRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\ContentWarningResource;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\PublisherResource;
use Modules\GameTables\Filament\Widgets\PendingModerationWidget;
use Modules\GameTables\Http\Controllers\CampaignInvitationController;
use Modules\GameTables\Http\Controllers\GuestConfirmationController;
use Modules\GameTables\Infrastructure\Calendar\GameTableCalendarSource;
use Modules\GameTables\Infrastructure\Listeners\CancelParticipantsOnTableCancellation;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnCancellation;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnGuestRegistration;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnRegistration;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnWaitingListPromotion;
use Modules\GameTables\Infrastructure\Observers\GameTableObserver;
use Modules\GameTables\Infrastructure\Observers\ParticipantStatusObserver;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentCampaignInvitationRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentCampaignJoinRequestRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentCampaignRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentCampaignRosterRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentContentWarningRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventGameTableConfigRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentGameSystemRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentGameTableRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentParticipantRepository;
use Modules\GameTables\Infrastructure\Services\AssociationRoleMembershipChecker;
use Modules\GameTables\Infrastructure\Services\CampaignDirection;
use Modules\GameTables\Infrastructure\Services\CampaignInvitationService;
use Modules\GameTables\Infrastructure\Services\CampaignQueryService;
use Modules\GameTables\Infrastructure\Services\CampaignRosterQuery;
use Modules\GameTables\Infrastructure\Services\CampaignRosterService;
use Modules\GameTables\Infrastructure\Services\CampaignRosterSessionSync;
use Modules\GameTables\Infrastructure\Services\CreationEligibilityService;
use Modules\GameTables\Infrastructure\Services\DatabaseCampaignInvitationSendLog;
use Modules\GameTables\Infrastructure\Services\DatabaseRegistrationLock;
use Modules\GameTables\Infrastructure\Services\EligibilityService;
use Modules\GameTables\Infrastructure\Services\EventCreationEligibilityService;
use Modules\GameTables\Infrastructure\Services\EventGameTableConfigService;
use Modules\GameTables\Infrastructure\Services\EventWithTablesQuery;
use Modules\GameTables\Infrastructure\Services\FrontendCreationService;
use Modules\GameTables\Infrastructure\Services\GameMasterService;
use Modules\GameTables\Infrastructure\Services\GameTableQueryService;
use Modules\GameTables\Infrastructure\Services\GameTableService;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Infrastructure\Services\ParticipantActorContext;
use Modules\GameTables\Infrastructure\Services\ProfileCampaignInvitationsDataProvider;
use Modules\GameTables\Infrastructure\Services\ProfileCreatedCampaignsDataProvider;
use Modules\GameTables\Infrastructure\Services\ProfileCreatedTablesDataProvider;
use Modules\GameTables\Infrastructure\Services\ProfileGameTablesDataProvider;
use Modules\GameTables\Infrastructure\Services\RegistrationService;
use Modules\GameTables\Infrastructure\Services\TableDirection;
use Modules\GameTables\Infrastructure\Services\TableRegistrationsQuery;
use Modules\GameTables\Infrastructure\Services\UserModelAccountLookup;
use Modules\GameTables\Listeners\NotifyAdminsOnCampaignModerationSubmission;
use Modules\GameTables\Listeners\NotifyAdminsOnModerationSubmission;
use Modules\GameTables\Listeners\NotifyDirectionOfInvitationAccepted;
use Modules\GameTables\Listeners\NotifyDirectionOfJoinRequest;
use Modules\GameTables\Listeners\NotifyDirectionOfPlayerLeft;
use Modules\GameTables\Listeners\NotifyUserOnCampaignModerationApproved;
use Modules\GameTables\Listeners\NotifyUserOnCampaignModerationRejected;
use Modules\GameTables\Listeners\NotifyUserOnModerationApproved;
use Modules\GameTables\Listeners\NotifyUserOnModerationRejected;
use Modules\GameTables\Listeners\SendCampaignInvitation;
use Modules\GameTables\Listeners\SendCampaignSessionScheduled;
use Modules\GameTables\Listeners\SendCancellationConfirmation;
use Modules\GameTables\Listeners\SendConfirmationNotification;
use Modules\GameTables\Listeners\SendGuestEmailConfirmationRequest;
use Modules\GameTables\Listeners\SendGuestRegistrationConfirmation;
use Modules\GameTables\Listeners\SendGuestRegistrationExpiredNotification;
use Modules\GameTables\Listeners\SendJoinRequestAccepted;
use Modules\GameTables\Listeners\SendJoinRequestRejected;
use Modules\GameTables\Listeners\SendRegistrationConfirmation;
use Modules\GameTables\Listeners\SendRejectionNotification;
use Modules\GameTables\Listeners\SendRosterMemberAdded;
use Modules\GameTables\Listeners\SendRosterMemberRemoved;
use Modules\GameTables\Notifications\CampaignInvitationNotification;
use Modules\GameTables\Policies\CampaignPolicy;
use Modules\GameTables\Policies\ContentWarningPolicy;
use Modules\GameTables\Policies\GameSystemPolicy;
use Modules\GameTables\Policies\GameTablePolicy;
use Modules\GameTables\Policies\PublisherPolicy;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class GameTablesServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'game-tables';
    }

    public function register(): void
    {
        parent::register();

        // Register model extensions early so they're available before Filament loads
        $this->registerModelExtensions();

        // Repository bindings
        $this->app->bind(GameSystemRepositoryInterface::class, EloquentGameSystemRepository::class);
        $this->app->bind(ContentWarningRepositoryInterface::class, EloquentContentWarningRepository::class);
        $this->app->bind(CampaignRepositoryInterface::class, EloquentCampaignRepository::class);
        $this->app->bind(CampaignRosterRepositoryInterface::class, EloquentCampaignRosterRepository::class);
        $this->app->bind(CampaignJoinRequestRepositoryInterface::class, EloquentCampaignJoinRequestRepository::class);
        $this->app->bind(CampaignInvitationRepositoryInterface::class, EloquentCampaignInvitationRepository::class);
        $this->app->bind(GameTableRepositoryInterface::class, EloquentGameTableRepository::class);
        $this->app->bind(ParticipantRepositoryInterface::class, EloquentParticipantRepository::class);
        $this->app->bind(EventGameTableConfigRepositoryInterface::class, EloquentEventGameTableConfigRepository::class);

        // Service bindings
        $this->app->bind(GameTableServiceInterface::class, GameTableService::class);
        $this->app->bind(EligibilityServiceInterface::class, EligibilityService::class);
        $this->app->bind(AccountLookupInterface::class, UserModelAccountLookup::class);
        $this->app->bind(CampaignInvitationSendLogInterface::class, DatabaseCampaignInvitationSendLog::class);
        $this->app->bind(MembershipCheckerInterface::class, AssociationRoleMembershipChecker::class);
        $this->app->bind(CreationEligibilityServiceInterface::class, CreationEligibilityService::class);
        $this->app->bind(FrontendCreationServiceInterface::class, FrontendCreationService::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);
        $this->app->bind(RegistrationLockInterface::class, DatabaseRegistrationLock::class);
        // One per request or queue job: a scope never reaches the next one
        $this->app->scoped(ParticipantActorContextInterface::class, ParticipantActorContext::class);
        $this->app->bind(EventWithTablesQueryInterface::class, EventWithTablesQuery::class);
        $this->app->bind(GameMasterServiceInterface::class, GameMasterService::class);
        $this->app->bind(EventGameTableConfigServiceInterface::class, EventGameTableConfigService::class);
        $this->app->bind(EventCreationEligibilityServiceInterface::class, EventCreationEligibilityService::class);
        $this->app->bind(NotificationRecipientResolverInterface::class, NotificationRecipientResolver::class);
        $this->app->bind(CampaignDirectionInterface::class, CampaignDirection::class);
        $this->app->bind(TableDirectionInterface::class, TableDirection::class);
        $this->app->bind(CampaignRosterSessionSyncInterface::class, CampaignRosterSessionSync::class);
        $this->app->bind(CampaignRosterServiceInterface::class, CampaignRosterService::class);
        $this->app->bind(CampaignInvitationServiceInterface::class, CampaignInvitationService::class);
        $this->app->bind(CampaignRosterQueryInterface::class, CampaignRosterQuery::class);
        $this->app->bind(TableRegistrationsQueryInterface::class, TableRegistrationsQuery::class);

        // Query service bindings
        $this->app->bind(GameTableQueryServiceInterface::class, GameTableQueryService::class);
        $this->app->bind(CampaignQueryServiceInterface::class, CampaignQueryService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Register Livewire components first (before Filament extensions)
        $this->registerLivewireComponents();
        $this->registerFilamentExtensions();
        $this->registerModelObservers();
        $this->registerEventListeners();
        $this->registerCommands();
        $this->registerSchedule();
        $this->registerRateLimiters();
        $this->registerCookieDeclaration();
        $this->registerLegalMailNotice();
        $this->shareGameTableCount();
        $this->shareProfileGameTables();
        $this->shareProfileCreatedTables();
        $this->shareProfileCreatedCampaigns();
        $this->shareProfileCampaignInvitations();
        $this->shareEventCreationContext();
    }

    /**
     * Share profile game tables data via Inertia for the profile page.
     */
    private function shareProfileGameTables(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('profileGameTables', function (): ?array {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileGameTablesDataProvider::class);

            return $provider->getDataForUser($user->id);
        });

        Inertia::share('profileGameTablesTotal', function (): ?int {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileGameTablesDataProvider::class);
            $data = $provider->getDataForUser($user->id);

            return $data['total'] ?? 0;
        });
    }

    /**
     * Share profile created tables data via Inertia for the profile page.
     */
    private function shareProfileCreatedTables(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('profileCreatedTables', function (): ?array {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileCreatedTablesDataProvider::class);

            return $provider->getDataForUser($user->id);
        });

        Inertia::share('profileCreatedTablesTotal', function (): ?int {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileCreatedTablesDataProvider::class);
            $data = $provider->getDataForUser($user->id);

            return $data['total'] ?? 0;
        });
    }

    /**
     * Share profile created campaigns data via Inertia for the profile page.
     */
    private function shareProfileCreatedCampaigns(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('profileCreatedCampaigns', function (): ?array {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileCreatedCampaignsDataProvider::class);

            return $provider->getDataForUser($user->id);
        });

        Inertia::share('profileCreatedCampaignsTotal', function (): ?int {
            $route = request()->route();
            if ($route?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if ($user === null) {
                return null;
            }

            $provider = app(ProfileCreatedCampaignsDataProvider::class);
            $data = $provider->getDataForUser($user->id);

            return $data['total'] ?? 0;
        });
    }

    /**
     * Share the pending campaign invitations of the account's verified email on the profile page.
     * The tab is visible only for a verified email with at least one invitation.
     */
    private function shareProfileCampaignInvitations(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        // One lookup per request for the three props
        $data = static function (): ?array {
            $request = request();
            if ($request->route()?->getName() !== 'profile.show') {
                return null;
            }

            $user = auth()->user();
            if (! $user instanceof UserModel) {
                return null;
            }

            if (! $request->attributes->has('game-tables.profile-invitations')) {
                $request->attributes->set(
                    'game-tables.profile-invitations',
                    app(ProfileCampaignInvitationsDataProvider::class)->getDataForUser($user),
                );
            }

            return $request->attributes->get('game-tables.profile-invitations');
        };

        Inertia::share('profileCampaignInvitations', static fn (): ?array => $data());
        Inertia::share('profileCampaignInvitationsTotal', static fn (): ?int => $data()['total'] ?? null);
        Inertia::share('profileCampaignInvitationsVisible', static fn (): bool => ($data()['total'] ?? 0) > 0);
    }

    /**
     * Share game table count via Inertia for the event detail page.
     */
    private function shareGameTableCount(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('gameTableCount', function (): ?int {
            $route = request()->route();
            if ($route?->getName() !== 'events.show') {
                return null;
            }

            $slug = request()->route('slug');
            if (! is_string($slug) || $slug === '') {
                return null;
            }

            $eventQuery = app(EventQueryServiceInterface::class);
            $event = $eventQuery->findPublishedBySlug($slug);
            if ($event === null) {
                return null;
            }

            $queryService = app(GameTableQueryServiceInterface::class);

            return $queryService->getPublishedTablesTotal(eventId: $event->id);
        });
    }

    /**
     * Register console commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireGuestRegistrationsCommand::class,
                GenerateSlugsCommand::class,
                PruneCampaignInvitationsCommand::class,
                PruneParticipantStatusLogCommand::class,
                UpdateTableStatusesCommand::class,
                SendTableRemindersCommand::class,
            ]);
        }
    }

    /**
     * Tables start and complete with the clock (off with automation.auto_status_enabled).
     * Unconfirmed guest registrations expire with the clock whatever that setting says.
     */
    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('game-tables:update-statuses')
                ->everyFiveMinutes()
                // A run takes seconds; a killed one must not block the updates for a day
                ->withoutOverlapping(10);

            $schedule->command('game-tables:expire-guest-registrations')
                ->everyFiveMinutes()
                ->withoutOverlapping(10);

            $schedule->command('game-tables:prune-campaign-invitations')
                ->daily()
                ->withoutOverlapping(10);

            $schedule->command('game-tables:prune-participant-status-log')
                ->daily()
                ->withoutOverlapping(10);

            // Does nothing until the reminder is enabled in the module settings
            $schedule->command('game-tables:send-reminders')
                ->hourly()
                ->withoutOverlapping(10);
        });
    }

    /**
     * The browser hint cookie of the guest form, declared in the core's registry: a cookie
     * consent module, when installed, lists it in its banner.
     */
    private function registerCookieDeclaration(): void
    {
        $this->callAfterResolving(CookieDeclarationRegistryInterface::class, function (CookieDeclarationRegistryInterface $registry): void {
            $registry->declare(new DeclaredCookieDTO(
                name: 'gt_guest_registrations',
                categorySlug: 'preferences',
                // The association's name from the site settings, APP_NAME when it is blank
                provider: $this->app->make(SiteBrandingServiceInterface::class)->siteName(),
                purpose: (string) __('game-tables::messages.cookies.guest_registrations.purpose'),
                duration: (string) __('game-tables::messages.cookies.guest_registrations.duration'),
            ));
        });
    }

    /**
     * The legal block of the campaign invitation email, editable from the core's legal pages when
     * the core offers them (an older core keeps the fixed lines).
     */
    private function registerLegalMailNotice(): void
    {
        if (! interface_exists(LegalMailNoticeRegistryInterface::class)) {
            return;
        }

        $this->callAfterResolving(LegalMailNoticeRegistryInterface::class, function (LegalMailNoticeRegistryInterface $registry): void {
            $registry->register(new LegalMailNoticeDTO(
                key: CampaignInvitationNotification::NOTICE_KEY,
                module: 'game-tables',
                label: (string) __('game-tables::emails.campaign_invitation.notice_label'),
                help: (string) __('game-tables::emails.campaign_invitation.notice_help'),
                defaultContent: (string) __('game-tables::emails.campaign_invitation.privacy_default'),
                placeholders: [':inviter', ':campaign', ':site', ':days'],
                preview: static fn (string $content): string => (string) (new CampaignInvitationNotification(
                    inviterName: 'John Doe',
                    campaignTitle: (string) __('game-tables::emails.campaign_invitation.preview_campaign'),
                    campaignSlug: 'campana-de-ejemplo',
                    email: 'john@example.com',
                    token: Str::random(64),
                    expiresAt: now()->addDays(CampaignInvitationNotification::EXPIRY_DAYS)->format('d/m/Y'),
                    legalText: $content,
                ))->toMail(new AnonymousNotifiable)->render(),
            ));
        });
    }

    /**
     * Per IP, over the limit a translated message, never an error page. The guest confirmation
     * link (page and button): wide enough for a person, tight enough to stop guessing tokens. The
     * guest registration form: the limits of the settings, as a form error on the table page. The
     * requests to join a campaign and the players added from the roster page are limited per user
     * instead, as a flash on the page.
     */
    private function registerRateLimiters(): void
    {
        // The invitation page and its actions: the token is the credential, so guessing is limited per IP
        RateLimiter::for('game-tables-campaign-invitation', static fn (Request $request): Limit => Limit::perMinute(30)->by('gt-invitation:'.($request->ip() ?? 'unknown'))->response(
            static function (Request $request, array $headers): HttpResponse {
                $message = (string) __('game-tables::messages.errors.too_many_confirmation_attempts');

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, $headers);
                }

                // The page with the message, still 429: a plain 429 would land in Inertia's raw error modal
                $token = $request->route('token');
                $response = app(CampaignInvitationController::class)
                    ->limitedPage($message, is_string($token) ? $token : '')
                    ->toResponse($request);
                $response->setStatusCode(429);
                $response->headers->add($headers);

                return $response;
            },
        ));

        RateLimiter::for('game-tables-guest-confirmation', static function (Request $request): array {
            $ip = $request->ip() ?? 'unknown';
            $tooMany = static function (Request $request, array $headers): HttpResponse {
                $message = (string) __('game-tables::messages.errors.too_many_confirmation_attempts');

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, $headers);
                }

                // The confirmation page with the message, still 429: a plain 429 would land in
                // Inertia's raw error modal, and the list of tables shows no flash message
                $token = $request->route('token');

                $response = app(GuestConfirmationController::class)
                    ->messagePage(is_string($token) ? $token : '', $message)
                    ->toResponse($request);
                $response->setStatusCode(429);
                $response->headers->add($headers);

                return $response;
            };

            // Distinct keys: limits sharing a key would share one counter
            return [
                Limit::perMinute(20)->by('gt-confirm:'.$ip)->response($tooMany),
                Limit::perHour(100)->by('gt-confirm-h:'.$ip)->response($tooMany),
            ];
        });

        RateLimiter::for('game-tables-guest-registration', static function (Request $request): array {
            $ip = $request->ip() ?? 'unknown';
            $settings = app(GameTableSettingsReader::class);
            $tooMany = static function (Request $request, array $headers): HttpResponse {
                $message = (string) __('game-tables::messages.errors.guest_rate_limited');

                // Inertia cannot show a 429 inside the form: the error goes where the refusals go
                return $request->expectsJson()
                    ? response()->json(['message' => $message], 429, $headers)
                    : back()->withErrors(['registration' => $message])->withInput();
            };

            return [
                Limit::perMinute($settings->guestRateLimitPerMinute())->by('gt-guest:'.$ip)->response($tooMany),
                Limit::perHour($settings->guestRateLimitPerHour())->by('gt-guest-h:'.$ip)->response($tooMany),
            ];
        });

        // Requests to join a campaign, per user (the route sits behind auth)
        RateLimiter::for('game-tables-campaign-join', static function (Request $request): array {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown');
            $tooMany = static function (Request $request, array $headers): HttpResponse {
                $message = (string) __('game-tables::messages.roster.errors.too_many_requests');

                // Inertia cannot show a 429 on the campaign page: the error goes where the refusals go
                return $request->expectsJson()
                    ? response()->json(['message' => $message], 429, $headers)
                    : back()->with('error', $message);
            };

            return [
                Limit::perMinute(5)->by('gt-join:'.$userId)->response($tooMany),
                Limit::perHour(20)->by('gt-join-h:'.$userId)->response($tooMany),
            ];
        });

        // Players added by email from the roster page, per user: enough for a direction filling
        // its roster, too few to try addresses one after another
        RateLimiter::for('game-tables-campaign-roster-add', static function (Request $request): Limit {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown');

            return Limit::perMinute(20)->by('gt-roster-add:'.$userId)->response(static function (Request $request, array $headers): HttpResponse {
                $message = (string) __('game-tables::messages.roster.errors.too_many_additions');

                // As the refusals of the roster page: a flash, not Inertia's raw 429
                return $request->expectsJson()
                    ? response()->json(['message' => $message], 429, $headers)
                    : back()->with('error', $message);
            });
        });

        // Approvals and rejections from the «Gestionar inscritos» page, per user, as the roster
        // additions: enough to answer a table's requests, not to hammer the service
        RateLimiter::for('game-tables-table-registrations', static function (Request $request): Limit {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown');

            return Limit::perMinute(20)->by('gt-table-registrations:'.$userId)->response(static function (Request $request, array $headers): HttpResponse {
                $message = (string) __('game-tables::messages.registrations.errors.too_many_actions');

                // As the refusals of the page: a flash, not Inertia's raw 429
                return $request->expectsJson()
                    ? response()->json(['message' => $message], 429, $headers)
                    : back()->with('error', $message);
            });
        });
    }

    /**
     * Register model observers.
     */
    private function registerModelObservers(): void
    {
        GameTableModel::observe(
            GameTableObserver::class
        );

        ParticipantModel::observe(
            ParticipantStatusObserver::class
        );
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        Event::listen(
            GuestConfirmationRequested::class,
            [SendGuestEmailConfirmationRequest::class, 'handle']
        );

        Event::listen(
            GuestRegistered::class,
            [SendGuestRegistrationConfirmation::class, 'handle']
        );

        Event::listen(
            GuestRegistrationExpired::class,
            [SendGuestRegistrationExpiredNotification::class, 'handle']
        );

        Event::listen(
            ParticipantRegistered::class,
            [NotifyOnRegistration::class, 'handle']
        );

        Event::listen(
            ParticipantRegistered::class,
            [SendRegistrationConfirmation::class, 'handle']
        );

        Event::listen(
            GuestRegistered::class,
            [NotifyOnGuestRegistration::class, 'handle']
        );

        Event::listen(
            ParticipantCancelled::class,
            [NotifyOnCancellation::class, 'handle']
        );

        Event::listen(
            ParticipantCancelled::class,
            [SendCancellationConfirmation::class, 'handle']
        );

        Event::listen(
            ParticipantPromotedFromWaitingList::class,
            [NotifyOnWaitingListPromotion::class, 'handle']
        );

        Event::listen(
            ParticipantConfirmed::class,
            [SendConfirmationNotification::class, 'handle']
        );

        Event::listen(
            ParticipantRejected::class,
            [SendRejectionNotification::class, 'handle']
        );

        Event::listen(
            GameTableCancelled::class,
            [CancelParticipantsOnTableCancellation::class, 'handle']
        );

        // Moderation event listeners
        Event::listen(
            GameTableSubmittedForReview::class,
            [NotifyAdminsOnModerationSubmission::class, 'handle']
        );

        Event::listen(
            CampaignSubmittedForReview::class,
            [NotifyAdminsOnCampaignModerationSubmission::class, 'handle']
        );

        Event::listen(
            GameTableModerationApproved::class,
            [NotifyUserOnModerationApproved::class, 'handle']
        );

        Event::listen(
            GameTableModerationRejected::class,
            [NotifyUserOnModerationRejected::class, 'handle']
        );

        Event::listen(
            CampaignModerationApproved::class,
            [NotifyUserOnCampaignModerationApproved::class, 'handle']
        );

        Event::listen(
            CampaignModerationRejected::class,
            [NotifyUserOnCampaignModerationRejected::class, 'handle']
        );

        // Campaign roster emails
        Event::listen(
            RosterMemberEnrolledInSession::class,
            [SendCampaignSessionScheduled::class, 'handle']
        );

        Event::listen(
            CampaignJoinRequested::class,
            [NotifyDirectionOfJoinRequest::class, 'handle']
        );

        Event::listen(
            CampaignJoinRequestAccepted::class,
            [SendJoinRequestAccepted::class, 'handle']
        );

        Event::listen(
            CampaignJoinRequestRejected::class,
            [SendJoinRequestRejected::class, 'handle']
        );

        Event::listen(
            CampaignRosterMemberAdded::class,
            [SendRosterMemberAdded::class, 'handle']
        );

        Event::listen(
            CampaignInvitationSent::class,
            [SendCampaignInvitation::class, 'handle']
        );

        Event::listen(
            CampaignInvitationAccepted::class,
            [NotifyDirectionOfInvitationAccepted::class, 'handle']
        );

        Event::listen(
            CampaignRosterMemberRemoved::class,
            [SendRosterMemberRemoved::class, 'handle']
        );

        Event::listen(
            CampaignRosterMemberLeft::class,
            [NotifyDirectionOfPlayerLeft::class, 'handle']
        );
    }

    /**
     * Register dynamic relationships on core models.
     * Uses resolveRelationUsing() - the correct Laravel way to add relationships dynamically.
     * Called in register() to ensure relationships are available before Filament loads.
     */
    private function registerModelExtensions(): void
    {
        // Add gameTableConfig() relationship to EventModel for configuration form section
        // Note: resolveRelationUsing is idempotent - calling it multiple times is safe
        EventModel::resolveRelationUsing('gameTableConfig', fn (EventModel $eventModel) => $eventModel->hasOne(EventGameTableConfigModel::class, 'event_id', 'id'));

        // Add gameTables() so the core EventModel does not need to reference this module.
        // While a core still declares the method, the method wins and this is unused.
        EventModel::resolveRelationUsing('gameTables', fn (EventModel $eventModel) => $eventModel->hasMany(GameTableModel::class, 'event_id'));
    }

    /**
     * Register Livewire components from the module.
     */
    private function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        // Register RelationManagers
        Livewire::component(
            'modules.game-tables.filament.relation-managers.event-game-table-config-relation-manager',
            EventGameTableConfigRelationManager::class
        );
    }

    /**
     * Register Filament extensions: RelationManagers for event game table configuration.
     */
    private function registerFilamentExtensions(): void
    {
        if (! class_exists(EventResource::class)) {
            return;
        }

        // Add config RelationManager to EventResource
        EventResource::extendRelations([
            EventGameTableConfigRelationManager::class,
        ]);
    }

    /**
     * Share event creation context via Inertia for the event detail page.
     */
    private function shareEventCreationContext(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('eventCreationContext', function (): ?array {
            $route = request()->route();
            if ($route?->getName() !== 'events.show') {
                return null;
            }

            $slug = request()->route('slug');
            if (! is_string($slug) || $slug === '') {
                return null;
            }

            $eventQuery = app(EventQueryServiceInterface::class);
            $event = $eventQuery->findPublishedBySlug($slug);
            if ($event === null) {
                return null;
            }

            $configService = app(EventGameTableConfigServiceInterface::class);
            $context = $configService->getCreationContext($event->id);

            return $context->toArray();
        });

        Inertia::share('eventCreationEligibility', function (): ?array {
            $route = request()->route();
            if ($route?->getName() !== 'events.show') {
                return null;
            }

            $slug = request()->route('slug');
            if (! is_string($slug) || $slug === '') {
                return null;
            }

            $eventQuery = app(EventQueryServiceInterface::class);
            $event = $eventQuery->findPublishedBySlug($slug);
            if ($event === null) {
                return null;
            }

            // The same rule as the create page: an ended event offers no button
            $userId = auth()->id();
            $eligibility = app(FrontendCreationServiceInterface::class)->eventCreationEligibility(
                $event->id,
                $userId !== null ? (string) $userId : null,
            );

            return $eligibility->toArray();
        });
    }

    public function onEnable(): void
    {
        // Migration is handled by the module system
    }

    public function onDisable(): void
    {
        // Cleanup if needed
    }

    /**
     * Register Filament resources provided by this module.
     *
     * @return array<class-string<FilamentResource>>
     */
    public function registerFilamentResources(): array
    {
        return [
            GameSystemResource::class,
            PublisherResource::class,
            ContentWarningResource::class,
            CampaignResource::class,
            GameTableResource::class,
        ];
    }

    /**
     * Register policies provided by this module.
     *
     * @return array<class-string, class-string>
     */
    public function registerPolicies(): array
    {
        return [
            GameSystemModel::class => GameSystemPolicy::class,
            PublisherModel::class => PublisherPolicy::class,
            ContentWarningModel::class => ContentWarningPolicy::class,
            CampaignModel::class => CampaignPolicy::class,
            GameTableModel::class => GameTablePolicy::class,
        ];
    }

    /**
     * Register navigation groups provided by this module.
     *
     * @return array<string, array{icon?: string, sort?: int}>
     */
    public function registerNavigationGroups(): array
    {
        return [
            __('game-tables::messages.navigation.group') => [
                'sort' => 12, // After 'Contenido' (10), before 'Socios' (15)
            ],
        ];
    }

    /**
     * @return array<PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [
            // GameTable permissions
            new PermissionDTO(
                name: 'gametables.view_any',
                label: __('game-tables::messages.permissions.gametables.view_any'),
                group: __('game-tables::messages.navigation.tables'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'gametables.view',
                label: __('game-tables::messages.permissions.gametables.view'),
                group: __('game-tables::messages.navigation.tables'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'gametables.create',
                label: __('game-tables::messages.permissions.gametables.create'),
                group: __('game-tables::messages.navigation.tables'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'gametables.update',
                label: __('game-tables::messages.permissions.gametables.update'),
                group: __('game-tables::messages.navigation.tables'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'gametables.delete',
                label: __('game-tables::messages.permissions.gametables.delete'),
                group: __('game-tables::messages.navigation.tables'),
                module: 'gametables',
                roles: [],
            ),

            // Campaign permissions
            new PermissionDTO(
                name: 'campaigns.view_any',
                label: __('game-tables::messages.permissions.campaigns.view_any'),
                group: __('game-tables::messages.navigation.campaigns'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'campaigns.view',
                label: __('game-tables::messages.permissions.campaigns.view'),
                group: __('game-tables::messages.navigation.campaigns'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'campaigns.create',
                label: __('game-tables::messages.permissions.campaigns.create'),
                group: __('game-tables::messages.navigation.campaigns'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'campaigns.update',
                label: __('game-tables::messages.permissions.campaigns.update'),
                group: __('game-tables::messages.navigation.campaigns'),
                module: 'gametables',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'campaigns.delete',
                label: __('game-tables::messages.permissions.campaigns.delete'),
                group: __('game-tables::messages.navigation.campaigns'),
                module: 'gametables',
                roles: [],
            ),

            // GameSystem permissions
            new PermissionDTO(
                name: 'gamesystems.view_any',
                label: __('game-tables::messages.permissions.gamesystems.view_any'),
                group: __('game-tables::messages.navigation.config'),
                module: 'gametables',
                roles: [],
            ),
            new PermissionDTO(
                name: 'gamesystems.manage',
                label: __('game-tables::messages.permissions.gamesystems.manage'),
                group: __('game-tables::messages.navigation.config'),
                module: 'gametables',
                roles: [],
            ),

            // ContentWarning permissions
            new PermissionDTO(
                name: 'contentwarnings.view_any',
                label: __('game-tables::messages.permissions.contentwarnings.view_any'),
                group: __('game-tables::messages.navigation.config'),
                module: 'gametables',
                roles: [],
            ),
            new PermissionDTO(
                name: 'contentwarnings.manage',
                label: __('game-tables::messages.permissions.contentwarnings.manage'),
                group: __('game-tables::messages.navigation.config'),
                module: 'gametables',
                roles: [],
            ),

            // Settings permission
            new PermissionDTO(
                name: 'gametables.settings',
                label: __('game-tables::messages.permissions.settings'),
                group: __('game-tables::messages.navigation.config'),
                module: 'gametables',
                roles: [],
            ),
        ];
    }

    /**
     * @return array<NavigationItemDTO>
     */
    public function registerNavigation(): array
    {
        return [
            new NavigationItemDTO(
                label: __('game-tables::messages.navigation.tables'),
                route: 'filament.admin.resources.game-tables.index',
                icon: 'heroicon-o-table-cells',
                group: __('game-tables::messages.navigation.group'),
                sort: 1,
                permissions: ['gametables:gametables.view_any'],
                module: 'gametables',
            ),
            new NavigationItemDTO(
                label: __('game-tables::messages.navigation.campaigns'),
                route: 'filament.admin.resources.campaigns.index',
                icon: 'heroicon-o-book-open',
                group: __('game-tables::messages.navigation.group'),
                sort: 2,
                permissions: ['gametables:campaigns.view_any'],
                module: 'gametables',
            ),
            new NavigationItemDTO(
                label: __('game-tables::messages.navigation.game_systems'),
                route: 'filament.admin.resources.game-systems.index',
                icon: 'heroicon-o-puzzle-piece',
                group: __('game-tables::messages.navigation.group'),
                sort: 3,
                permissions: ['gametables:gamesystems.view_any'],
                module: 'gametables',
            ),
            new NavigationItemDTO(
                label: __('game-tables::messages.navigation.publishers'),
                route: 'filament.admin.resources.publishers.index',
                icon: 'heroicon-o-building-office',
                group: __('game-tables::messages.navigation.group'),
                sort: 4,
                permissions: ['gametables:gamesystems.view_any'],
                module: 'gametables',
            ),
            new NavigationItemDTO(
                label: __('game-tables::messages.navigation.content_warnings'),
                route: 'filament.admin.resources.content-warnings.index',
                icon: 'heroicon-o-exclamation-triangle',
                group: __('game-tables::messages.navigation.group'),
                sort: 5,
                permissions: ['gametables:contentwarnings.view_any'],
                module: 'gametables',
            ),
        ];
    }

    /**
     * Register slots provided by this module.
     *
     * @return array<SlotRegistrationDTO>
     */
    public function registerSlots(): array
    {
        return [
            new SlotRegistrationDTO(
                slot: 'game-table-registration',
                component: 'components/RegistrationButton.vue',
                module: $this->moduleName(),
                order: 0,
                props: [],
                dataKeys: ['table', 'eligibility', 'guestEligibility', 'userRegistration', 'campaignSession'],
            ),
            new SlotRegistrationDTO(
                slot: 'event-detail-actions',
                component: 'components/EventTablesLink.vue',
                module: $this->moduleName(),
                order: 10,
                props: [],
                dataKeys: ['event', 'gameTableCount'],
            ),
            new SlotRegistrationDTO(
                slot: 'event-detail-actions',
                component: 'components/EventCreateTableButton.vue',
                module: $this->moduleName(),
                order: 20,
                props: [],
                dataKeys: ['event', 'eventCreationEligibility'],
            ),
            new SlotRegistrationDTO(
                slot: 'profile-sections',
                component: 'components/profile/ProfileGameTablesSection.vue',
                module: $this->moduleName(),
                order: 10,
                props: [],
                dataKeys: ['profileGameTables'],
                profileTab: [
                    'tabId' => 'gametables',
                    'icon' => 'dice',
                    'labelKey' => 'gameTables.profile.tabLabel',
                    'badgeKey' => 'profileGameTablesTotal',
                ],
            ),
            new SlotRegistrationDTO(
                slot: 'profile-sections',
                component: 'components/profile/ProfileCreatedTablesSection.vue',
                module: $this->moduleName(),
                order: 15,
                props: [],
                dataKeys: ['profileCreatedTables'],
                profileTab: [
                    'tabId' => 'gametables-created',
                    'parentId' => 'gametables',
                    'icon' => 'pencil-square',
                    'labelKey' => 'gameTables.profile.created.tabLabel',
                    'badgeKey' => 'profileCreatedTablesTotal',
                ],
            ),
            new SlotRegistrationDTO(
                slot: 'profile-sections',
                component: 'components/profile/ProfileCreatedCampaignsSection.vue',
                module: $this->moduleName(),
                order: 16,
                props: [],
                dataKeys: ['profileCreatedCampaigns'],
                profileTab: [
                    'tabId' => 'gametables-campaigns',
                    'parentId' => 'gametables',
                    'icon' => 'book-open',
                    'labelKey' => 'campaigns.profile.tabLabel',
                    'badgeKey' => 'profileCreatedCampaignsTotal',
                ],
            ),
            new SlotRegistrationDTO(
                slot: 'profile-sections',
                component: 'components/profile/ProfileCampaignInvitationsSection.vue',
                module: $this->moduleName(),
                // Tabs are listed by order across modules: stay before the others' tabs (20)
                order: 17,
                props: [],
                // badgeKey and visibleKey are read from the slot's props, so they are listed too
                dataKeys: ['profileCampaignInvitations', 'profileCampaignInvitationsTotal', 'profileCampaignInvitationsVisible'],
                profileTab: [
                    'tabId' => 'gametables-invitations',
                    'parentId' => 'gametables',
                    'icon' => 'envelope',
                    'labelKey' => 'gameTables.profile.invitations.tabLabel',
                    'badgeKey' => 'profileCampaignInvitationsTotal',
                    'visibleKey' => 'profileCampaignInvitationsVisible',
                ],
            ),
        ];
    }

    /**
     * Register page prefixes provided by this module.
     * Allows module Vue pages to be resolved by Inertia.
     *
     * @return array<PagePrefixDTO>
     */
    public function registerPagePrefixes(): array
    {
        return [
            new PagePrefixDTO(prefix: 'GameTables', module: $this->moduleName(), usesCoreLayout: true),
            new PagePrefixDTO(prefix: 'Campaigns', module: $this->moduleName(), usesCoreLayout: true),
        ];
    }

    /**
     * Register public routes for menu item configuration.
     *
     * @return array<ModuleRouteDTO>
     */
    public function registerRoutes(): array
    {
        return [
            new ModuleRouteDTO(
                routeName: 'gametables.index',
                label: __('game-tables::messages.routes.tables'),
                module: $this->moduleName(),
            ),
            new ModuleRouteDTO(
                routeName: 'gametables.calendar',
                label: __('game-tables::messages.routes.tables_calendar'),
                module: $this->moduleName(),
            ),
            new ModuleRouteDTO(
                routeName: 'campaigns.index',
                label: __('game-tables::messages.routes.campaigns'),
                module: $this->moduleName(),
            ),
        ];
    }

    /**
     * Register calendar sources provided by this module.
     * Allows game tables to appear in the public calendars.
     *
     * @return array<class-string<CalendarEntrySourceInterface>>
     */
    public function registerCalendarSources(): array
    {
        return [
            GameTableCalendarSource::class,
        ];
    }

    /**
     * Get the Filament form schema for module settings.
     *
     * @return array<Component>
     */
    public function getSettingsSchema(): array
    {
        return GameTablesSettings::getFormSchemaComponents();
    }

    /**
     * Register Filament pages provided by this module.
     *
     * @return array<class-string<Page>>
     */
    public function registerFilamentPages(): array
    {
        return [
            GameTablesSettings::class,
        ];
    }

    /**
     * Register Filament widgets provided by this module.
     *
     * @return array<class-string<Widget>>
     */
    public function registerFilamentWidgets(): array
    {
        return [
            PendingModerationWidget::class,
        ];
    }
}
