<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every progress bar goes through CapacityBar. Hand-rolled bars drifted apart (green/red on campaigns,
 * green/amber/red on tables) and their `bg-muted` track vanished inside `bg-muted` boxes.
 */
final class CapacityBarTest extends TestCase
{
    private const COMPONENT = 'components/CapacityBar.vue';

    private const TRACK = '/class="[^"]*\boverflow-hidden\b[^"]*\brounded-full\b[^"]*"/';

    private function root(): string
    {
        return dirname(__DIR__, 3).'/resources/js';
    }

    public function test_no_component_draws_its_own_progress_bar(): void
    {
        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root(), RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($this->root()) + 1);
            if ($relative === self::COMPONENT) {
                continue;
            }

            if (preg_match(self::TRACK, (string) file_get_contents($file->getPathname())) === 1) {
                $violations[] = $relative;
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Use CapacityBar instead:\n".implode("\n", $violations));
    }

    public function test_occupancy_fades_from_the_warning_token_to_the_error_token(): void
    {
        $path = $this->root().'/'.self::COMPONENT;
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('color-mix(in oklch, var(--color-warning), var(--color-error)', $contents);
    }

    public function test_the_track_is_not_the_muted_background(): void
    {
        $contents = (string) file_get_contents($this->root().'/'.self::COMPONENT);

        $this->assertMatchesRegularExpression(self::TRACK, $contents);
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bbg-muted\b[^"]*"/', $contents);
    }
}
