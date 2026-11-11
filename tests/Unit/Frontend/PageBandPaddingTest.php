<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The title band at the top of a page has the same height as the core's and the other modules':
 * its container uses py-6. With py-8 the module's bands stood taller than every other band.
 */
final class PageBandPaddingTest extends TestCase
{
    private const string BAND = '/<div class="bg-surface shadow">\s*<div\s+class="([^"]*)"/';

    private function root(): string
    {
        return dirname(__DIR__, 3).'/resources/js/pages';
    }

    public function test_every_title_band_uses_the_core_padding(): void
    {
        $bands = 0;
        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root(), RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            if (preg_match_all(self::BAND, $source, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $classes) {
                $bands++;
                if (preg_match('/\bpy-6\b/', $classes) !== 1) {
                    $violations[] = substr($file->getPathname(), strlen($this->root()) + 1).': '.$classes;
                }
            }
        }

        $this->assertGreaterThan(0, $bands);
        $this->assertSame([], $violations);
    }
}
