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
        return new HtmlString('<p>'.$this->escapedOneLine($line).'</p>');
    }

    /**
     * «Label: value» with the label in bold, where the value is typed by a user: escaped the same
     * way as plainLine().
     */
    protected function plainLabelledLine(string $label, string $value): HtmlString
    {
        return new HtmlString('<p><strong>'.$this->escapedOneLine($label).':</strong> '.$this->escapedOneLine($value).'</p>');
    }

    private function escapedOneLine(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], ' ', e($text));
    }
}
