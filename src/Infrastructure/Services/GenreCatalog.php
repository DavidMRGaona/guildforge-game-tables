<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\DTOs\GenreDTO;
use Modules\GameTables\Application\Services\GenreCatalogInterface;
use Modules\GameTables\Domain\Repositories\GenreRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GenreKey;

/**
 * Bound scoped: the genres are read once per request or queued job, on first use.
 */
final class GenreCatalog implements GenreCatalogInterface
{
    /** @var array<string, GenreDTO>|null key => genre, in the catalog's order */
    private ?array $genres = null;

    public function __construct(private readonly GenreRepositoryInterface $repository) {}

    public function active(): array
    {
        return array_values(array_filter($this->genres(), static fn (GenreDTO $genre): bool => $genre->isActive));
    }

    public function resolve(array $keys): array
    {
        $wanted = $this->keySet($keys);

        return array_values(array_filter(
            $this->genres(),
            static fn (GenreDTO $genre): bool => isset($wanted[$genre->key]),
        ));
    }

    public function options(array $keptKeys = []): array
    {
        $options = [];

        foreach ($this->offered($keptKeys) as $genre) {
            $options[$genre->key] = $genre->name;
        }

        return $options;
    }

    public function acceptedKeys(array $keptKeys = []): array
    {
        return array_map(static fn (GenreDTO $genre): string => $genre->key, $this->offered($keptKeys));
    }

    /**
     * @param  array<mixed>  $keptKeys
     * @return list<GenreDTO>
     */
    private function offered(array $keptKeys): array
    {
        $kept = $this->keySet($keptKeys);

        return array_values(array_filter(
            $this->genres(),
            static fn (GenreDTO $genre): bool => $genre->isActive || isset($kept[$genre->key]),
        ));
    }

    /**
     * @param  array<mixed>  $keys
     * @return array<string, true>
     */
    private function keySet(array $keys): array
    {
        $set = [];

        foreach (GenreKey::listFrom($keys) as $key) {
            $set[$key->value] = true;
        }

        return $set;
    }

    /**
     * @return array<string, GenreDTO>
     */
    private function genres(): array
    {
        if ($this->genres === null) {
            $genres = [];

            foreach ($this->repository->all() as $genre) {
                $genres[$genre->key->value] = GenreDTO::fromEntity($genre);
            }

            // Kept only once read: a read that failed is tried again, never remembered as no genres
            $this->genres = $genres;
        }

        return $this->genres;
    }
}
