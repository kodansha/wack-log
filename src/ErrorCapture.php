<?php

namespace WackLog;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class ErrorCapture
 *
 * Routes PHP errors and WordPress debug messages to the logger instead of
 * PHP's standard error log.
 *
 * - Non-fatal PHP errors (warnings, notices, deprecations) are captured by
 *   an error handler and are not passed to PHP's standard error handler.
 * - Fatal errors (including uncaught exceptions) are logged on shutdown.
 *   They are also logged via the 'wp_php_error_message' filter, because
 *   WordPress' fatal error handler exits before later shutdown functions run.
 * - When WP_DEBUG is enabled, _doing_it_wrong() / _deprecated_*() messages
 *   are logged with structured context, and their trigger_error() calls are
 *   suppressed to avoid duplicate entries.
 *
 * Uncaught exceptions are intentionally not handled by set_exception_handler()
 * so that WordPress' fatal error handler and recovery mode keep working.
 *
 * [Usage]
 * use WackLog\ErrorCapture;
 *
 * (new ErrorCapture(logger()->getMonolog()))->register();
 */
class ErrorCapture
{
    public const FATAL_ERRORS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    public const ERROR_LEVEL_MAP = [
        E_ERROR => LogLevel::CRITICAL,
        E_WARNING => LogLevel::WARNING,
        E_PARSE => LogLevel::CRITICAL,
        E_NOTICE => LogLevel::NOTICE,
        E_CORE_ERROR => LogLevel::CRITICAL,
        E_CORE_WARNING => LogLevel::WARNING,
        E_COMPILE_ERROR => LogLevel::CRITICAL,
        E_COMPILE_WARNING => LogLevel::WARNING,
        E_USER_ERROR => LogLevel::ERROR,
        E_USER_WARNING => LogLevel::WARNING,
        E_USER_NOTICE => LogLevel::NOTICE,
        E_RECOVERABLE_ERROR => LogLevel::ERROR,
        E_DEPRECATED => LogLevel::NOTICE,
        E_USER_DEPRECATED => LogLevel::NOTICE,
    ];

