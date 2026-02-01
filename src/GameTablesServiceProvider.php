<?php

declare(strict_types=1);

namespace Modules\GameTables;

use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Application\Services\EventQueryServiceInterface;
use App\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\EventWithTablesQueryInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Console\Commands\GenerateSlugsCommand;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Events\ParticipantRejected;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ContentWarningRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Infrastructure\Listeners\CancelParticipantsOnTableCancellation;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnCancellation;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnGuestRegistration;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnRegistration;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnWaitingListPromotion;
use Modules\GameTables\Infrastructure\Listeners\PromoteFromWaitingListOnCancellation;
use Modules\GameTables\Infrastructure\Observers\GameTableObserver;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentCampaignRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentContentWarningRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentGameSystemRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentGameTableRepository;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentParticipantRepository;
use Modules\GameTables\Infrastructure\Services\CampaignQueryService;
use Modules\GameTables\Infrastructure\Services\CreationEligibilityService;
use Modules\GameTables\Infrastructure\Services\EligibilityService;
use Modules\GameTables\Infrastructure\Services\EventWithTablesQuery;
use Modules\GameTables\Infrastructure\Services\FrontendCreationService;
use Modules\GameTables\Infrastructure\Services\GameMasterService;
use Modules\GameTables\Infrastructure\Services\GameTableQueryService;
use Modules\GameTables\Infrastructure\Services\GameTableService;
use Modules\GameTables\Infrastructure\Services\ProfileCreatedTablesDataProvider;
use Modules\GameTables\Infrastructure\Services\ProfileGameTablesDataProvider;
use Modules\GameTables\Infrastructure\Services\RegistrationService;
use Modules\GameTables\Listeners\SendCancellationConfirmation;
use Modules\GameTables\Listeners\SendConfirmationNotification;
use Modules\GameTables\Listeners\SendGuestRegistrationConfirmation;
use Modules\GameTables\Listeners\SendRegistrationConfirmation;
use Modules\GameTables\Listeners\SendRejectionNotification;

final class GameTablesServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'game-tables';
    }

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            $this->modulePath('config/game-tables.php'),
            'game-tables'
        );

        // Repository bindings
        $this->app->bind(GameSystemRepositoryInterface::class, EloquentGameSystemRepository::class);
        $this->app->bind(ContentWarningRepositoryInterface::class, EloquentContentWarningRepository::class);
        $this->app->bind(CampaignRepositoryInterface::class, EloquentCampaignRepository::class);
        $this->app->bind(GameTableRepositoryInterface::class, EloquentGameTableRepository::class);
        $this->app->bind(ParticipantRepositoryInterface::class, EloquentParticipantRepository::class);

        // Service bindings
        $this->app->bind(GameTableServiceInterface::class, GameTableService::class);
        $this->app->bind(EligibilityServiceInterface::class, EligibilityService::class);
        $this->app->bind(CreationEligibilityServiceInterface::class, CreationEligibilityService::class);
        $this->app->bind(FrontendCreationServiceInterface::class, FrontendCreationService::class);
        $this->app->bind(RegistrationServiceInterface::class, RegistrationService::class);
        $this->app->bind(EventWithTablesQueryInterface::class, EventWithTablesQuery::class);
        $this->app->bind(GameMasterServiceInterface::class, GameMasterService::class);

        // Query service bindings
        $this->app->bind(GameTableQueryServiceInterface::class, GameTableQueryService::class);
        $this->app->bind(CampaignQueryServiceInterface::class, CampaignQueryService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerModelObservers();
        $this->registerEventListeners();
        $this->registerCommands();
        $this->shareGameTableCount();
        $this->shareProfileGameTables();
        $this->shareProfileCreatedTables();
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
                GenerateSlugsCommand::class,
            ]);
        }
    }

    /**
     * Register model observers.
     */
    private function registerModelObservers(): void
    {
        \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel::observe(
            GameTableObserver::class
        );
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        Event::listen(
            ParticipantCancelled::class,
            [PromoteFromWaitingListOnCancellation::class, 'handle']
        );

        Event::listen(
            GuestRegistered::class,
            [SendGuestRegistrationConfirmation::class, 'handle']
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
     * @return array<class-string<\Filament\Resources\Resource>>
     */
    public function registerFilamentResources(): array
    {
        return [
            \Modules\GameTables\Filament\Resources\GameSystemResource::class,
            \Modules\GameTables\Filament\Resources\ContentWarningResource::class,
            \Modules\GameTables\Filament\Resources\CampaignResource::class,
            \Modules\GameTables\Filament\Resources\GameTableResource::class,
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
            \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel::class => \Modules\GameTables\Policies\GameSystemPolicy::class,
            \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel::class => \Modules\GameTables\Policies\ContentWarningPolicy::class,
            \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel::class => \Modules\GameTables\Policies\CampaignPolicy::class,
            \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel::class => \Modules\GameTables\Policies\GameTablePolicy::class,
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
                label: __('game-tables::messages.navigation.content_warnings'),
                route: 'filament.admin.resources.content-warnings.index',
                icon: 'heroicon-o-exclamation-triangle',
                group: __('game-tables::messages.navigation.group'),
                sort: 4,
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
                dataKeys: ['table', 'eligibility', 'userRegistration'],
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
                slot: 'profile-sections',
                component: 'components/profile/ProfileGameTablesSection.vue',
                module: $this->moduleName(),
                order: 10,
                props: [],
                dataKeys: ['profileGameTables'],
                profileTab: [
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
                    'icon' => 'pencil-square',
                    'labelKey' => 'gameTables.profile.created.tabLabel',
                    'badgeKey' => 'profileCreatedTablesTotal',
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
            new PagePrefixDTO(prefix: 'GameTables', module: $this->moduleName()),
            new PagePrefixDTO(prefix: 'Campaigns', module: $this->moduleName()),
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
     * Get the Filament form schema for module settings.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    public function getSettingsSchema(): array
    {
        return \Modules\GameTables\Filament\Pages\GameTablesSettings::getFormSchemaComponents();
    }

    /**
     * Register Filament pages provided by this module.
     *
     * @return array<class-string<\Filament\Pages\Page>>
     */
    public function registerFilamentPages(): array
    {
        return [
            \Modules\GameTables\Filament\Pages\GameTablesSettings::class,
        ];
    }
}
