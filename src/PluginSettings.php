<?php

namespace WackLog;

/**
 * Class PluginSettings
 *
 * @package WackLog
 */
final class PluginSettings
{
    private bool $use_json_format;

    private bool $capture_php_errors;

    final private function __construct()
    {
        // If the value exists in the constant, always use that value
        $this->use_json_format = self::getUseJsonFormatOptionFromConstant()
            ?? self::getUseJsonFormatOptionFromDatabase();

        $this->capture_php_errors = self::getCapturePhpErrorsOptionFromConstant()
            ?? self::getCapturePhpErrorsOptionFromDatabase();
    }

    /**
     * Get the flag to use JSON format
     *
     * @return string The flag to use JSON format
     */
    public function useJsonFormat(): bool
    {
        return $this->use_json_format;
    }

    /**
     * Get the flag to capture PHP errors and WordPress debug messages
     */
    public function capturePhpErrors(): bool
    {
        return $this->capture_php_errors;
    }

    /**
     * Get instance
     *
     * @return PluginSettings
     */
    public static function get(): PluginSettings
    {
        return new self();
    }

    /**
     * Get the flag to use JSON format from the 'WACK_LOG_SETTINGS' constant.
     */
    public static function getUseJsonFormatOptionFromConstant(): ?bool
    {
        return self::getBooleanOptionFromConstant('use_json_format');
    }

    /**
     * Get the flag to use JSON format from the database.
     */
    public static function getUseJsonFormatOptionFromDatabase(): bool
    {
        return self::getBooleanOptionFromDatabase('use_json_format');
    }

    /**
     * Get the flag to capture PHP errors from the 'WACK_LOG_SETTINGS' constant.
     */
    public static function getCapturePhpErrorsOptionFromConstant(): ?bool
    {
        return self::getBooleanOptionFromConstant('capture_php_errors');
    }

    /**
     * Get the flag to capture PHP errors from the database.
     */
    public static function getCapturePhpErrorsOptionFromDatabase(): bool
    {
        return self::getBooleanOptionFromDatabase('capture_php_errors');
    }

    /**
     * Get a boolean option from the 'WACK_LOG_SETTINGS' constant.
     *
     * @return bool|null true only if the value is exactly true, null if the key is not set
     */
    private static function getBooleanOptionFromConstant(string $key): ?bool
    {
        if (!isset(Constants::settingsConstant()[$key])) {
            return null;
        }

        return Constants::settingsConstant()[$key] === true;
    }

    /**
     * Get a boolean option from the database.
     *
     * Checkbox values are saved as 'on', so any truthy value is treated as true.
     */
    private static function getBooleanOptionFromDatabase(string $key): bool
    {
        $settings_option = get_option('wack_log_settings');

        if ($settings_option && isset($settings_option[$key])) {
            return (bool) $settings_option[$key];
        }

        return false;
    }
}