    public const ERROR_NAMES = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
    ];

    /**
     * WordPress debug actions mapped to [callback method, accepted args, filter that controls trigger_error()]
     */
    public const WP_DEBUG_HOOKS = [
        'doing_it_wrong_run' => ['logDoingItWrong', 3, 'doing_it_wrong_trigger_error'],
        'deprecated_function_run' => ['logDeprecatedFunction', 3, 'deprecated_function_trigger_error'],
        'deprecated_constructor_run' => ['logDeprecatedConstructor', 3, 'deprecated_constructor_trigger_error'],
        'deprecated_class_run' => ['logDeprecatedClass', 3, 'deprecated_class_trigger_error'],
        'deprecated_file_included' => ['logDeprecatedFile', 4, 'deprecated_file_trigger_error'],
        'deprecated_argument_run' => ['logDeprecatedArgument', 3, 'deprecated_argument_trigger_error'],
        'deprecated_hook_run' => ['logDeprecatedHook', 4, 'deprecated_hook_trigger_error'],
    ];

    private bool $registered = false;

    /** @var callable|null */
    private $previous_error_handler = null;

    private bool $fatal_error_logged = false;

    /**
     * Memory reserved to be freed on shutdown, so that out-of-memory errors can still be logged
     */
    private ?string $reserved_memory = null;

    public function __construct(
        private LoggerInterface $logger,
        private bool $wp_debug = false,
    ) {}

    /**
     * Register the error handler, the shutdown function and WordPress hooks
     *
     * Calling this method more than once has no effect.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $this->previous_error_handler = set_error_handler([$this, 'handleError']);

        $this->reserved_memory = str_repeat(' ', 20 * 1024);
        register_shutdown_function([$this, 'handleShutdown']);

        add_filter('wp_php_error_message', [$this, 'filterPhpErrorMessage'], 10, 2);

        if ($this->wp_debug) {
            foreach (self::WP_DEBUG_HOOKS as $action => [$method, $accepted_args, $filter]) {
                add_action($action, [$this, $method], 10, $accepted_args);
                add_filter($filter, '__return_false');
            }
        }
    }

    /**
     * Whether register() has been called
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Error handler passed to set_error_handler()
     *
     * Returns false to let PHP's standard error handler handle the error,
     * true to stop further handling.
     *
     * The previous error handler, if any, is always called. Its return value is
     * respected only for errors that are not logged here (unreported or fatal).
     */
    public function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        // Respect error_reporting() and the @ operator
        if (!self::isReported($errno)) {
            return $this->callPreviousErrorHandler($errno, $errstr, $errfile, $errline) ?? false;
        }

        // Fatal errors (e.g. trigger_error() with E_USER_ERROR) must still terminate the script,
        // so leave them to PHP and log them on shutdown instead.
        if (in_array($errno, self::FATAL_ERRORS, true)) {
            return $this->callPreviousErrorHandler($errno, $errstr, $errfile, $errline) ?? false;
        }

        $this->logger->log(
            self::ERROR_LEVEL_MAP[$errno] ?? LogLevel::CRITICAL,
            sprintf('PHP %s: %s in %s on line %d', self::errorName($errno), $errstr, $errfile, $errline),
            [
                'type' => self::errorName($errno),
                'message' => $errstr,
                'file' => $errfile,
                'line' => $errline,
            ],
        );

        // The error has been logged, so don't pass it to PHP's standard error handler
        // even if the previous handler asks to.
        $this->callPreviousErrorHandler($errno, $errstr, $errfile, $errline);

        return true;
    }

    /**
     * Shutdown function passed to register_shutdown_function()
     */
    public function handleShutdown(): void
    {
        $this->handleFatalError(error_get_last());
    }

    /**
     * Log the given error if it is a fatal error
     *
     * @param array{type: int, message: string, file: string, line: int}|null $error The value of error_get_last()
     */
    public function handleFatalError(?array $error): void
    {
        if ($this->fatal_error_logged || !is_array($error) || !in_array($error['type'] ?? null, self::FATAL_ERRORS, true)) {
            return;
        }

        // Respect error_reporting() as PHP does
        if (!self::isReported($error['type'])) {
            return;
        }

        // Free the reserved memory here, as this is reached either from the shutdown function or
        // from the 'wp_php_error_message' filter (in which case the shutdown function never runs).
        $this->reserved_memory = null;
        $this->fatal_error_logged = true;

        $this->logger->log(
            LogLevel::CRITICAL,
            sprintf('PHP Fatal error (%s): %s in %s on line %d', self::errorName($error['type']), $error['message'], $error['file'], $error['line']),
            [
                'type' => self::errorName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ],
        );
    }

    /**
     * Callback for the 'wp_php_error_message' filter
     *
     * WordPress' fatal error handler calls wp_die() right after this filter,
     * and shutdown functions registered after it never run, so log here.
     */
    public function filterPhpErrorMessage($message, $error)
    {
        if (is_array($error)) {
            $this->handleFatalError($error);
        }

        return $message;
    }

    public function logDoingItWrong($function_name, $message, $version): void
    {
        $this->logWordPressDebugMessage(
            E_USER_NOTICE,
            sprintf('%s was called incorrectly. %s (This message was added in version %s.)', $function_name, $message, $version),
            ['type' => 'doing_it_wrong', 'function' => $function_name, 'version' => $version],
        );
    }

    public function logDeprecatedFunction($function_name, $replacement, $version): void
    {
        $this->logWordPressDebugMessage(
            E_USER_DEPRECATED,
            sprintf('Function %s is deprecated since version %s', $function_name, $version)
                . ($replacement ? sprintf('! Use %s instead.', $replacement) : ' with no alternative available.'),
            ['type' => 'deprecated_function', 'function' => $function_name, 'replacement' => $replacement, 'version' => $version],
        );
    }

    public function logDeprecatedConstructor($class_name, $version, $parent_class = ''): void
    {
        $this->logWordPressDebugMessage(
            E_USER_DEPRECATED,
            sprintf('The called constructor method for %s', $class_name)
                . ($parent_class ? sprintf(' in %s', $parent_class) : '')
                . sprintf(' is deprecated since version %s! Use __construct() instead.', $version),
            ['type' => 'deprecated_constructor', 'class' => $class_name, 'parent_class' => $parent_class, 'version' => $version],
        );
    }

    public function logDeprecatedClass($class_name, $replacement, $version): void
    {
        $this->logWordPressDebugMessage(
            E_USER_DEPRECATED,
            sprintf('Class %s is deprecated since version %s', $class_name, $version)
                . ($replacement ? sprintf('! Use %s instead.', $replacement) : ' with no alternative available.'),
            ['type' => 'deprecated_class', 'class' => $class_name, 'replacement' => $replacement, 'version' => $version],
        );
    }

    public function logDeprecatedFile($file, $replacement, $version, $message = ''): void
    {
        $this->logWordPressDebugMessage(
            E_USER_DEPRECATED,
            sprintf('File %s is deprecated since version %s', $file, $version)
                . ($replacement ? sprintf('! Use %s instead.', $replacement) : ' with no alternative available.')
                . ($message ? ' ' . $message : ''),
            ['type' => 'deprecated_file', 'file' => $file, 'replacement' => $replacement, 'version' => $version],
        );
    }

    public function logDeprecatedArgument($function_name, $message, $version): void
    {
        $this->logWordPressDebugMessage(
            E_USER_DEPRECATED,
            sprintf('Function %s was called with an argument that is deprecated since version %s', $function_name, $version)
                . ($message ? '! ' . $message : ' with no alternative available.'),
            ['type' => 'deprecated_argument', 'function' => $function_name, 'version' => $version],
        );
    }

    public function logDeprecatedHook($hook, $replacement, $version, $message = ''): void
    {
        $this->logWordPressDebugMessage(
            E_USER_DEPRECATED,
            sprintf('Hook %s is deprecated since version %s', $hook, $version)
                . ($replacement ? sprintf('! Use %s instead.', $replacement) : ' with no alternative available.')
                . ($message ? ' ' . $message : ''),
            ['type' => 'deprecated_hook', 'hook' => $hook, 'replacement' => $replacement, 'version' => $version],
        );
    }

    /**
     * Log a WordPress debug message, which would otherwise be raised by trigger_error() with $errno
     *
     * As trigger_error() is suppressed, check error_reporting() (and the @ operator) here instead.
     */
    private function logWordPressDebugMessage(int $errno, string $message, array $context): void
    {
        if (!self::isReported($errno)) {
            return;
        }

        $this->logger->notice($message, $context);
    }

    /**
     * Whether the given error type is included in error_reporting()
     */
    private static function isReported(int $errno): bool
    {
        return (error_reporting() & $errno) !== 0;
    }

    /**
     * Get the name of the given error type (e.g. E_WARNING => 'E_WARNING')
     */
    public static function errorName(int $errno): string
    {
        return self::ERROR_NAMES[$errno] ?? 'Unknown error';
    }

    /**
     * Call the error handler that was set before this one, if any
     *
     * PHP passes an error to its standard error handler only if the handler returns exactly false,
     * so any other return value (including null) is treated as true.
     *
     * @return bool|null Whether the previous handler handled the error, or null if there is none
     */
    private function callPreviousErrorHandler(int $errno, string $errstr, string $errfile, int $errline): ?bool
    {
        if (!is_callable($this->previous_error_handler)) {
            return null;
        }

        return call_user_func($this->previous_error_handler, $errno, $errstr, $errfile, $errline) !== false;
    }
}
