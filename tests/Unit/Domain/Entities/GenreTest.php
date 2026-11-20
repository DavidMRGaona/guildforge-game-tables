<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use Modules\GameTables\Domain\Entities\Genre;
use Modules\GameTables\Domain\Enums\GenreColor;
use Modules\GameTables\Domain\ValueObjects\GenreId;
use Modules\GameTables\Domain\ValueObjects\GenreKey;
use PHPUnit\Framework\TestCase;

final class GenreTest extends TestCase
{
    public function test_renaming_and_recolouring_keep_the_key(): void
    {
        $genre = $this->genre();

        $genre->rename('Horror cósmico');
        $genre->recolour(GenreColor::Info);

        $this->assertSame('horror', $genre->key->value);
        $this->assertSame('Horror cósmico', $genre->name);
        $this->assertSame(GenreColor::Info, $genre->color);
    }

    public function test_it_can_be_deactivated_and_activated_again(): void
    {
        $genre = $this->genre();

        $genre->deactivate();
        $this->assertFalse($genre->isActive);

        $genre->activate();
        $this->assertTrue($genre->isActive);
    }

    public function test_it_moves_to_another_position(): void
    {
        $genre = $this->genre();

        $genre->moveTo(5);

        $this->assertSame(5, $genre->sortOrder);
    }

    public function test_gray_is_the_default_colour(): void
    {
        $this->assertSame(GenreColor::Gray, GenreColor::default());
    }

    public function test_the_colours_are_the_states_of_the_theme(): void
    {
        $this->assertSame(['primary', 'info', 'success', 'warning', 'danger', 'gray'], GenreColor::values());
    }

    private function genre(): Genre
    {
        return new Genre(
            id: GenreId::generate(),
            key: new GenreKey('horror'),
            name: 'Terror',
            color: GenreColor::Danger,
            sortOrder: 2,
            isActive: true,
        );
    }
}
