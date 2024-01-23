<?php

namespace WackLog;

/**
 * Class Constant
 *
 * @package WackLog
 */
final class Constants
{
    /**
     * Get the settings from the 'WACK_LOG_SETTINGS' constant.
     *
     * This method checks if the 'WACK_LOG_SETTINGS' constant is defined.
     * If it is, it returns the value of the constant. If it's not, it returns an empty array.
     *
     * @return array The settings from the 'WACK_LOG_SETTINGS' constant, or an empty array if the constant is not defined.
     */
    public static function settingsConstant(): array
    {
        if (defined('WACK_LOG_SETTINGS')) {
            return constant('WACK_LOG_SETTINGS');
        } else {
            return [];
        }
    }
}
