<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * The module has no Vue test harness: these tests read the sources of the table form (create and
 * edit) to pin where its validation errors show. Each error is counted on the tab of its field and
 * the errors of the directors' fields show under their own inputs. «Mis mesas», where a refused
 * change lands, shows why.
 */
final class TableFormErrorsSourceTest extends TestCase
{
    private const PAGES = ['pages/GameTables/Create.vue', 'pages/GameTables/Edit.vue'];

    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$file);
    }

    /**
     * @return array<string, string> field => tab, as the page's `fieldToTab` maps them
     */
    private function fieldToTab(string $page): array
    {
        $found = preg_match('/^const fieldToTab: Record<string, string> = \{\n(.*?)^\};/ms', $this->source('resources/js/'.$page), $match);
        $this->assertSame(1, $found, "fieldToTab not found in {$page}");

        preg_match_all("/^\s+([a-z_]+): '([a-z]+)',$/m", $match[1], $entries, PREG_SET_ORDER);

        $map = [];
        foreach ($entries as [, $field, $tab]) {
            $map[$field] = $tab;
        }

        return $map;
    }

    public function test_a_tab_counts_an_error_by_the_first_segment_of_its_key(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source('resources/js/'.$page);

            // The shared summary composable maps each key by its first segment (FormErrorSummarySourceTest)
            $this->assertStringContainsString("fieldToTab,\n    fieldLabels,", $source, $page);
            $this->assertStringNotContainsString('fieldToTab[key]', $source, $page);
            // A failed save opens the first tab with errors (Participantes when only a director failed)
            $this->assertStringContainsString('onError: () => revealErrors(),', $source, $page);
        }
    }

    public function test_every_field_the_request_validates_has_its_tab(): void
    {
        preg_match_all("/^            '([a-z_]+)(?:\.[a-z_*.]+)?' => \[/m", $this->source('src/Http/Requests/FrontendCreateGameTableRequest.php'), $matches);
        $fields = array_values(array_unique($matches[1]));
        $this->assertContains('game_masters', $fields);
        $this->assertContains('genres', $fields);

        foreach (self::PAGES as $page) {
            $map = $this->fieldToTab($page);

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $map, "{$page}: {$field}");
            }

            $this->assertSame('participants', $map['game_masters'], $page);
            $this->assertSame('participants', $map['gm_gdpr_consent'] ?? null, $page);
            foreach (['genres', 'safety_tools', 'content_warning_ids', 'custom_warnings'] as $field) {
                $this->assertSame('content', $map[$field], "{$page}: {$field}");
            }
            // The form sends the warnings as `content_warning_ids`, the key the request validates
            $this->assertArrayNotHasKey('content_warnings', $map, $page);
        }
    }

    public function test_the_pages_hand_the_directors_field_errors_to_their_section(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source('resources/js/'.$page);

            $this->assertStringContainsString('const { tabErrors, gameMasterErrors, errorItems, revealErrors, focusError, removeGameMasterRowErrors } = useFormErrorSummary({', $source, $page);
            $this->assertStringContainsString(":error=\"form.errors.game_masters\"\n                                    :errors=\"gameMasterErrors\"", $source, $page);
        }
    }

    public function test_each_director_field_shows_its_error_under_its_input(): void
    {
        $source = $this->source('resources/js/components/GameMasterSection.vue');

        // Errors are keyed by the position in the full list, the main director included
        $this->assertStringContainsString('return `game_masters.${fullIndex}.${field}`;', $source);
        $this->assertStringContainsString("props.modelValue.flatMap((gm, i) => (gm.role !== 'main' ? [i] : []))", $source);

        foreach (['first_name', 'last_name', 'email', 'phone', 'custom_title'] as $field) {
            $this->assertStringContainsString(":aria-invalid=\"!!fieldError(fullIndexOf(index), '{$field}')\"\n                            :aria-describedby=\"errorId(fullIndexOf(index), '{$field}')\"", $source, $field);
            $this->assertStringContainsString("<p\n                            v-if=\"fieldError(fullIndexOf(index), '{$field}')\"\n                            :id=\"errorId(fullIndexOf(index), '{$field}')\"\n                            class=\"mt-1 text-sm text-error\"", $source, $field);
        }

        foreach (['custom_title', 'phone'] as $field) {
            $this->assertStringContainsString(":aria-invalid=\"!!fieldError(mainGmIndex, '{$field}')\"\n                            :aria-describedby=\"errorId(mainGmIndex, '{$field}')\"", $source, $field);
        }

        $this->assertStringContainsString('<p v-if="gdprConsentError" id="gm_gdpr_consent-error" class="mt-1 text-sm text-error">', $source);
        // An error with no input of its own is listed by the form's error summary (FormErrorSummarySourceTest)
        $this->assertStringNotContainsString('otherErrors', $source);
    }

    public function test_my_tables_and_my_campaigns_show_the_outcome_of_an_action(): void
    {
        foreach (['pages/GameTables/MyTables.vue', 'pages/Campaigns/MyCampaigns.vue'] as $page) {
            $source = $this->source('resources/js/'.$page);

            $this->assertStringContainsString('const { success: flashSuccess, error: flashError } = useFlashMessages();', $source, $page);
            // What was done (created, saved, sent, deleted) and why an action was refused
            $this->assertStringContainsString("<div\n                v-if=\"flashSuccess\"\n                role=\"status\"", $source, $page);
            $this->assertStringContainsString('{{ flashSuccess }}', $source, $page);
            $this->assertStringContainsString("<div\n                v-if=\"flashError\"\n                role=\"alert\"", $source, $page);
            $this->assertStringContainsString('{{ flashError }}', $source, $page);
        }
    }

    public function test_a_removed_director_row_takes_only_its_own_errors(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source('resources/js/'.$page);

            // Errors are keyed by row position: removing a row moves the later rows' errors up
            // (TableFormsThemeSourceTest); adding one, at the end, keeps them all
            $this->assertStringContainsString('replaceErrors: (errors) => form.clearErrors().setError(errors as Parameters<typeof form.setError>[0]),', $source, $page);
            $this->assertStringContainsString('@remove="removeGameMasterRowErrors"', $source, $page);
        }
    }
}
