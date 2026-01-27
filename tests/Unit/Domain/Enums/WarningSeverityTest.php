<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\WarningSeverity;
use Tests\TestCase;

final class WarningSeverityTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = WarningSeverity::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(WarningSeverity::Mild, $cases);
        $this->assertContains(WarningSeverity::Moderate, $cases);
        $this->assertContains(WarningSeverity::Severe, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('mild', WarningSeverity::Mild->value);
        $this->assertEquals('moderate', WarningSeverity::Moderate->value);
        $this->assertEquals('severe', WarningSeverity::Severe->value);
    }

    public function test_it_returns_label(): void
    {
        $this->assertIsString(WarningSeverity::Mild->label());
        $this->assertIsString(WarningSeverity::Moderate->label());
        $this->assertIsString(WarningSeverity::Severe->label());
    }

    public function test_it_returns_color(): void
    {
        $this->assertIsString(WarningSeverity::Mild->color());
        $this->assertIsString(WarningSeverity::Moderate->color());
        $this->assertIsString(WarningSeverity::Severe->color());
    }

    public function test_it_returns_options(): void
    {
        $options = WarningSeverity::options();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);
        $this->assertArrayHasKey('mild', $options);
        $this->assertArrayHasKey('moderate', $options);
        $this->assertArrayHasKey('severe', $options);
    }
}
