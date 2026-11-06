<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Domain;

use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Solo socios» names the site's association member role, whatever it is called (#176).
 */
final class MembersOnlyLabelsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_the_members_only_labels_name_the_given_role(): void
    {
        $this->assertSame('Solo el rol Miembros', RegistrationType::MembersOnly->label('Miembros'));
        $this->assertSame('Solo el rol Miembros', RosterAccess::MembersOnly->label('Miembros'));
        $this->assertSame('Solo el rol Miembros', RegistrationType::options('Miembros')['members_only']);
        $this->assertSame('Solo el rol Miembros', RosterAccess::options('Miembros')['members_only']);
    }

    public function test_without_a_role_they_use_a_generic_name(): void
    {
        $this->assertSame('Solo el rol socio', RegistrationType::MembersOnly->label());
        $this->assertSame('Abierto a todos', RegistrationType::Everyone->label('Miembros'));
    }
}
