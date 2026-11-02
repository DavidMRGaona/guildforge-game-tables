<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Modules\GameTables\Domain\Enums\TableFormat;

/**
 * Where a table or campaign from the web is played: the location in person, the link online and at
 * least one of the two when hybrid, as TableFormat decides and the form marks.
 */
trait ValidatesPlace
{
    /**
     * The location is required in person, and when hybrid while there is no link.
     */
    protected function locationRequired(): RequiredIf
    {
        return Rule::requiredIf(fn (): bool => TableFormat::needsLocation($this->placeFormat(), $this->placeOnlineUrl()));
    }

    protected function onlineUrlRequired(): RequiredIf
    {
        return Rule::requiredIf(fn (): bool => TableFormat::needsOnlineUrl($this->placeFormat()));
    }

    /**
     * Either field will do when hybrid: the message says so instead of asking for the location only.
     *
     * @return array<string, string>
     */
    protected function placeMessages(): array
    {
        return $this->placeFormat() === TableFormat::InPerson
            ? []
            : ['location.required' => __('game-tables::messages.validation.location_or_online_url_required')];
    }

    private function placeFormat(): ?TableFormat
    {
        $format = $this->input('table_format');

        return is_string($format) ? TableFormat::tryFrom($format) : null;
    }

    private function placeOnlineUrl(): ?string
    {
        $url = $this->input('online_url');

        return is_string($url) ? $url : null;
    }
}
