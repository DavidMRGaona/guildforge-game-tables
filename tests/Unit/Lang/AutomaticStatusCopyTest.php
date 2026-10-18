<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Lang;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * Copy of the automatic status update. Literal texts, so a missing or misplaced key fails.
 */
final class AutomaticStatusCopyTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function lang(string $locale): array
    {
        return Arr::dot(require dirname(__DIR__, 3)."/lang/{$locale}/messages.php");
    }

    public function test_the_spanish_settings_copy(): void
    {
        $messages = $this->lang('es');

        $this->assertSame('Automatización', $messages['settings.sections.automation']);
        $this->assertSame('Actualizar el estado de las mesas automáticamente', $messages['settings.fields.auto_status_enabled']);
        $this->assertSame(
            'Cada cinco minutos, las mesas programadas o completas pasan a «En progreso» a su hora de inicio y las mesas en progreso pasan a «Completada» al terminar (inicio más duración). No se envían correos. Los borradores y las mesas canceladas no cambian. Desactivado, el estado solo cambia con las acciones Iniciar y Completar del panel',
            $messages['settings.fields.auto_status_enabled_help']
        );
    }

    public function test_the_english_settings_copy(): void
    {
        $messages = $this->lang('en');

        $this->assertSame('Automation', $messages['settings.sections.automation']);
        $this->assertSame('Update the status of tables automatically', $messages['settings.fields.auto_status_enabled']);
        $this->assertSame(
            'Every five minutes, scheduled or full tables move to «In progress» at their start time and tables in progress move to «Completed» when they end (start plus duration). No emails are sent. Drafts and cancelled tables never change. When off, the status only changes with the Start and Complete actions of the panel',
            $messages['settings.fields.auto_status_enabled_help']
        );
    }
}
