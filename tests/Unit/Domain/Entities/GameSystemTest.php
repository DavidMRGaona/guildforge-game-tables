<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use PHPUnit\Framework\TestCase;

final class GameSystemTest extends TestCase
{
    public function test_it_creates_game_system_with_required_data(): void
    {
        $id = GameSystemId::generate();

        $gameSystem = new GameSystem(
            id: $id,
            name: 'Dungeons & Dragons',
            slug: 'dungeons-and-dragons',
            isActive: true,
        );

        $this->assertInstanceOf(GameSystem::class, $gameSystem);
        $this->assertTrue($id->equals($gameSystem->id));
        $this->assertEquals('Dungeons & Dragons', $gameSystem->name);
        $this->assertEquals('dungeons-and-dragons', $gameSystem->slug);
        $this->assertTrue($gameSystem->isActive);
        $this->assertNull($gameSystem->description);
        $this->assertNull($gameSystem->publisher);
        $this->assertNull($gameSystem->edition);
        $this->assertNull($gameSystem->year);
        $this->assertNull($gameSystem->logoUrl);
        $this->assertNull($gameSystem->websiteUrl);
        $this->assertNull($gameSystem->createdAt);
    }

    public function test_it_creates_game_system_with_all_data(): void
    {
        $id = GameSystemId::generate();
        $createdAt = new DateTimeImmutable('2026-01-01 10:00:00');

        $gameSystem = new GameSystem(
            id: $id,
            name: 'Call of Cthulhu',
            slug: 'call-of-cthulhu',
            isActive: true,
            description: 'A horror roleplaying game based on the writings of H.P. Lovecraft.',
            publisher: 'Chaosium',
            edition: '7th Edition',
            year: 2014,
            logoUrl: 'https://example.com/coc-logo.png',
            websiteUrl: 'https://www.chaosium.com/call-of-cthulhu-rpg/',
            createdAt: $createdAt,
        );

        $this->assertInstanceOf(GameSystem::class, $gameSystem);
        $this->assertEquals('Call of Cthulhu', $gameSystem->name);
        $this->assertEquals('A horror roleplaying game based on the writings of H.P. Lovecraft.', $gameSystem->description);
        $this->assertEquals('Chaosium', $gameSystem->publisher);
        $this->assertEquals('7th Edition', $gameSystem->edition);
        $this->assertEquals(2014, $gameSystem->year);
        $this->assertEquals('https://example.com/coc-logo.png', $gameSystem->logoUrl);
        $this->assertEquals('https://www.chaosium.com/call-of-cthulhu-rpg/', $gameSystem->websiteUrl);
        $this->assertEquals($createdAt, $gameSystem->createdAt);
    }

    public function test_it_can_update_info(): void
    {
        $gameSystem = $this->createGameSystem();

        $gameSystem->updateInfo(
            name: 'D&D 5th Edition',
            slug: 'dnd-5e',
            description: 'The fifth edition of the world\'s greatest roleplaying game.',
            publisher: 'Wizards of the Coast',
            edition: '5th Edition',
            year: 2014,
        );

        $this->assertEquals('D&D 5th Edition', $gameSystem->name);
        $this->assertEquals('dnd-5e', $gameSystem->slug);
        $this->assertEquals('The fifth edition of the world\'s greatest roleplaying game.', $gameSystem->description);
        $this->assertEquals('Wizards of the Coast', $gameSystem->publisher);
        $this->assertEquals('5th Edition', $gameSystem->edition);
        $this->assertEquals(2014, $gameSystem->year);
    }

    public function test_it_can_update_media(): void
    {
        $gameSystem = $this->createGameSystem();

        $gameSystem->updateMedia(
            logoUrl: 'https://example.com/new-logo.png',
            websiteUrl: 'https://dnd.wizards.com/',
        );

        $this->assertEquals('https://example.com/new-logo.png', $gameSystem->logoUrl);
        $this->assertEquals('https://dnd.wizards.com/', $gameSystem->websiteUrl);
    }

    public function test_it_can_activate(): void
    {
        $gameSystem = $this->createGameSystem(isActive: false);

        $gameSystem->activate();

        $this->assertTrue($gameSystem->isActive);
    }

    public function test_it_can_deactivate(): void
    {
        $gameSystem = $this->createGameSystem(isActive: true);

        $gameSystem->deactivate();

        $this->assertFalse($gameSystem->isActive);
    }

    public function test_it_checks_if_active(): void
    {
        $activeSystem = $this->createGameSystem(isActive: true);
        $inactiveSystem = $this->createGameSystem(isActive: false);

        $this->assertTrue($activeSystem->isActive());
        $this->assertFalse($inactiveSystem->isActive());
    }

    private function createGameSystem(bool $isActive = true): GameSystem
    {
        return new GameSystem(
            id: GameSystemId::generate(),
            name: 'Test System',
            slug: 'test-system',
            isActive: $isActive,
        );
    }
}
