<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source guards, not behaviour tests: the module has no Vue test runner. A group of checkboxes is
 * named by the legend of its fieldset; a bare <label> names no field, so screen readers skip the
 * title and the browser reports it.
 */
final class CheckboxGroupLegendTest extends TestCase
{
    private const string PAGES = __DIR__.'/../../../resources/js/pages/';

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function forms(): array
    {
        return [
            'table creation' => ['GameTables/Create.vue', ['genres', 'safety_tools', 'content_warning_ids']],
            'table edition' => ['GameTables/Edit.vue', ['genres', 'safety_tools', 'content_warning_ids']],
            'campaign creation' => ['Campaigns/Create.vue', ['safety_tools', 'content_warning_ids']],
            'campaign edition' => ['Campaigns/Edit.vue', ['safety_tools', 'content_warning_ids']],
        ];
    }

    /**
     * @param  list<string>  $groups
     */
    #[DataProvider('forms')]
    public function test_each_checkbox_group_is_a_fieldset_named_by_its_legend(string $page, array $groups): void
    {
        $source = file_get_contents(self::PAGES.$page);
        $this->assertNotFalse($source, "{$page} is readable");

        foreach ($groups as $group) {
            $this->assertMatchesRegularExpression(
                '/<fieldset id="'.$group.'"[^>]*>\s*<legend\b/',
                $source,
                "{$page}: {$group} is not a fieldset with a legend",
            );
        }

        // The old group titles: labels with no field to point to
        $this->assertStringNotContainsString('<label class="block text-sm font-medium text-base-secondary mb-3">', $source);
    }
}
