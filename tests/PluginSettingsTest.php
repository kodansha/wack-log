<?php

namespace WackLogTest;

use WP_Mock;
use Mockery;
use WackLog\PluginSettings;
use WackLog\Constants;

final class PluginSettingsTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // getUseJsonFormatOptionFromConstant
    //==========================================================================
    // phpcs:ignore
    public function test_getUseJsonFormatOptionFromConstant_settings_found(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'use_json_format' => true,
            ]);
        $result = PluginSettings::getUseJsonFormatOptionFromConstant();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getUseJsonFormatOptionFromConstant_settings_found_but_not_true(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'use_json_format' => 'DUMMY',
            ]);
        $result = PluginSettings::getUseJsonFormatOptionFromConstant();
        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_getUseJsonFormatOptionFromConstant_settings_not_found(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([]);
        $result = PluginSettings::getUseJsonFormatOptionFromConstant();
        $this->assertNull($result);
    }

    //==========================================================================
    // getUseJsonFormatOptionFromDatabase
    //==========================================================================
    // phpcs:ignore
    public function test_getUseJsonFormatOptionFromDatabase_settings_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_log_settings')
            ->andReturn([
                'use_json_format' => true,
            ]);
        $result = PluginSettings::getUseJsonFormatOptionFromDatabase();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getUseJsonFormatOptionFromDatabase_settings_not_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_log_settings')
            ->andReturn(false);
        $result = PluginSettings::getUseJsonFormatOptionFromDatabase();
        $this->assertFalse($result);
    }

    //==========================================================================
    // useJsonFormat
    //==========================================================================
    // phpcs:ignore
    public function test_useJsonFormat_found_in_constant(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'use_json_format' => true,
            ]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([]);

        $instance = PluginSettings::get();

        $this->assertTrue($instance->useJsonFormat());
    }

    // phpcs:ignore
    public function test_useJsonFormat_constant_value_overwrite_database_value(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'use_json_format' => false,
            ]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([
                'use_json_format' => true,
            ]);

        $instance = PluginSettings::get();

        $this->assertFalse($instance->useJsonFormat());
    }

    // phpcs:ignore
    public function test_useJsonFormat_found_only_in_database(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([
                'use_json_format' => true,
            ]);

        $instance = PluginSettings::get();

        $this->assertTrue($instance->useJsonFormat());
    }

    // phpcs:ignore
    public function test_useJsonFormat_found_only_in_database_and_false(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([
                'use_json_format' => false,
            ]);

        $instance = PluginSettings::get();

        $this->assertFalse($instance->useJsonFormat());
    }

    // phpcs:ignore
    public function test_useJsonFormat_checkbox_value_in_database(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([
                'use_json_format' => 'on',
            ]);

        $instance = PluginSettings::get();

        $this->assertTrue($instance->useJsonFormat());
    }

    //==========================================================================
    // getCapturePhpErrorsOptionFromConstant
    //==========================================================================
    // phpcs:ignore
    public function test_getCapturePhpErrorsOptionFromConstant_settings_found(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'capture_php_errors' => true,
            ]);
        $result = PluginSettings::getCapturePhpErrorsOptionFromConstant();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getCapturePhpErrorsOptionFromConstant_settings_found_and_false(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'capture_php_errors' => false,
            ]);
        $result = PluginSettings::getCapturePhpErrorsOptionFromConstant();
        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_getCapturePhpErrorsOptionFromConstant_settings_found_but_not_true(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'capture_php_errors' => 'DUMMY',
            ]);
        $result = PluginSettings::getCapturePhpErrorsOptionFromConstant();
        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_getCapturePhpErrorsOptionFromConstant_settings_not_found(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'use_json_format' => true,
            ]);
        $result = PluginSettings::getCapturePhpErrorsOptionFromConstant();
        $this->assertNull($result);
    }

    //==========================================================================
    // getCapturePhpErrorsOptionFromDatabase
    //==========================================================================
    // phpcs:ignore
    public function test_getCapturePhpErrorsOptionFromDatabase_settings_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_log_settings')
            ->andReturn([
                'capture_php_errors' => 'on',
            ]);
        $result = PluginSettings::getCapturePhpErrorsOptionFromDatabase();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getCapturePhpErrorsOptionFromDatabase_other_settings_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_log_settings')
            ->andReturn([
                'use_json_format' => 'on',
            ]);
        $result = PluginSettings::getCapturePhpErrorsOptionFromDatabase();
        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_getCapturePhpErrorsOptionFromDatabase_settings_not_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_log_settings')
            ->andReturn(false);
        $result = PluginSettings::getCapturePhpErrorsOptionFromDatabase();
        $this->assertFalse($result);
    }

    //==========================================================================
    // capturePhpErrors
    //==========================================================================
    // phpcs:ignore
    public function test_capturePhpErrors_defaults_to_false(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn(false);

        $instance = PluginSettings::get();

        $this->assertFalse($instance->capturePhpErrors());
    }

    // phpcs:ignore
    public function test_capturePhpErrors_found_in_constant(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'capture_php_errors' => true,
            ]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([]);

        $instance = PluginSettings::get();

        $this->assertTrue($instance->capturePhpErrors());
    }

    // phpcs:ignore
    public function test_capturePhpErrors_constant_value_overwrite_database_value(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'capture_php_errors' => false,
            ]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([
                'capture_php_errors' => 'on',
            ]);

        $instance = PluginSettings::get();

        $this->assertFalse($instance->capturePhpErrors());
    }

    // phpcs:ignore
    public function test_capturePhpErrors_found_only_in_database(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([
                'capture_php_errors' => 'on',
            ]);

        $instance = PluginSettings::get();

        $this->assertTrue($instance->capturePhpErrors());
    }

    // phpcs:ignore
    public function test_settings_are_resolved_independently(): void
    {
        $mock = Mockery::mock('overload:' . Constants::class)->makePartial();
        $mock->shouldReceive('settingsConstant')
            ->andReturn([
                'use_json_format' => true,
            ]);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn([
                'use_json_format' => false,
                'capture_php_errors' => 'on',
            ]);

        $instance = PluginSettings::get();

        $this->assertTrue($instance->useJsonFormat());
        $this->assertTrue($instance->capturePhpErrors());
    }
}
