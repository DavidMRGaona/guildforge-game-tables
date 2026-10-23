<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Enums;

use Modules\GameTables\Domain\Enums\Language;
use Tests\TestCase;

/**
 * Public pages name each language in its own tongue, whatever the site locale.
 */
final class LanguageTest extends TestCase
{
    public function test_each_language_has_its_native_name(): void
    {
        $this->assertSame('Español', Language::Spanish->nativeName());
        $this->assertSame('English', Language::English->nativeName());
        $this->assertSame('Català', Language::Catalan->nativeName());
        $this->assertSame('Euskara', Language::Basque->nativeName());
        $this->assertSame('Galego', Language::Galician->nativeName());
    }

    public function test_native_options_list_the_selectable_languages(): void
    {
        $this->assertSame([
            'es' => 'Español',
            'en' => 'English',
            'ca' => 'Català',
            'eu' => 'Euskara',
            'gl' => 'Galego',
        ], Language::nativeOptions());
    }

    public function test_native_name_of_a_stored_code(): void
    {
        $this->assertNull(Language::nativeNameOf(null));
        $this->assertSame('Español', Language::nativeNameOf('es'));
        // Codes outside the selectable list are still named, or shown as they are
        $this->assertSame('Français', Language::nativeNameOf('fr'));
        $this->assertSame('xx', Language::nativeNameOf('xx'));
    }

    public function test_native_names_do_not_follow_the_locale(): void
    {
        app()->setLocale('en');

        $this->assertSame('Español', Language::nativeNameOf('es'));
    }
}
