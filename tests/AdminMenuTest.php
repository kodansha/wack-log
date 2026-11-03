<?php

namespace WackLogTest;

use WackLog\AdminMenu;
use WP_Mock;

final class AdminMenuTest extends WP_Mock\Tools\TestCase
{
    /**
     * Call addAdminSubMenuPage() and return the arguments passed to add_settings_field(), keyed by field ID
     */
    private function settingsFields(): array
    {
        $fields = [];

        WP_Mock::userFunction('add_submenu_page');
        WP_Mock::userFunction('register_setting');
        WP_Mock::userFunction('add_settings_section');
        WP_Mock::userFunction('remove_submenu_page');
        WP_Mock::userFunction('add_settings_field')
            ->andReturnUsing(function (...$args) use (&$fields) {
                $fields[$args[0]] = $args;
            });

        (new AdminMenu())->addAdminSubMenuPage();

        return $fields;
    }

    private function renderField(array $field, $settings_option): string
    {
        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn($settings_option);

        ob_start();
        $field[2]();
        return ob_get_clean();
    }

    public static function checkboxProvider(): array
    {
        return [
            'use_json_format' => ['use_json_format', 'wack-log-settings-log-format-section'],
            'capture_php_errors' => ['capture_php_errors', 'wack-log-settings-error-capture-section'],
        ];
    }

    /**
     * @dataProvider checkboxProvider
     */
    // phpcs:ignore
    public function test_checkbox_field_is_added_to_section(string $key, string $section): void
    {
        $field = $this->settingsFields()[$key];

        $this->assertSame('wack-log-settings-page', $field[3]);
        $this->assertSame($section, $field[4]);
    }

    /**
     * @dataProvider checkboxProvider
     */
    // phpcs:ignore
    public function test_checkbox_is_associated_with_label(string $key): void
    {
        $field = $this->settingsFields()[$key];
        $html = $this->renderField($field, false);

        $this->assertSame(['label_for' => "wack_log_settings_{$key}"], $field[5]);
        $this->assertStringContainsString("id=\"wack_log_settings_{$key}\"", $html);
        $this->assertStringContainsString("name=\"wack_log_settings[{$key}]\"", $html);
    }

    /**
     * @dataProvider checkboxProvider
     */
    // phpcs:ignore
    public function test_checkbox_is_checked_when_option_is_on(string $key): void
    {
        $html = $this->renderField($this->settingsFields()[$key], [$key => 'on']);

        $this->assertStringContainsString('checked', $html);
    }

    /**
     * @dataProvider checkboxProvider
     */
    // phpcs:ignore
    public function test_checkbox_is_not_checked_when_option_is_not_set(string $key): void
    {
        $html = $this->renderField($this->settingsFields()[$key], false);

        $this->assertStringNotContainsString('checked', $html);
    }

    // phpcs:ignore
    public function test_error_capture_section_is_added(): void
    {
        $sections = [];
        WP_Mock::userFunction('add_submenu_page');
        WP_Mock::userFunction('register_setting');
        WP_Mock::userFunction('add_settings_field');
        WP_Mock::userFunction('remove_submenu_page');
        WP_Mock::userFunction('add_settings_section')
            ->andReturnUsing(function (...$args) use (&$sections) {
                $sections[] = $args[0];
            });

        (new AdminMenu())->addAdminSubMenuPage();

        $this->assertSame(
            ['wack-log-settings-log-format-section', 'wack-log-settings-error-capture-section'],
            $sections,
        );
    }
}
