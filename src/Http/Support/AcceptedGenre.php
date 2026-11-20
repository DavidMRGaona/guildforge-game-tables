<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\GameTables\Application\Services\GenreCatalogInterface;

/**
 * A genre a form may save: an active one, or one the table or campaign being edited already has
 * (deactivated since or not). The catalog is read only when a genre is sent, so building the
 * rules of a form never touches the database.
 */
final class AcceptedGenre implements ValidationRule
{
    /** @var array<string, true>|null */
    private ?array $accepted = null;

    /**
     * @param  Closure(): array<mixed>  $keptKeys  the keys stored on the record being edited, none on create
     */
    public function __construct(private readonly Closure $keptKeys) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! isset($this->accepted()[$value])) {
            $fail('validation.in')->translate();
        }
    }

    /**
     * @return array<string, true>
     */
    private function accepted(): array
    {
        if ($this->accepted === null) {
            $keys = app(GenreCatalogInterface::class)->acceptedKeys(($this->keptKeys)());
            $this->accepted = array_fill_keys($keys, true);
        }

        return $this->accepted;
    }
}
