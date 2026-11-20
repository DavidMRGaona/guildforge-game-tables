<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The key a table or campaign stores for each of its genres: lower case letters and digits in
 * words joined by underscores, starting with a letter. It never changes once the genre exists:
 * renaming a genre changes its name, never its key.
 */
final readonly class GenreKey
{
    // Starting with a letter: a key made of digits would turn the options' keys into numbers
    public const string PATTERN = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';

    public const int MAX_LENGTH = 50;

    public function __construct(public string $value)
    {
        if (! self::isWellFormed($value)) {
            throw new InvalidArgumentException('A genre key is lower case words joined by underscores, starting with a letter, at most '.self::MAX_LENGTH.' characters.');
        }
    }

    /**
     * The key for a stored value, or null when it is not a well-formed key
     */
    public static function tryFrom(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_string($value) && self::isWellFormed($value) ? new self($value) : null;
    }

    /**
     * The well-formed keys of a stored list, each once and in order; anything else is left out
     *
     * @param  array<mixed>  $values
     * @return list<self>
     */
    public static function listFrom(array $values): array
    {
        $keys = [];

        foreach ($values as $value) {
            $key = self::tryFrom($value);

            if ($key instanceof self && ! array_key_exists($key->value, $keys)) {
                $keys[$key->value] = $key;
            }
        }

        return array_values($keys);
    }

    /**
     * The values of the well-formed keys of a list, as listFrom() keeps them
     *
     * @param  array<mixed>  $values
     * @return list<string>
     */
    public static function valuesOf(array $values): array
    {
        return array_map(static fn (self $key): string => $key->value, self::listFrom($values));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function isWellFormed(string $value): bool
    {
        return strlen($value) <= self::MAX_LENGTH && preg_match(self::PATTERN, $value) === 1;
    }
}
