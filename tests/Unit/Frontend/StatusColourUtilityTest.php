<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A status colour utility (bg-, text-, border-… with danger, error, success, warning or info) that
 * the theme does not define renders with no colour at all, silently. Each one must be either a
 * colour token (`--color-<name>` in the core's @theme or the module stylesheet, so Tailwind
 * generates it with any variant) or one of the core's own utility classes (`.bg-error-light`),
 * which exist only as written: without variants.
 */
final class StatusColourUtilityTest extends TestCase
{
    private const UTILITY = '/(?<![\w-])((?:[\w-]+:)*)((?:bg|text|border(?:-[lrtbxy])?|ring|ring-offset|divide|outline|fill|stroke|from|to|via|decoration|shadow)-((?:danger|error|success|warning|info)(?:-[a-z0-9]+)*))(?:\/\d+)?(?![\w-])/';

    private function moduleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * The core stylesheet: the module lives in `modules/<name>` of the host app.
     */
    private function hostStylesheet(): ?string
    {
        $path = dirname($this->moduleRoot(), 2).'/resources/css/app.css';

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function test_the_theme_has_no_danger_colour(): void
    {
        $violations = [];
        foreach ($this->utilities() as [$file, $class, , $name]) {
            if (str_starts_with($name, 'danger')) {
                $violations[] = "{$file}: {$class}";
            }
        }

        $this->assertSame([], $violations, "There is no `danger` colour: use the `error` tokens (bg-error-light, text-error, border-error):\n".implode("\n", $violations));
    }

    public function test_every_status_colour_utility_exists_in_the_theme(): void
    {
        $host = $this->hostStylesheet();
        if ($host === null) {
            $this->markTestSkipped('The host app stylesheet is not available outside the host app.');
        }

        $module = (string) file_get_contents($this->moduleRoot().'/resources/css/module.css');
        preg_match_all('/--color-([a-z0-9-]+)\s*:/', $host.$module, $tokens);
        $tokens = array_flip($tokens[1]);
        preg_match_all('/^\s*\.([a-z0-9-]+)\s*\{/m', $host, $classes);
        $classes = array_flip($classes[1]);

        $violations = [];
        foreach ($this->utilities() as [$file, $class, $variants, $name, $utility]) {
            if (isset($tokens[$name])) {
                continue;
            }
            if (isset($classes[$utility]) && $variants === '') {
                continue;
            }

            $violations[] = isset($classes[$utility])
                ? "{$file}: {$class} (the core's .{$utility} takes no variants; use a token such as {$this->tokenHint($name)})"
                : "{$file}: {$class} (no such colour)";
        }

        $this->assertSame([], array_values(array_unique($violations)), implode("\n", $violations));
    }

    /**
     * @return list<array{string, string, string, string, string}> file, class, variants, colour name, utility without variants
     */
    private function utilities(): array
    {
        $root = $this->moduleRoot().'/resources/js';
        $found = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! in_array($file->getExtension(), ['vue', 'ts'], true)) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);
            // Locale files hold text, not classes
            if (str_starts_with($relative, 'locales/')) {
                continue;
            }

            preg_match_all(self::UTILITY, (string) file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $found[] = [$relative, $match[0], $match[1], $match[3], $match[2]];
            }
        }

        return $found;
    }

    private function tokenHint(string $name): string
    {
        return str_ends_with($name, '-light') ? substr($name, 0, -strlen('-light')).'-bg' : $name;
    }
}
