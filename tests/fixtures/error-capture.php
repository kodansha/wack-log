<?php

/**
 * Fixture script for ErrorCaptureProcessTest.
 *
 * Registers ErrorCapture with a JSON StdoutLogger and then runs the scenario
 * given as the first argument, so that behavior which ends the process
 * (fatal errors, uncaught exceptions, shutdown functions) can be tested.
 *
 * Usage: php error-capture.php <scenario> [--wp-fatal-handler]
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Minimal WordPress hook API
$GLOBALS['wp_filters'] = [];

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['wp_filters'][$hook][] = [$callback, $accepted_args];
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    add_filter($hook, $callback, $priority, $accepted_args);
}

function apply_filters($hook, $value, ...$args)
{
    foreach ($GLOBALS['wp_filters'][$hook] ?? [] as [$callback, $accepted_args]) {
        $value = call_user_func_array($callback, array_slice([$value, ...$args], 0, $accepted_args));
    }

    return $value;
}

function do_action($hook, ...$args)
{
    foreach ($GLOBALS['wp_filters'][$hook] ?? [] as [$callback, $accepted_args]) {
        call_user_func_array($callback, array_slice($args, 0, $accepted_args));
    }
}

function __return_false()
{
    return false;
}

// Mimic _doing_it_wrong() in WordPress core
function _doing_it_wrong($function_name, $message, $version)
{
    do_action('doing_it_wrong_run', $function_name, $message, $version);

    if (apply_filters('doing_it_wrong_trigger_error', true, $function_name, $message, $version)) {
        trigger_error("{$function_name} was called incorrectly. {$message}", E_USER_NOTICE);
    }
}

$scenario = $argv[1] ?? '';

// Mimic WP_Fatal_Error_Handler, which is registered before plugins are loaded
// and exits (via wp_die()) before later shutdown functions run.
if (in_array('--wp-fatal-handler', $argv, true)) {
    register_shutdown_function(function () {
        $error = error_get_last();
        if (is_array($error) && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            apply_filters('wp_php_error_message', 'There has been a critical error on this website.', $error);
            fwrite(STDERR, "WP_DIE\n");
            exit(1);
        }
    });
}

// Mimic an error handler registered before WACK Log that asks for PHP's standard error handler
if (in_array('--previous-handler', $argv, true)) {
    set_error_handler(function () {
        echo "PREVIOUS_HANDLER_CALLED\n";
        return false;
    });
}

$logger = new WackLog\StdoutLogger(true);
(new WackLog\ErrorCapture($logger->getMonolog(), true))->register();

switch ($scenario) {
    case 'warning':
        $array = [];
        $array['undefined_key'];
        echo "CONTINUED\n";
        break;

    case 'deprecated':
        trigger_error('Deprecated thing', E_USER_DEPRECATED);
        echo "CONTINUED\n";
        break;

    case 'suppressed':
        @trigger_error('Suppressed thing', E_USER_WARNING);
        echo "CONTINUED\n";
        break;

    case 'doing_it_wrong':
        _doing_it_wrong('some_function', 'Do it right.', '6.0.0');
        echo "CONTINUED\n";
        break;

    case 'suppressed_doing_it_wrong':
        @_doing_it_wrong('some_function', 'Do it right.', '6.0.0');
        echo "CONTINUED\n";
        break;

    case 'unreported_fatal':
        error_reporting(0);
        undefined_function();
        echo "CONTINUED\n";
        break;

    case 'fatal':
        undefined_function();
        echo "CONTINUED\n";
        break;

    case 'exception':
        throw new RuntimeException('Uncaught!');

    case 'user_error':
        trigger_error('User fatal', E_USER_ERROR);
        echo "CONTINUED\n";
        break;

    case 'out_of_memory':
        ini_set('memory_limit', '32M');
        $data = [];
        while (true) {
            $data[] = str_repeat('x', 1024 * 1024);
        }

        // no break
    default:
        fwrite(STDERR, "Unknown scenario: {$scenario}\n");
        exit(2);
}
