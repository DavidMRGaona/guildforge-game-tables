<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources of the web table and campaign
 * forms. The server is the only judge of a save (no browser validation hides an error on another
 * tab), invalid fields keep the error colour in both themes, the pieces of these forms use theme
 * tokens instead of light/dark pairs (module `dark:` utilities lose to plain ones), the consent
 * checkbox names its error, and a removed director row takes only its own errors away.
 */
final class TableFormsThemeSourceTest extends TestCase
{
    private const array FORM_PAGES = [
        'pages/GameTables/Create.vue',
        'pages/GameTables/Edit.vue',
        'pages/Campaigns/Create.vue',
        'pages/Campaigns/Edit.vue',
    ];

    private const array TABLE_PAGES = ['pages/GameTables/Create.vue', 'pages/GameTables/Edit.vue'];

    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/'.$file);
    }

    public function test_the_forms_leave_validation_to_the_server(): void
    {
        foreach (self::FORM_PAGES as $page) {
            $source = $this->source($page);

            $this->assertStringContainsString('<form novalidate @submit.prevent="submit">', $source, $page);
            // The inputs still say they are required
            $this->assertStringContainsString('required', $source, $page);
        }
    }

    public function test_every_invalid_field_keeps_the_error_colour(): void
    {
        $helper = $this->source('utils/fieldStateClass.ts');
        $this->assertStringContainsString("? 'border-error hover:border-error focus:border-error focus:ring-error'", $helper);
        $this->assertStringContainsString(": 'border-default hover:border-strong focus:border-primary-500 focus:ring-primary-500'", $helper);

        foreach (self::FORM_PAGES as $page) {
            $source = $this->source($page);

            preg_match_all('/:aria-invalid="!!form\.errors\.([a-z_]+)"/', $source, $invalid);
            preg_match_all('/fieldStateClass\(!!form\.errors\.([a-z_]+)\)/', $source, $styled);
            $this->assertNotEmpty($invalid[1], $page);
            $this->assertSame($invalid[1], $styled[1], $page);
            // The focus colour comes from the helper only: never next to an error
            $this->assertStringNotContainsString('focus:ring-primary-500"', $source, $page);

            // Every core select names its error, so it shows the error border
            preg_match_all('/<FormSelect\b(.*?)\/>/s', $source, $selects);
            $this->assertNotEmpty($selects[1], $page);
            foreach ($selects[1] as $select) {
                $this->assertStringContainsString(':error="', $select, "{$page}: {$select}");
            }
        }

        $section = $this->source('components/GameMasterSection.vue');
        // Prettier may break the binding after its opening quote
        $this->assertSame(7, preg_match_all('/:class="\\s*fieldStateClass\\(!!fieldError\\(/', $section), 'director inputs');
    }

    public function test_no_light_dark_pairs_in_the_pieces_of_these_forms(): void
    {
        foreach (['components/GameMasterSection.vue', 'components/FormErrorSummary.vue', 'components/FormTabs.vue'] as $file) {
            $this->assertStringNotContainsString('dark:', $this->source($file), $file);
        }

        $section = $this->source('components/GameMasterSection.vue');
        // Main director card: a translucent primary tint over the surface, theme text
        $this->assertStringContainsString('class="relative rounded-lg border border-primary-500/30 bg-primary-500/10 p-4"', $section);
        $this->assertStringContainsString('<div class="font-medium text-base-primary">', $section);
        // «Tú» and «Director principal (tú)»: the theme's link shade of primary (AA in both themes)
        $this->assertStringContainsString('rounded-full border border-primary-500/30 bg-primary-500/5 px-2 py-0.5 text-xs font-medium text-[color:var(--color-primary-link)]', $section);
        $this->assertStringContainsString('<div class="text-sm text-[color:var(--color-primary-link)]">', $section);
        $this->assertStringNotContainsString('bg-primary-50 ', $section);

        $tabs = $this->source('components/FormTabs.vue');
        $this->assertStringContainsString("? 'text-[color:var(--color-primary-link)]'", $tabs);
        $this->assertStringContainsString("'text-[color:var(--color-text-secondary)] hover:text-[color:var(--color-text-primary)]'", $tabs);
        $this->assertStringContainsString('bg-error px-1.5 py-0.5 text-xs font-semibold text-base-inverted', $tabs);

        foreach (['pages/GameTables/MyTables.vue', 'pages/Campaigns/MyCampaigns.vue'] as $page) {
            $source = $this->source($page);

            $this->assertStringContainsString('class="mb-6 rounded-lg border-l-4 border-success bg-success/10 p-4 text-sm text-base-primary"', $source, $page);
            $this->assertStringContainsString('class="mb-6 rounded-lg border-l-4 border-error bg-error/10 p-4 text-sm text-base-primary"', $source, $page);
            $this->assertStringNotContainsString('text-red-800', $source, $page);
        }
    }

    public function test_the_authors_values_say_they_apply_to_every_table(): void
    {
        $section = $this->source('components/GameMasterSection.vue');

        $this->assertStringContainsString("t('gameTables.create.gameMasters.appliesToAllTables')", $section);
        $this->assertStringContainsString("appliesToAllTables: 'Se aplica a todas las mesas que diriges.',", $this->source('locales/es.ts'));
        $this->assertStringContainsString("appliesToAllTables: 'It applies to all the tables you run.',", $this->source('locales/en.ts'));
        // The values start from the author's director record
        $this->assertStringContainsString('phone: props.currentUser.phone ?? \'\',', $section);
        $this->assertStringContainsString('custom_title: props.currentUser.custom_title ?? \'\',', $section);
        $this->assertStringContainsString('is_name_public: props.currentUser.is_name_public ?? true,', $section);
    }

    public function test_the_consent_checkbox_names_its_error(): void
    {
        $section = $this->source('components/GameMasterSection.vue');

        $this->assertStringContainsString("querySelector<HTMLInputElement>('#gm_gdpr_consent')", $section);
        $this->assertStringContainsString("input.setAttribute('aria-invalid', 'true');", $section);
        $this->assertStringContainsString("input.setAttribute('aria-describedby', 'gm_gdpr_consent-error');", $section);
        $this->assertStringContainsString("input.removeAttribute('aria-describedby');", $section);
    }

    public function test_the_english_custom_warnings_label_exists(): void
    {
        $this->assertStringContainsString("                customWarnings: 'Custom warnings',\n", $this->source('locales/en.ts'));
    }

    public function test_removing_a_director_row_takes_only_its_own_errors(): void
    {
        $composable = $this->source('composables/useFormErrorSummary.ts');
        $this->assertStringContainsString('export function shiftGameMasterErrors(', $composable);
        $this->assertStringContainsString('removeGameMasterRowErrors', $composable);

        $section = $this->source('components/GameMasterSection.vue');
        $this->assertStringContainsString("(e: 'remove', index: number): void;", $section);
        $this->assertStringContainsString("emit('remove', actualIndex);", $section);

        foreach (self::TABLE_PAGES as $page) {
            $source = $this->source($page);

            // Adding a row keeps every error; removing one renumbers the rows after it
            $this->assertStringNotContainsString('watch(() => form.game_masters.length', $source, $page);
            $this->assertStringContainsString('@remove="removeGameMasterRowErrors"', $source, $page);
        }
    }
}
