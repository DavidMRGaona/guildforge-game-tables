<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\GameTables\Domain\ValueObjects\GenreKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tables and campaigns store the keys of their genres, so a key keeps the shape the
 * catalog has always written: lower case words joined by underscores.
 */
final class GenreKeyTest extends TestCase
{
    public function test_it_keeps_a_well_formed_key(): void
    {
        $this->assertSame('post_apocalyptic', (new GenreKey('post_apocalyptic'))->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedKeys(): array
    {
        return [
            'empty' => [''],
            'capitals' => ['Fantasy'],
            'hyphen' => ['sci-fi'],
            'space' => ['sci fi'],
            'leading underscore' => ['_horror'],
            'trailing underscore' => ['horror_'],
            'double underscore' => ['sci__fi'],
            'accent' => ['fantasía'],
            'only digits' => ['2'],
            'starting with a digit' => ['3d_horror'],
            'too long' => [str_repeat('a', GenreKey::MAX_LENGTH + 1)],
        ];
    }

    #[DataProvider('malformedKeys')]
    public function test_it_refuses_a_malformed_key(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GenreKey($key);
    }

    #[DataProvider('malformedKeys')]
    public function test_try_from_gives_null_for_a_malformed_key(string $key): void
    {
        $this->assertNull(GenreKey::tryFrom($key));
    }

    public function test_try_from_gives_null_for_anything_but_a_string(): void
    {
        $this->assertNull(GenreKey::tryFrom(null));
        $this->assertNull(GenreKey::tryFrom(3));
        $this->assertNull(GenreKey::tryFrom(['value' => 'horror']));
    }

    public function test_try_from_gives_the_key_for_a_well_formed_one(): void
    {
        $this->assertSame('sci_fi', GenreKey::tryFrom('sci_fi')?->value);
    }

    public function test_digits_are_accepted_after_the_first_letter(): void
    {
        $this->assertSame('d20_fantasy', (new GenreKey('d20_fantasy'))->value);
    }

    public function test_the_longest_key_is_accepted(): void
    {
        $key = str_repeat('a', GenreKey::MAX_LENGTH);

        $this->assertSame($key, (new GenreKey($key))->value);
    }

    public function test_values_in_lists_joins_the_lists_of_several_records(): void
    {
        $this->assertSame(
            ['horror', 'mystery', 'fantasy'],
            GenreKey::valuesInLists([['horror', 'mystery'], null, 'horror', ['fantasy', 'horror', 'Bad Key']]),
        );
    }

    public function test_two_keys_with_the_same_value_are_equal(): void
    {
        $this->assertTrue((new GenreKey('horror'))->equals(new GenreKey('horror')));
        $this->assertFalse((new GenreKey('horror'))->equals(new GenreKey('mystery')));
    }

    public function test_list_from_keeps_the_well_formed_keys_once_and_in_order(): void
    {
        $keys = GenreKey::listFrom(['horror', 'Fantasy', 'horror', 7, 'mystery', null]);

        $this->assertSame(['horror', 'mystery'], array_map(static fn (GenreKey $key): string => $key->value, $keys));
    }
}
