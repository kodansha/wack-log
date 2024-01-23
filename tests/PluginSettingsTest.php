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
}
