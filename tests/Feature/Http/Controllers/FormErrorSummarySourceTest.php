<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources of the four forms with tabs (table
 * and campaign, create and edit) to pin the error summary. After a failed save it lists every error,
 * each one under its tab and field label (a key no field renders still shows, as «Otros datos»), and
 * a tab with errors names them in its accessible name.
 */
final class FormErrorSummarySourceTest extends TestCase
{
    private const TABLE_PAGES = ['pages/GameTables/Create.vue', 'pages/GameTables/Edit.vue'];

    private const CAMPAIGN_PAGES = ['pages/Campaigns/Create.vue', 'pages/Campaigns/Edit.vue'];

    private const COMPOSABLE = 'composables/useFormErrorSummary.ts';

    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/'.$file);
    }

    /**
     * @return array<string, string> entries of the page's `const <name>: Record<string, string> = {...}` map
     */
    private function pageMap(string $page, string $name): array
    {
        $found = preg_match('/^const '.$name.': Record<string, string> = \{\n(.*?)^\};/ms', $this->source($page), $match);
        $this->assertSame(1, $found, "{$name} not found in {$page}");

        preg_match_all("/^\s+([a-z_]+): '([A-Za-z_.]+)',$/m", $match[1], $entries, PREG_SET_ORDER);

        $map = [];
        foreach ($entries as [, $field, $value]) {
            $map[$field] = $value;
        }

        return $map;
    }

    /**
     * @return array<string, string> dotted key => message of a locale file (single-line strings only)
     */
    private function localeKeys(string $locale): array
    {
        $keys = [];
        $stack = [];

        foreach (explode("\n", $this->source("locales/{$locale}.ts")) as $line) {
            if (preg_match('/^( *)([A-Za-z0-9_]+): \{$/', $line, $m) === 1) {
                $depth = intdiv(strlen($m[1]), 4);
                $stack = array_slice($stack, 0, $depth - 1);
                $stack[] = $m[2];

                continue;
            }

            if (preg_match("/^( *)([A-Za-z0-9_]+): (['\"])(.*)\\3,$/", $line, $m) === 1) {
                $path = array_slice($stack, 0, intdiv(strlen($m[1]), 4) - 1);
                $keys[implode('.', [...$path, $m[2]])] = $m[4];
            }
        }

        return $keys;
    }

    public function test_the_copy_is_pinned_in_spanish_and_translated(): void
    {
        $es = $this->localeKeys('es');
        $en = $this->localeKeys('en');

        $this->assertSame('No se ha podido guardar', $es['gameTables.formErrors.title'] ?? null);
        $this->assertSame('Revisa {count} campo antes de guardar: | Revisa {count} campos antes de guardar:', $es['gameTables.formErrors.intro'] ?? null);
        $this->assertSame('Otros datos', $es['gameTables.formErrors.otherData'] ?? null);
        $this->assertSame('{tab}, {count} error | {tab}, {count} errores', $es['gameTables.formErrors.tabWithErrors'] ?? null);
        $this->assertSame('Consentimiento de los co-directores', $es['gameTables.create.gameMasters.gdprConsentLabel'] ?? null);

        $this->assertSame('Could not save', $en['gameTables.formErrors.title'] ?? null);
        $this->assertSame('Check {count} field before saving: | Check {count} fields before saving:', $en['gameTables.formErrors.intro'] ?? null);
        $this->assertSame('Other data', $en['gameTables.formErrors.otherData'] ?? null);
        $this->assertSame('{tab}, {count} error | {tab}, {count} errors', $en['gameTables.formErrors.tabWithErrors'] ?? null);
        $this->assertSame("Co-GMs' consent", $en['gameTables.create.gameMasters.gdprConsentLabel'] ?? null);
    }

    public function test_the_summary_lists_each_error_as_a_button_and_takes_the_focus(): void
    {
        $source = $this->source('components/FormErrorSummary.vue');

        $this->assertStringContainsString('v-if="items.length > 0"', $source);
        $this->assertStringContainsString('tabindex="-1"', $source);
        $this->assertStringContainsString('role="alert"', $source);
        $this->assertStringContainsString("t('gameTables.formErrors.title')", $source);
        $this->assertStringContainsString("t('gameTables.formErrors.intro', { count: items.length }, items.length)", $source);
        $this->assertStringContainsString('<li v-for="item in items" :key="item.key">', $source);
        $this->assertStringContainsString("@click=\"emit('select', item)\"", $source);
        $this->assertStringContainsString('{{ item.tabLabel }} › {{ item.label }}', $source);
        $this->assertStringContainsString('{{ item.message }}', $source);
        $this->assertStringContainsString('defineExpose({ focus });', $source);
    }

    public function test_the_composable_maps_every_key_by_its_first_segment_and_never_drops_one(): void
    {
        $source = $this->source(self::COMPOSABLE);

        $this->assertStringContainsString("return key.split('.')[0] ?? key;", $source);
        $this->assertStringContainsString("return options.fieldToTab[fieldOf(key)] ?? 'basic';", $source);
        // Every non-empty message becomes an item; an unknown field gets the generic label
        $this->assertStringContainsString("typeof message === 'string' && message !== ''", $source);
        $this->assertStringContainsString("return labelKey !== undefined ? t(labelKey) : t('gameTables.formErrors.otherData');", $source);
        // A director's row: «Director principal» or «Co-director #<n>» (n among the co-directors)
        $this->assertStringContainsString("t('gameTables.create.gameMasters.roleMain')", $source);
        $this->assertStringContainsString("rows.slice(0, index).filter((other) => other.role !== 'main').length + 1", $source);
        $this->assertStringContainsString("t('gameTables.create.gameMasters.cogmNumber', { n })", $source);
        // The directors' errors handed to their section (moved here from the pages)
        $this->assertStringContainsString("if (key.startsWith('game_masters.') || key === 'gm_gdpr_consent') {", $source);
        // A failed save opens the first tab with errors and focuses the summary, not the field
        $this->assertStringContainsString("const first = findFirstTabWithErrors();\n            if (first !== null) {\n                options.activeTab.value = first;", $source);
        $this->assertStringContainsString("const summaryRef = useTemplateRef<{ focus: () => void }>('errorSummary');", $source);
        $this->assertStringContainsString('summaryRef.value?.focus()', $source);
        // An item opens its tab, then focuses its field (or the tab when the field is not on the page)
        $this->assertStringContainsString('options.activeTab.value = item.tab;', $source);
        $this->assertStringContainsString('`tab-${item.tab}`', $source);
    }

    public function test_every_page_uses_the_summary_and_keeps_a_single_mapping(): void
    {
        foreach ([...self::TABLE_PAGES, ...self::CAMPAIGN_PAGES] as $page) {
            $source = $this->source($page);

            $this->assertStringContainsString("import { useFormErrorSummary } from '../../composables/useFormErrorSummary';", $source, $page);
            $this->assertStringContainsString("import FormErrorSummary from '../../components/FormErrorSummary.vue';", $source, $page);
            $this->assertStringContainsString('<FormErrorSummary ref="errorSummary" :items="errorItems" @select="focusError" />', $source, $page);
            $summary = strpos($source, '<FormErrorSummary');
            $tabs = strpos($source, '<FormTabs');
            $this->assertNotFalse($summary, $page);
            $this->assertNotFalse($tabs, $page);
            $this->assertLessThan($tabs, $summary, "{$page}: the summary goes above the tabs");
            $this->assertStringContainsString('onError: () => revealErrors(),', $source, $page);
            $this->assertStringContainsString(':errors="tabErrors"', $source, $page);
            // The mapping lives in the composable only
            $this->assertStringNotContainsString('function findFirstTabWithErrors', $source, $page);
            $this->assertStringNotContainsString('function scrollToFirstError', $source, $page);
            $this->assertStringNotContainsString("key.split('.')", $source, $page);
        }
    }

    public function test_every_tab_field_has_a_label_the_form_already_uses(): void
    {
        $es = $this->localeKeys('es');

        foreach ([...self::TABLE_PAGES, ...self::CAMPAIGN_PAGES] as $page) {
            $tabs = $this->pageMap($page, 'fieldToTab');
            $labels = $this->pageMap($page, 'fieldLabels');

            $this->assertSame(array_keys($tabs), array_keys($labels), "{$page}: one label per mapped field");
            foreach ($labels as $field => $key) {
                $this->assertArrayHasKey($key, $es, "{$page}: {$field} => {$key}");
            }
        }

        foreach (self::TABLE_PAGES as $page) {
            $tabs = $this->pageMap($page, 'fieldToTab');
            $labels = $this->pageMap($page, 'fieldLabels');

            $this->assertSame('content', $tabs['content_warnings'], $page);
            $this->assertSame('content', $tabs['content_warning_ids'], $page);
            $this->assertSame('gameTables.create.gameMasters.gdprConsentLabel', $labels['gm_gdpr_consent'], $page);
            $this->assertSame('gameTables.create.gameMasters.title', $labels['game_masters'], $page);
            $this->assertStringContainsString('gameMasters: () => form.game_masters,', $this->source($page), $page);
        }
    }

    public function test_every_field_the_campaign_request_validates_has_its_tab(): void
    {
        preg_match_all("/^            '([a-z_]+)(?:\.[a-z_*.]+)?' => \[/m", (string) file_get_contents(dirname(__DIR__, 4).'/src/Http/Requests/FrontendCreateCampaignRequest.php'), $matches);
        $fields = array_values(array_unique($matches[1]));
        $this->assertContains('genres', $fields);

        foreach (self::CAMPAIGN_PAGES as $page) {
            $map = $this->pageMap($page, 'fieldToTab');

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $map, "{$page}: {$field}");
            }
        }
    }

    public function test_list_fields_have_an_id_to_focus(): void
    {
        foreach (self::TABLE_PAGES as $page) {
            $source = $this->source($page);

            foreach (['safety_tools', 'content_warnings', 'custom_warnings'] as $id) {
                $this->assertStringContainsString("<div id=\"{$id}\">", $source, "{$page}: {$id}");
            }
            $this->assertStringContainsString('<GameMasterSection
                                    id="game_masters"', $source, $page);
        }

        foreach (self::CAMPAIGN_PAGES as $page) {
            $source = $this->source($page);

            foreach (['safety_tools', 'content_warning_ids', 'custom_warnings'] as $id) {
                $this->assertStringContainsString("<div id=\"{$id}\">", $source, "{$page}: {$id}");
            }
        }
    }

    public function test_a_tab_with_errors_shows_them_and_names_them(): void
    {
        $source = $this->source('components/FormTabs.vue');

        $this->assertStringContainsString("t('gameTables.formErrors.tabWithErrors', { tab: tab.label, count }, count)", $source);
        $this->assertStringContainsString(':aria-label="tabAccessibleName(tab)"', $source);
        $this->assertStringContainsString("hasErrors(tab.id)\n                        ? 'text-error'", $source);
        // The badge (warning icon + count) is visual only: the accessible name already carries the count
        $this->assertStringContainsString('v-if="hasErrors(tab.id)"
                    aria-hidden="true"', $source);
        $this->assertStringNotContainsString('errores`', $source);
    }

    public function test_the_directors_section_leaves_unplaced_errors_to_the_summary(): void
    {
        $source = $this->source('components/GameMasterSection.vue');

        $this->assertStringNotContainsString('otherErrors', $source);
        $this->assertStringNotContainsString('text-status-error', $source);
        $this->assertStringContainsString('<p v-if="error" class="text-sm text-error">{{ error }}</p>', $source);
    }
}
