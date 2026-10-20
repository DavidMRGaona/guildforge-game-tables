<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Lang;

use Tests\Support\Modules\ModuleTestCase;

/**
 * Every message key the module's web controllers write out (flashes, page titles, refusals)
 * resolves in Spanish and English, so nobody ever reads a raw key. Keys built at run time
 * (`'…errors.'.$reason`) are not written out and are not checked here.
 */
final class ControllerMessageKeysTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_every_message_key_the_controllers_use_exists_in_spanish_and_english(): void
    {
        $keys = $this->controllerKeys();
        $this->assertContains('game-tables::messages.frontend.table_created', $keys);
        $this->assertContains('game-tables::messages.frontend.campaign_created', $keys);

        $translator = app('translator');

        foreach (['es', 'en'] as $locale) {
            foreach ($keys as $key) {
                // No fallback: an English key missing would otherwise read the Spanish copy
                $this->assertNotSame($key, $translator->get($key, [], $locale, false), "{$locale}: {$key}");
            }
        }
    }

    /**
     * @return list<string> the complete `game-tables::messages.*` keys written in the controllers
     */
    private function controllerKeys(): array
    {
        $keys = [];

        foreach (glob(dirname(__DIR__, 3).'/src/Http/Controllers/*.php') ?: [] as $file) {
            preg_match_all("/['\"](game-tables::messages\.[a-z0-9_.]*[a-z0-9_])['\"]/", (string) file_get_contents($file), $matches);
            array_push($keys, ...$matches[1]);
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }
}
