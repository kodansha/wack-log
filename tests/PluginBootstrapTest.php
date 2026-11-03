<?php

namespace WackLogTest;

use PHPUnit\Framework\TestCase;
use WackLog\ErrorCapture;
use WP_Mock;
use WP_Mock\Tools\Constraints\ExpectationsMet;

/**
 * Tests for wack-log.php
 *
 * Each test runs in a separate process because loading the plugin file defines
 * global functions and constants, and registers an error handler.
 *
 * This extends PHPUnit's TestCase instead of WP_Mock\Tools\TestCase, because the
 * latter emits a deprecation on PHP 8.4+ when loaded, which is reported as an error
 * in a separate process.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class PluginBootstrapTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function assertConditionsMet(): void
    {
        $this->assertThat(null, new ExpectationsMet());
    }

    private static function currentErrorHandler(): ?callable
    {
        $current = set_error_handler(fn() => false);
        restore_error_handler();

        return $current;
    }

    private function loadPlugin(array $settings_constant = [], $settings_option = false): void
    {
        // ABSPATH and WPINC may already be defined by WP_Mock
        defined('ABSPATH') || define('ABSPATH', '/tmp/wordpress/');
        defined('WPINC') || define('WPINC', 'wp-includes');
        define('WACK_LOG_SETTINGS', $settings_constant);

        WP_Mock::userFunction('get_option')
            ->with('wack_log_settings')
            ->andReturn($settings_option);

        require dirname(__DIR__) . '/wack-log.php';
    }

    // phpcs:ignore
    public function test_error_capture_is_not_registered_by_default(): void
    {
        $previous = self::currentErrorHandler();

        $this->loadPlugin();

        $this->assertNull(wack_log_register_error_capture());
        $this->assertSame($previous, self::currentErrorHandler());
    }

    // phpcs:ignore
    public function test_error_capture_is_registered_when_enabled_in_constant(): void
    {
        $this->loadPlugin(['capture_php_errors' => true]);

        $capture = wack_log_register_error_capture();
        $this->assertInstanceOf(ErrorCapture::class, $capture);
        $this->assertTrue($capture->isRegistered());
        $this->assertSame([$capture, 'handleError'], self::currentErrorHandler());

        restore_error_handler();
    }

    // phpcs:ignore
    public function test_error_capture_is_registered_when_enabled_in_database(): void
    {
        $this->loadPlugin([], ['capture_php_errors' => 'on']);

        $this->assertInstanceOf(ErrorCapture::class, wack_log_register_error_capture());

        restore_error_handler();
    }

    // phpcs:ignore
    public function test_error_capture_is_not_registered_when_disabled_in_constant(): void
    {
        $this->loadPlugin(['capture_php_errors' => false], ['capture_php_errors' => 'on']);

        $this->assertNull(wack_log_register_error_capture());
    }

    // phpcs:ignore
    public function test_error_capture_is_registered_only_once(): void
    {
        $this->loadPlugin(['capture_php_errors' => true]);

        $first = wack_log_register_error_capture();
        $second = wack_log_register_error_capture();
        $this->assertSame($first, $second);

        // Only one handler has been pushed
        restore_error_handler();
        $this->assertNotSame([$first, 'handleError'], self::currentErrorHandler());
    }

    // phpcs:ignore
    public function test_error_capture_logs_to_plugin_logger(): void
    {
        // Remove PHPUnit's error handler so that it is not chained
        set_error_handler(null);
        $this->loadPlugin(['capture_php_errors' => true]);

        $handler = new \Monolog\Handler\TestHandler();
        logger()->getMonolog()->setHandlers([$handler]);

        wack_log_register_error_capture()->handleError(E_WARNING, 'Captured', '/path/to/file.php', 1);

        $this->assertTrue($handler->hasWarningThatContains('Captured'));

        restore_error_handler();
        restore_error_handler();
    }

    // phpcs:ignore
    public function test_wp_debug_hooks_are_added_when_wp_debug_is_enabled(): void
    {
        define('WP_DEBUG', true);
        WP_Mock::expectActionAdded('doing_it_wrong_run', [\WP_Mock\Functions::type(ErrorCapture::class), 'logDoingItWrong'], 10, 3);
        WP_Mock::expectFilterAdded('doing_it_wrong_trigger_error', '__return_false');

        $this->loadPlugin(['capture_php_errors' => true]);

        $this->assertConditionsMet();

        restore_error_handler();
    }

    // phpcs:ignore
    public function test_wp_debug_hooks_are_not_added_when_wp_debug_is_disabled(): void
    {
        define('WP_DEBUG', false);
        WP_Mock::expectHookNotAdded('filters', 'doing_it_wrong_trigger_error', '__return_false');

        $this->loadPlugin(['capture_php_errors' => true]);

        $this->assertConditionsMet();

        restore_error_handler();
    }

    // phpcs:ignore
    public function test_plugins_loaded_action_is_added(): void
    {
        WP_Mock::expectActionAdded('plugins_loaded', 'wack_log_init', PHP_INT_MAX - 1);

        $this->loadPlugin();

        $this->assertConditionsMet();
    }
}
