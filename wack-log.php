<?php

/**
 * Plugin Name: WACK Log
 * Plugin URI: https://packagist.org/packages/kodansha/wack-log
 * Description: Simple logger plugin to output logs to stdout.
 * Version: 0.1.0
 * Author: KODANSHAtech LLC.
 * Author URI: https://github.com/kodansha
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Don't do anything if called directly.
if (!defined('ABSPATH') || !defined('WPINC')) {
    die();
}

// Autoloader
if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!function_exists('logger')) {
    /**
     * Logs a message to stdout or returns an instance of the logger.
     *
     * This function creates a singleton instance of the WackLog\StdoutLogger class.
     * If a message is provided, it logs the message to stdout as debug logs and returns null.
     * If no message is provided, it returns the logger instance.
     *
     * @param string|null $message The message to log. If null, the function will return the logger instance.
     * @param bool $force_re_instantiation If true, the function will re-instantiate the logger instance.
     *
     * @return WackLog\StdoutLogger|null The logger instance or null if a message was logged.
     */
    function logger(string $message = null, bool $force_re_instantiation = false): WackLog\StdoutLogger|null
    {
        static $instance = null;

        $use_json_log_format = WackLog\PluginSettings::get()->useJsonFormat();

        if (is_null($instance) || $force_re_instantiation) {
            $instance = new WackLog\StdoutLogger($use_json_log_format);
        }

        if (!is_null($message)) {
            $instance->debug($message);
            return null;
        }

        return $instance;
    }
}

/**
 * Initialize plugin
 */
function wack_log_init()
{
    (new WackLog\AdminMenu())->init();
}

add_action('plugins_loaded', 'wack_log_init', PHP_INT_MAX - 1);
