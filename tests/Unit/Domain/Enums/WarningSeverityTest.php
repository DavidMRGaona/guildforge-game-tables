<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\WarningSeverity;
use Tests\TestCase;

final class WarningSeverityTest extends TestCase
{
    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('mild', WarningSeverity::Mild->value);
        $this->assertEquals('moderate', WarningSeverity::Moderate->value);
        $this->assertEquals('severe', WarningSeverity::Severe->value);
    }

    public function test_it_returns_label(): void
    {
        foreach (WarningSeverity::cases() as $severity) {
            $this->assertSame(__('game-tables::messages.enums.warning_severity.'.$severity->value), $severity->label());
        }
    }

    public function test_it_returns_color(): void
    {
        $this->assertSame('info', WarningSeverity::Mild->color());
        $this->assertSame('warning', WarningSeverity::Moderate->color());
        $this->assertSame('danger', WarningSeverity::Severe->color());
    }

    public function test_it_returns_options(): void
    {
        $options = WarningSeverity::options();

        $this->assertCount(3, $options);
        $this->assertArrayHasKey('mild', $options);
        $this->assertArrayHasKey('moderate', $options);
        $this->assertArrayHasKey('severe', $options);
    }
}
