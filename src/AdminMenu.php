<?php

namespace WackLog;

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
                'dashicons-superhero-alt'
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
            }
        );

        register_setting(
            'wack-log-settings',
            'wack_log_settings',
            ['sanitize_callback' => [$this, 'optionsSanitizeCallback']]
        );

        //----------------------------------------------------------------------
        // Log Format
        //----------------------------------------------------------------------
        add_settings_section(
            'wack-log-settings-log-format-section',
            'Log Format',
            '__return_null',
            'wack-log-settings-page'
        );

        add_settings_field(
            'use_json_format',
            'Use JSON as log format',
            function () {
                $settings_option = get_option("wack_log_settings");
                $use_json_format = $settings_option['use_json_format'] ?? false;
                ?>
                <input type="checkbox" name="wack_log_settings[use_json_format]" <?php echo $use_json_format ? 'checked' : ''; ?>>
                <p>Check if you want to use JSON as log output format.</p>
                <?php
            },
            'wack-log-settings-page',
            'wack-log-settings-log-format-section'
        );

        // Remove the default WACK Stack settings page
        remove_submenu_page('wack-stack-settings', 'wack-stack-settings');
    }

    /**
     * Sanitize the options passed in
     *
     * - Reinstantiate the logger instance
     */
    public function optionsSanitizeCallback($options): array | null
    {
        wack_log('Reinstantiate logger instance as settings have been saved.', true);
        return $options;
    }
}
