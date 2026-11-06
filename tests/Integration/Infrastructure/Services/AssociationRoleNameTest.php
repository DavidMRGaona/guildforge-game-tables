<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Modules\GameTables\Application\Services\AssociationRoleNameInterface;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The visible name of the site's association member role, for the texts that name it (#176).
 */
final class AssociationRoleNameTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_it_is_the_display_name_of_the_configured_role(): void
    {
        RoleModel::query()->create(['name' => 'miembro', 'display_name' => 'Miembros']);
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'miembro');

        $this->assertSame('Miembros', app(AssociationRoleNameInterface::class)->displayName());
    }

    public function test_the_default_role_shows_its_display_name(): void
    {
        $this->assertSame('Socio', app(AssociationRoleNameInterface::class)->displayName());
    }

    public function test_without_a_usable_role_it_falls_back_to_a_generic_name(): void
    {
        // A base role in the setting means nobody is an association member
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'member');

        $this->assertSame('socio', app(AssociationRoleNameInterface::class)->displayName());
    }
}
