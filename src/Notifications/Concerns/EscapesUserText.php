<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications\Concerns;

use Illuminate\Support\HtmlString;

trait EscapesUserText
{
    /**
     * A line that contains text typed by a user, as a one-line HTML block: escaped and without
     * line breaks, so the Markdown mail template never turns it into links or formatting.
     */
    protected function plainLine(string $line): HtmlString
    {
        return new HtmlString('<p>'.str_replace(["\r\n", "\r", "\n"], ' ', e($line)).'</p>');
    }
}
