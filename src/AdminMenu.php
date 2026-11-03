<?php

namespace WackLog;

/**
 * Class AdminMenu
 *
 * @package WackLog
 */
class AdminMenu
{
    /**
     * Initialize the settings page
     */
    public function init()
    {
        add_action('admin_menu', [$this, 'addAdminMenuPage']);
        add_action('admin_menu', [$this, 'addAdminSubMenuPage']);
    }

    /**
     * Add the WACK Stack settings root page to the WordPress admin menu
     */
    public function addAdminMenuPage(): void
    {
        global $menu;

        // Check if the menu already exists
        $menu_slug = 'wack-stack-settings';
        $menu_exists = false;

        foreach ($menu as $item) {
            if ($item[2] == $menu_slug) {
                $menu_exists = true;
                break;
            }
        }

        // Add the menu if it doesn't exist
        if (!$menu_exists) {
            add_menu_page(
                'WACK Stack Settings',
                'WACK Stack',
                'manage_options',
                'wack-stack-settings',
                function () {
                    ?>
                    <div class="wrap">
                        <h1>WACK Stack Settings</h1>
                        <p>The settings pages for plugins belonging to the WACK Stack ecosystem.</p>
                    </div>
                    <?php
                },
                'dashicons-superhero-alt',
            );
        }
    }

    public function addAdminSubMenuPage(): void
    {
        add_submenu_page(
            'wack-stack-settings',
            'WACK Log Settings',
            'WACK Log',
            'manage_options',
            'wack-log-settings',
            function () {
                ?>
                <div class="wrap">
                    <form action='options.php' method='post'>
                        <h1>WACK Log Settings</h1>
                        <?php
                        settings_fields('wack-log-settings');
                do_settings_sections('wack-log-settings-page');
                submit_button();
                ?>
                    </form>
                </div>
                <?php
            },
        );

        register_setting(
            'wack-log-settings',
            'wack_log_settings',
            ['sanitize_callback' => [$this, 'optionsSanitizeCallback']],
        );

        //----------------------------------------------------------------------
        // Log Format
        //----------------------------------------------------------------------
        add_settings_section(
            'wack-log-settings-log-format-section',
            'Log Format',
            '__return_null',
            'wack-log-settings-page',
        );

        add_settings_field(
            'use_json_format',
            'Use JSON as log format',
            function () {
                $settings_option = get_option('wack_log_settings');
                $use_json_format = $settings_option['use_json_format'] ?? false;
                ?>
                <input type="checkbox" id="wack_log_settings_use_json_format" name="wack_log_settings[use_json_format]" <?php echo $use_json_format ? 'checked' : ''; ?>>
                <p>Check if you want to use JSON as log output format.</p>
                <?php
            },
            'wack-log-settings-page',
            'wack-log-settings-log-format-section',
            ['label_for' => 'wack_log_settings_use_json_format'],
        );

        //----------------------------------------------------------------------
        // Error Capture
        //----------------------------------------------------------------------
        add_settings_section(
            'wack-log-settings-error-capture-section',
            'Error Capture',
            '__return_null',
            'wack-log-settings-page',
        );

        add_settings_field(
            'capture_php_errors',
            'Capture PHP errors',
            function () {
                $settings_option = get_option('wack_log_settings');
                $capture_php_errors = $settings_option['capture_php_errors'] ?? false;
                ?>
                <input type="checkbox" id="wack_log_settings_capture_php_errors" name="wack_log_settings[capture_php_errors]" <?php echo $capture_php_errors ? 'checked' : ''; ?>>
                <p>Check if you want to output PHP errors (warnings, notices, deprecations and fatal errors) and WordPress debug messages through WACK Log instead of PHP's standard error log.</p>
                <p>The change takes effect from the next request.</p>
                <?php
            },
            'wack-log-settings-page',
            'wack-log-settings-error-capture-section',
            ['label_for' => 'wack_log_settings_capture_php_errors'],
        );

        // Remove the default WACK Stack settings page
        remove_submenu_page('wack-stack-settings', 'wack-stack-settings');
    }

    /**
     * Sanitize the options passed in
     *
     * - Re-instantiate the logger instance
     */
    public function optionsSanitizeCallback($options): ?array
    {
        logger('Re-instantiate logger instance as settings have been saved.', true);
        return $options;
    }
}
