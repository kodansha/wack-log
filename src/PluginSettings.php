<?php

namespace WackLog;

/**
 * Class PluginSettings
 * @package WackLog
 */
final class PluginSettings
{
    private bool $use_json_format;

    final private function __construct()
    {
        // If the value exists in the constant, always use that value
        $use_json_format = self::getUseJsonFormatOptionFromConstant();
        if ($use_json_format === true) {
            $this->use_json_format = true;
        } else if ($use_json_format === false) {
            $this->use_json_format = false;
        } else {
            $use_json_format = self::getUseJsonFormatOptionFromDatabase();
            if ($use_json_format === true) {
                $this->use_json_format = true;
            } else {
                $this->use_json_format = false;
            }
        }
    }

    /**
     * Get the flag to use JSON format
     * @return string The flag to use JSON format
     */
    public function useJsonFormat(): bool
    {
        return $this->use_json_format;
    }

    /**
     * Get instance
     * @return PluginSettings
     */
    public static function get(): PluginSettings
    {
        return new self();
    }

    /**
     * Get the flag to use JSON format from the 'WACK_LOG_SETTINGS' constant.
     */
    public static function getUseJsonFormatOptionFromConstant(): bool | null
    {
        if (!isset(Constants::settingsConstant()['use_json_format'])) {
            return null;
        }

        if (Constants::settingsConstant()['use_json_format'] === true) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the flag to use JSON format from the database.
     */
    public static function getUseJsonFormatOptionFromDatabase(): bool
    {
        $settings_option = get_option('wack_log_settings');

        if ($settings_option && isset($settings_option['use_json_format'])) {
            return $settings_option['use_json_format'];
        }

        return false;
    }
}
