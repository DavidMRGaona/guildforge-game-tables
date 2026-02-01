<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Tests\TestCase;

final class CreationAccessLevelTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = CreationAccessLevel::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(CreationAccessLevel::Everyone, $cases);
        $this->assertContains(CreationAccessLevel::Registered, $cases);
        $this->assertContains(CreationAccessLevel::Role, $cases);
        $this->assertContains(CreationAccessLevel::Permission, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('everyone', CreationAccessLevel::Everyone->value);
        $this->assertEquals('registered', CreationAccessLevel::Registered->value);
        $this->assertEquals('role', CreationAccessLevel::Role->value);
        $this->assertEquals('permission', CreationAccessLevel::Permission->value);
    }

    public function test_it_returns_label(): void
    {
        $this->assertIsString(CreationAccessLevel::Everyone->label());
        $this->assertIsString(CreationAccessLevel::Registered->label());
        $this->assertIsString(CreationAccessLevel::Role->label());
        $this->assertIsString(CreationAccessLevel::Permission->label());
    }

    public function test_it_returns_options(): void
    {
        $options = CreationAccessLevel::options();

        $this->assertIsArray($options);
        $this->assertCount(4, $options);
        $this->assertArrayHasKey('everyone', $options);
        $this->assertArrayHasKey('registered', $options);
        $this->assertArrayHasKey('role', $options);
        $this->assertArrayHasKey('permission', $options);
    }

    public function test_it_returns_values(): void
    {
        $values = CreationAccessLevel::values();

        $this->assertIsArray($values);
        $this->assertCount(4, $values);
        $this->assertContains('everyone', $values);
        $this->assertContains('registered', $values);
        $this->assertContains('role', $values);
        $this->assertContains('permission', $values);
    }

    public function test_it_requires_authentication_returns_false_for_everyone(): void
    {
        $this->assertFalse(CreationAccessLevel::Everyone->requiresAuthentication());
    }

    public function test_it_requires_authentication_returns_true_for_registered(): void
    {
        $this->assertTrue(CreationAccessLevel::Registered->requiresAuthentication());
    }

    public function test_it_requires_authentication_returns_true_for_role(): void
    {
        $this->assertTrue(CreationAccessLevel::Role->requiresAuthentication());
    }

    public function test_it_requires_authentication_returns_true_for_permission(): void
    {
        $this->assertTrue(CreationAccessLevel::Permission->requiresAuthentication());
    }
}
