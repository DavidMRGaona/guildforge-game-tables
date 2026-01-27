<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\ContentWarning;
use Modules\GameTables\Domain\Enums\WarningSeverity;
use Modules\GameTables\Domain\ValueObjects\ContentWarningId;
use PHPUnit\Framework\TestCase;

final class ContentWarningTest extends TestCase
{
    public function test_it_creates_content_warning_with_required_data(): void
    {
        $id = ContentWarningId::generate();

        $warning = new ContentWarning(
            id: $id,
            name: 'violence',
            label: 'Violencia',
            severity: WarningSeverity::Moderate,
            isActive: true,
        );

        $this->assertInstanceOf(ContentWarning::class, $warning);
        $this->assertTrue($id->equals($warning->id));
        $this->assertEquals('violence', $warning->name);
        $this->assertEquals('Violencia', $warning->label);
        $this->assertEquals(WarningSeverity::Moderate, $warning->severity);
        $this->assertTrue($warning->isActive);
        $this->assertNull($warning->description);
        $this->assertNull($warning->icon);
        $this->assertNull($warning->createdAt);
    }

    public function test_it_creates_content_warning_with_all_data(): void
    {
        $id = ContentWarningId::generate();
        $createdAt = new DateTimeImmutable('2026-01-01 10:00:00');

        $warning = new ContentWarning(
            id: $id,
            name: 'death',
            label: 'Muerte',
            severity: WarningSeverity::Severe,
            isActive: true,
            description: 'Temas de muerte y morir',
            icon: 'heroicon-o-exclamation-triangle',
            createdAt: $createdAt,
        );

        $this->assertInstanceOf(ContentWarning::class, $warning);
        $this->assertEquals('death', $warning->name);
        $this->assertEquals('Muerte', $warning->label);
        $this->assertEquals('Temas de muerte y morir', $warning->description);
        $this->assertEquals('heroicon-o-exclamation-triangle', $warning->icon);
        $this->assertEquals($createdAt, $warning->createdAt);
    }

    public function test_it_can_update_info(): void
    {
        $warning = $this->createWarning();

        $warning->updateInfo(
            name: 'updated-violence',
            label: 'Violencia actualizada',
            description: 'Descripcion actualizada',
            severity: WarningSeverity::Severe,
            icon: 'heroicon-o-shield-exclamation',
        );

        $this->assertEquals('updated-violence', $warning->name);
        $this->assertEquals('Violencia actualizada', $warning->label);
        $this->assertEquals('Descripcion actualizada', $warning->description);
        $this->assertEquals(WarningSeverity::Severe, $warning->severity);
        $this->assertEquals('heroicon-o-shield-exclamation', $warning->icon);
    }

    public function test_it_can_activate(): void
    {
        $warning = $this->createWarning(isActive: false);

        $warning->activate();

        $this->assertTrue($warning->isActive);
    }

    public function test_it_can_deactivate(): void
    {
        $warning = $this->createWarning(isActive: true);

        $warning->deactivate();

        $this->assertFalse($warning->isActive);
    }

    public function test_it_checks_if_active(): void
    {
        $activeWarning = $this->createWarning(isActive: true);
        $inactiveWarning = $this->createWarning(isActive: false);

        $this->assertTrue($activeWarning->isActive());
        $this->assertFalse($inactiveWarning->isActive());
    }

    public function test_it_checks_if_severe(): void
    {
        $severeWarning = $this->createWarning(severity: WarningSeverity::Severe);
        $mildWarning = $this->createWarning(severity: WarningSeverity::Mild);

        $this->assertTrue($severeWarning->isSevere());
        $this->assertFalse($mildWarning->isSevere());
    }

    private function createWarning(
        bool $isActive = true,
        WarningSeverity $severity = WarningSeverity::Moderate,
    ): ContentWarning {
        return new ContentWarning(
            id: ContentWarningId::generate(),
            name: 'test-warning',
            label: 'Test Warning',
            severity: $severity,
            isActive: $isActive,
        );
    }
}
