<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Tests\TestCase;

final class CreationAccessLevelTest extends TestCase
{
    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('registered', CreationAccessLevel::Registered->value);
        $this->assertEquals('role', CreationAccessLevel::Role->value);
        $this->assertEquals('permission', CreationAccessLevel::Permission->value);
    }

    public function test_it_returns_label(): void
    {
        foreach (CreationAccessLevel::cases() as $level) {
            $this->assertSame(__('game-tables::messages.enums.creation_access_level.'.$level->value), $level->label());
        }
    }

    public function test_it_returns_options(): void
    {
        $options = CreationAccessLevel::options();

        $this->assertCount(3, $options);
        $this->assertArrayNotHasKey('everyone', $options);
        $this->assertArrayHasKey('registered', $options);
        $this->assertArrayHasKey('role', $options);
        $this->assertArrayHasKey('permission', $options);
    }

    public function test_it_returns_values(): void
    {
        $values = CreationAccessLevel::values();

        $this->assertCount(3, $values);
        $this->assertNotContains('everyone', $values);
        $this->assertContains('registered', $values);
        $this->assertContains('role', $values);
        $this->assertContains('permission', $values);
    }

    public function test_a_stored_everyone_reads_as_registered_users(): void
    {
        // Offered until it turned out that creating always needed a session
        $this->assertSame(CreationAccessLevel::Registered, CreationAccessLevel::fromStored('everyone'));
    }

    public function test_other_stored_levels_read_as_themselves(): void
    {
        foreach (CreationAccessLevel::cases() as $level) {
            $this->assertSame($level, CreationAccessLevel::fromStored($level->value));
        }
    }
}
