<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\EarlyAccessTier;
use Modules\GameTables\Infrastructure\Services\EventCreationEligibilityService;
use Tests\TestCase;

final class EventCreationEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventGameTableConfigRepositoryInterface&MockInterface $configRepository;

    private CreationEligibilityServiceInterface&MockInterface $globalEligibilityService;

    private AuthorizationServiceInterface&MockInterface $authorizationService;

    private EventCreationEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configRepository = Mockery::mock(EventGameTableConfigRepositoryInterface::class);
        $this->globalEligibilityService = Mockery::mock(CreationEligibilityServiceInterface::class);
        $this->authorizationService = Mockery::mock(AuthorizationServiceInterface::class);

        $this->service = new EventCreationEligibilityService(
            $this->configRepository,
            $this->authorizationService,
            $this->globalEligibilityService,
        );
    }

    public function test_returns_not_eligible_when_tables_disabled(): void
    {
        $eventId = 'event-123';
        $config = $this->createConfig(tablesEnabled: false);

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn($config);

        $result = $this->service->canCreateTableForEvent($eventId, null);

        $this->assertFalse($result->eligible);
        $this->assertSame('tables_not_enabled_for_event', $result->reason);
    }

    public function test_returns_eligible_at_for_guest_when_creation_not_yet_open(): void
    {
        $eventId = 'event-123';
        $openDate = (new DateTimeImmutable())->modify('+7 days');
        $config = $this->createConfig(
            tablesEnabled: true,
            earlyAccessEnabled: true,
            creationOpensAt: $openDate,
        );

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn($config);

        $result = $this->service->canCreateTableForEvent($eventId, null);

        $this->assertFalse($result->eligible);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->canCreateAt);
        $this->assertEquals($openDate->format('Y-m-d'), $result->canCreateAt->format('Y-m-d'));
    }

    public function test_returns_eligible_at_for_user_without_early_access_role(): void
    {
        $eventId = 'event-123';
        $user = UserModel::factory()->create();
        $generalOpenDate = (new DateTimeImmutable())->modify('+7 days');
        $earlyAccessTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['vip', 'premium'],
            daysBeforeOpening: 3,
        );

        $config = $this->createConfig(
            tablesEnabled: true,
            earlyAccessEnabled: true,
            creationOpensAt: $generalOpenDate,
            earlyAccessTier: $earlyAccessTier,
        );

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn($config);

        $this->authorizationService
            ->shouldReceive('hasAnyRole')
            ->with(Mockery::type(UserModel::class), ['vip', 'premium'])
            ->once()
            ->andReturn(false);

        $result = $this->service->canCreateTableForEvent($eventId, $user->id);

        $this->assertFalse($result->eligible);
        $this->assertNull($result->reason);
        $this->assertNotNull($result->canCreateAt);
        // User without early access gets the general open date
        $this->assertEquals($generalOpenDate->format('Y-m-d'), $result->canCreateAt->format('Y-m-d'));
    }

    public function test_allows_early_access_for_user_with_matching_role(): void
    {
        $eventId = 'event-123';
        $user = UserModel::factory()->create();
        // General open date is 3 days from now, early access (+3-3=now) is open for VIP users
        $generalOpenDate = (new DateTimeImmutable())->modify('+3 days');
        $earlyAccessTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['vip'],
            daysBeforeOpening: 3,
        );

        $config = $this->createConfig(
            tablesEnabled: true,
            earlyAccessEnabled: true,
            creationOpensAt: $generalOpenDate,
            earlyAccessTier: $earlyAccessTier,
        );

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn($config);

        $this->authorizationService
            ->shouldReceive('hasAnyRole')
            ->with(Mockery::type(UserModel::class), ['vip'])
            ->once()
            ->andReturn(true);

        // Event has tables enabled but no eligibility override - falls back to global service
        $this->globalEligibilityService
            ->shouldReceive('canCreateTable')
            ->with($user->id)
            ->once()
            ->andReturn(\Modules\GameTables\Application\DTOs\CreationEligibilityDTO::eligible());

        $result = $this->service->canCreateTableForEvent($eventId, $user->id);

        $this->assertTrue($result->eligible);
        $this->assertTrue($result->canCreateTables);
    }

    public function test_allows_early_access_for_user_with_matching_permission(): void
    {
        $eventId = 'event-123';
        $user = UserModel::factory()->create();
        // General open date is 5 days from now, early access (5-5=now) is open
        $generalOpenDate = (new DateTimeImmutable())->modify('+5 days');
        $earlyAccessTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Permission,
            requiredPermission: 'gametables:early_create',
            daysBeforeOpening: 5,
        );

        $config = $this->createConfig(
            tablesEnabled: true,
            earlyAccessEnabled: true,
            creationOpensAt: $generalOpenDate,
            earlyAccessTier: $earlyAccessTier,
        );

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn($config);

        $this->authorizationService
            ->shouldReceive('can')
            ->with(Mockery::type(UserModel::class), 'gametables:early_create')
            ->once()
            ->andReturn(true);

        // Event has tables enabled but no eligibility override - falls back to global service
        $this->globalEligibilityService
            ->shouldReceive('canCreateTable')
            ->with($user->id)
            ->once()
            ->andReturn(\Modules\GameTables\Application\DTOs\CreationEligibilityDTO::eligible());

        $result = $this->service->canCreateTableForEvent($eventId, $user->id);

        $this->assertTrue($result->eligible);
        $this->assertTrue($result->canCreateTables);
    }

    public function test_returns_eligible_when_creation_is_open(): void
    {
        $eventId = 'event-123';
        $user = UserModel::factory()->create();
        // General open date is in the past (creation is open)
        $generalOpenDate = (new DateTimeImmutable())->modify('-1 day');

        $config = $this->createConfig(
            tablesEnabled: true,
            earlyAccessEnabled: true,
            creationOpensAt: $generalOpenDate,
        );

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn($config);

        // Event has tables enabled but no eligibility override - falls back to global service
        $this->globalEligibilityService
            ->shouldReceive('canCreateTable')
            ->with($user->id)
            ->once()
            ->andReturn(\Modules\GameTables\Application\DTOs\CreationEligibilityDTO::eligible());

        $result = $this->service->canCreateTableForEvent($eventId, $user->id);

        $this->assertTrue($result->eligible);
        $this->assertTrue($result->canCreateTables);
    }

    public function test_skips_early_access_check_when_not_configured(): void
    {
        $eventId = 'event-123';
        $user = UserModel::factory()->create();

        $config = $this->createConfig(
            tablesEnabled: true,
            earlyAccessEnabled: false,
        );

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn($config);

        // Event has tables enabled but no eligibility override - falls back to global service
        $this->globalEligibilityService
            ->shouldReceive('canCreateTable')
            ->with($user->id)
            ->once()
            ->andReturn(\Modules\GameTables\Application\DTOs\CreationEligibilityDTO::eligible());

        $result = $this->service->canCreateTableForEvent($eventId, $user->id);

        $this->assertTrue($result->eligible);
        $this->assertTrue($result->canCreateTables);
    }

    public function test_uses_global_eligibility_when_event_has_no_config(): void
    {
        $eventId = 'event-123';
        $user = UserModel::factory()->create();

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn(null);

        // When no event config exists, fall back to global settings
        $this->globalEligibilityService
            ->shouldReceive('canCreateTable')
            ->with($user->id)
            ->once()
            ->andReturn(\Modules\GameTables\Application\DTOs\CreationEligibilityDTO::eligible());

        $result = $this->service->canCreateTableForEvent($eventId, $user->id);

        $this->assertTrue($result->eligible);
    }

    public function test_uses_global_eligibility_when_event_config_returns_not_eligible(): void
    {
        $eventId = 'event-123';
        $user = UserModel::factory()->create();

        $this->configRepository
            ->shouldReceive('findByEvent')
            ->with($eventId)
            ->once()
            ->andReturn(null);

        // When no event config exists, fall back to global settings (disabled)
        $this->globalEligibilityService
            ->shouldReceive('canCreateTable')
            ->with($user->id)
            ->once()
            ->andReturn(\Modules\GameTables\Application\DTOs\CreationEligibilityDTO::notEligible('frontend_creation_disabled'));

        $result = $this->service->canCreateTableForEvent($eventId, $user->id);

        $this->assertFalse($result->eligible);
        $this->assertSame('frontend_creation_disabled', $result->reason);
    }

    private function createConfig(
        string $eventId = 'event-123',
        bool $tablesEnabled = false,
        bool $earlyAccessEnabled = false,
        ?DateTimeImmutable $creationOpensAt = null,
        ?EarlyAccessTier $earlyAccessTier = null,
    ): EventGameTableConfig {
        return new EventGameTableConfig(
            eventId: $eventId,
            tablesEnabled: $tablesEnabled,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
            fixedLocation: null,
            eligibilityOverride: null,
            earlyAccessEnabled: $earlyAccessEnabled,
            creationOpensAt: $creationOpensAt,
            earlyAccessTier: $earlyAccessTier,
        );
    }
}
