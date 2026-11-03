<?php

namespace WackLogTest;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use WackLog\ErrorCapture;
use WP_Mock;

final class ErrorCaptureTest extends WP_Mock\Tools\TestCase
{
    private TestHandler $handler;

    private Logger $logger;

    private int $error_reporting;

    /** Number of error handlers pushed in a test, to be restored in tearDown() */
    private int $pushed_error_handlers = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestHandler();
        $this->logger = new Logger('test', [$this->handler]);
        $this->error_reporting = error_reporting();
    }

    public function tearDown(): void
    {
        for ($i = 0; $i < $this->pushed_error_handlers; $i++) {
            restore_error_handler();
        }
        error_reporting($this->error_reporting);

        parent::tearDown();
    }

    /**
     * Push an error handler onto the stack (restored in tearDown())
     */
    private function pushErrorHandler(?callable $handler): void
    {
        set_error_handler($handler);
        $this->pushed_error_handlers++;
    }

    /**
     * Register the ErrorCapture (its error handler is restored in tearDown())
     */
    private function registerCapture(ErrorCapture $capture): void
    {
        $capture->register();
        $this->pushed_error_handlers++;
    }

    private static function currentErrorHandler(): ?callable
    {
        $current = set_error_handler(fn() => false);
        restore_error_handler();

        return $current;
    }

    private function onlyRecord(): LogRecord
    {
        $records = $this->handler->getRecords();
        $this->assertCount(1, $records);

        return $records[0];
    }

    //==========================================================================
    // register
    //==========================================================================
    // phpcs:ignore
    public function test_register_sets_error_handler(): void
    {
        $this->pushErrorHandler(null);
        $capture = new ErrorCapture($this->logger);

        $this->assertFalse($capture->isRegistered());
        $this->registerCapture($capture);

        $this->assertTrue($capture->isRegistered());
        $this->assertSame([$capture, 'handleError'], self::currentErrorHandler());
    }

    // phpcs:ignore
    public function test_register_adds_wp_php_error_message_filter(): void
    {
        $this->pushErrorHandler(null);
        $capture = new ErrorCapture($this->logger);

        WP_Mock::expectFilterAdded('wp_php_error_message', [$capture, 'filterPhpErrorMessage'], 10, 2);

        $this->registerCapture($capture);
        $this->assertConditionsMet();
    }

    // phpcs:ignore
    public function test_register_adds_wp_debug_hooks_when_wp_debug_is_enabled(): void
    {
        $this->pushErrorHandler(null);
        $capture = new ErrorCapture($this->logger, true);

        foreach (ErrorCapture::WP_DEBUG_HOOKS as $action => [$method, $accepted_args, $filter]) {
            WP_Mock::expectActionAdded($action, [$capture, $method], 10, $accepted_args);
            WP_Mock::expectFilterAdded($filter, '__return_false');
        }

        $this->registerCapture($capture);
        $this->assertConditionsMet();
    }

    // phpcs:ignore
    public function test_register_does_not_add_wp_debug_hooks_when_wp_debug_is_disabled(): void
    {
        $this->pushErrorHandler(null);
        $capture = new ErrorCapture($this->logger, false);

        foreach (ErrorCapture::WP_DEBUG_HOOKS as $action => [$method, $accepted_args, $filter]) {
            WP_Mock::expectHookNotAdded('actions', $action, [$capture, $method], 10, $accepted_args);
            WP_Mock::expectHookNotAdded('filters', $filter, '__return_false');
        }

        $this->registerCapture($capture);
        $this->assertConditionsMet();
    }

    // phpcs:ignore
    public function test_wp_debug_hooks_refer_to_existing_methods(): void
    {
        foreach (ErrorCapture::WP_DEBUG_HOOKS as [$method]) {
            $this->assertTrue(method_exists(ErrorCapture::class, $method), $method);
        }
    }

    // phpcs:ignore
    public function test_register_twice_has_no_effect(): void
    {
        $this->pushErrorHandler(null);
        $capture = new ErrorCapture($this->logger);

        $this->registerCapture($capture);
        $capture->register();

        // Only one handler has been pushed, so restoring once brings back the previous (none)
        restore_error_handler();
        $this->pushed_error_handlers--;
        $this->assertNull(self::currentErrorHandler());
    }

    // phpcs:ignore
    public function test_registered_handler_captures_triggered_error(): void
    {
        $this->pushErrorHandler(null);
        $this->registerCapture(new ErrorCapture($this->logger));

        trigger_error('Triggered warning', E_USER_WARNING);
        $line = __LINE__ - 1;

        $record = $this->onlyRecord();
        $this->assertSame(Level::Warning, $record->level);
        $this->assertSame(
            sprintf('PHP E_USER_WARNING: Triggered warning in %s on line %d', __FILE__, $line),
            $record->message,
        );
    }

    // phpcs:ignore
    public function test_registered_handler_captures_native_php_warning(): void
    {
        $this->pushErrorHandler(null);
        $this->registerCapture(new ErrorCapture($this->logger));

        $array = [];
        $array['undefined_key'];

        $record = $this->onlyRecord();
        $this->assertSame(Level::Warning, $record->level);
        $this->assertSame('E_WARNING', $record->context['type']);
        $this->assertStringContainsString('Undefined array key "undefined_key"', $record->message);
    }

    // phpcs:ignore
    public function test_registered_handler_ignores_errors_suppressed_by_at_operator(): void
    {
        $this->pushErrorHandler(null);
        $this->registerCapture(new ErrorCapture($this->logger));

        @trigger_error('Suppressed warning', E_USER_WARNING);

        $this->assertFalse($this->handler->hasRecords(Level::Warning));
    }

    //==========================================================================
    // handleError
    //==========================================================================
    public static function errorLevelProvider(): array
    {
        return [
            'E_WARNING' => [E_WARNING, 'E_WARNING', Level::Warning],
            'E_NOTICE' => [E_NOTICE, 'E_NOTICE', Level::Notice],
            'E_CORE_WARNING' => [E_CORE_WARNING, 'E_CORE_WARNING', Level::Warning],
            'E_COMPILE_WARNING' => [E_COMPILE_WARNING, 'E_COMPILE_WARNING', Level::Warning],
            'E_USER_WARNING' => [E_USER_WARNING, 'E_USER_WARNING', Level::Warning],
            'E_USER_NOTICE' => [E_USER_NOTICE, 'E_USER_NOTICE', Level::Notice],
            'E_RECOVERABLE_ERROR' => [E_RECOVERABLE_ERROR, 'E_RECOVERABLE_ERROR', Level::Error],
            'E_DEPRECATED' => [E_DEPRECATED, 'E_DEPRECATED', Level::Notice],
            'E_USER_DEPRECATED' => [E_USER_DEPRECATED, 'E_USER_DEPRECATED', Level::Notice],
        ];
    }

    /**
     * @dataProvider errorLevelProvider
     */
    // phpcs:ignore
    public function test_handleError_logs_non_fatal_errors_with_mapped_level(int $errno, string $name, Level $level): void
    {
        error_reporting(E_ALL);
        $capture = new ErrorCapture($this->logger);

        $result = $capture->handleError($errno, 'Something happened', '/path/to/file.php', 42);

        $this->assertTrue($result, 'PHP standard error handler should be skipped');
        $record = $this->onlyRecord();
        $this->assertSame($level, $record->level);
        $this->assertSame("PHP {$name}: Something happened in /path/to/file.php on line 42", $record->message);
        $this->assertSame([
            'type' => $name,
            'message' => 'Something happened',
            'file' => '/path/to/file.php',
            'line' => 42,
        ], $record->context);
    }

    // phpcs:ignore
    public function test_handleError_logs_unknown_error_type_as_critical(): void
    {
        error_reporting(-1);
        $capture = new ErrorCapture($this->logger);

        $result = $capture->handleError(1 << 30, 'Mystery', '/path/to/file.php', 1);

        $this->assertTrue($result);
        $record = $this->onlyRecord();
        $this->assertSame(Level::Critical, $record->level);
        $this->assertSame('Unknown error', $record->context['type']);
    }

    // phpcs:ignore
    public function test_handleError_uses_default_file_and_line(): void
    {
        error_reporting(E_ALL);
        $capture = new ErrorCapture($this->logger);

        $capture->handleError(E_WARNING, 'No location');

        $record = $this->onlyRecord();
        $this->assertSame('', $record->context['file']);
        $this->assertSame(0, $record->context['line']);
    }

    // phpcs:ignore
    public function test_handleError_ignores_errors_not_in_error_reporting(): void
    {
        error_reporting(E_ALL & ~E_NOTICE);
        $capture = new ErrorCapture($this->logger);

        $result = $capture->handleError(E_NOTICE, 'Ignored notice', '/path/to/file.php', 1);

        $this->assertFalse($result);
        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleError_ignores_all_errors_when_error_reporting_is_zero(): void
    {
        error_reporting(0);
        $capture = new ErrorCapture($this->logger);

        $result = $capture->handleError(E_WARNING, 'Ignored warning', '/path/to/file.php', 1);

        $this->assertFalse($result);
        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleError_leaves_fatal_errors_to_php(): void
    {
        error_reporting(E_ALL);
        $capture = new ErrorCapture($this->logger);

        // E_USER_ERROR must terminate the script, which happens only if the handler returns false
        $result = $capture->handleError(E_USER_ERROR, 'User fatal', '/path/to/file.php', 1);

        $this->assertFalse($result);
        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleError_calls_previous_handler_but_skips_php_standard_handler_for_logged_errors(): void
    {
        error_reporting(E_ALL);
        $calls = [];
        $this->pushErrorHandler(function (...$args) use (&$calls) {
            $calls[] = $args;
            return false;
        });
        $capture = new ErrorCapture($this->logger);
        $this->registerCapture($capture);

        $result = $capture->handleError(E_WARNING, 'Chained', '/path/to/file.php', 7);

        // The previous handler asks for PHP's standard handler, but the error has already been logged
        $this->assertTrue($result);
        $this->assertSame([[E_WARNING, 'Chained', '/path/to/file.php', 7]], $calls);
        $this->assertCount(1, $this->handler->getRecords());
    }

    public static function previousHandlerResultProvider(): array
    {
        return [
            'false' => [false, false],
            'true' => [true, true],
            // PHP passes an error to its standard handler only if the handler returns exactly false
            'null (void)' => [null, true],
            '0' => [0, true],
            'empty string' => ['', true],
        ];
    }

    /**
     * @dataProvider previousHandlerResultProvider
     */
    // phpcs:ignore
    public function test_handleError_respects_previous_handler_result_for_fatal_errors($previous_result, bool $expected): void
    {
        error_reporting(E_ALL);
        $this->pushErrorHandler(fn() => $previous_result);
        $capture = new ErrorCapture($this->logger);
        $this->registerCapture($capture);

        $this->assertSame($expected, $capture->handleError(E_USER_ERROR, 'User fatal', '/path/to/file.php', 1));
        $this->assertCount(0, $this->handler->getRecords());
    }

    /**
     * @dataProvider previousHandlerResultProvider
     */
    // phpcs:ignore
    public function test_handleError_respects_previous_handler_result_for_unreported_errors($previous_result, bool $expected): void
    {
        error_reporting(0);
        $this->pushErrorHandler(fn() => $previous_result);
        $capture = new ErrorCapture($this->logger);
        $this->registerCapture($capture);

        $this->assertSame($expected, $capture->handleError(E_WARNING, 'Silenced', '/path/to/file.php', 1));
        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleError_calls_previous_handler_for_unreported_errors(): void
    {
        error_reporting(0);
        $called = false;
        $this->pushErrorHandler(function () use (&$called) {
            $called = true;
            return true;
        });
        $capture = new ErrorCapture($this->logger);
        $this->registerCapture($capture);

        $result = $capture->handleError(E_WARNING, 'Silenced', '/path/to/file.php', 1);

        $this->assertTrue($called);
        $this->assertTrue($result);
        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleError_calls_previous_handler_for_fatal_errors(): void
    {
        error_reporting(E_ALL);
        $called = false;
        $this->pushErrorHandler(function () use (&$called) {
            $called = true;
            return true;
        });
        $capture = new ErrorCapture($this->logger);
        $this->registerCapture($capture);

        $result = $capture->handleError(E_USER_ERROR, 'User fatal', '/path/to/file.php', 1);

        $this->assertTrue($called);
        $this->assertTrue($result);
        $this->assertCount(0, $this->handler->getRecords());
    }

    //==========================================================================
    // handleFatalError
    //==========================================================================
    public static function fatalErrorProvider(): array
    {
        return [
            'E_ERROR' => [E_ERROR, 'E_ERROR'],
            'E_PARSE' => [E_PARSE, 'E_PARSE'],
            'E_CORE_ERROR' => [E_CORE_ERROR, 'E_CORE_ERROR'],
            'E_COMPILE_ERROR' => [E_COMPILE_ERROR, 'E_COMPILE_ERROR'],
            'E_USER_ERROR' => [E_USER_ERROR, 'E_USER_ERROR'],
        ];
    }

    /**
     * @dataProvider fatalErrorProvider
     */
    // phpcs:ignore
    public function test_handleFatalError_logs_fatal_errors_as_critical(int $type, string $name): void
    {
        $capture = new ErrorCapture($this->logger);

        $capture->handleFatalError([
            'type' => $type,
            'message' => 'Fatal!',
            'file' => '/path/to/file.php',
            'line' => 3,
        ]);

        $record = $this->onlyRecord();
        $this->assertSame(Level::Critical, $record->level);
        $this->assertSame("PHP Fatal error ({$name}): Fatal! in /path/to/file.php on line 3", $record->message);
        $this->assertSame([
            'type' => $name,
            'message' => 'Fatal!',
            'file' => '/path/to/file.php',
            'line' => 3,
        ], $record->context);
    }

    public static function nonFatalErrorProvider(): array
    {
        return [
            'null' => [null],
            'empty array' => [[]],
            'E_WARNING' => [['type' => E_WARNING, 'message' => 'w', 'file' => 'f', 'line' => 1]],
            'E_DEPRECATED' => [['type' => E_DEPRECATED, 'message' => 'd', 'file' => 'f', 'line' => 1]],
        ];
    }

    /**
     * @dataProvider nonFatalErrorProvider
     */
    // phpcs:ignore
    public function test_handleFatalError_ignores_non_fatal_errors(?array $error): void
    {
        $capture = new ErrorCapture($this->logger);

        $capture->handleFatalError($error);

        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleFatalError_ignores_errors_not_in_error_reporting(): void
    {
        error_reporting(E_ALL & ~E_USER_ERROR);
        $capture = new ErrorCapture($this->logger);

        $capture->handleFatalError(['type' => E_USER_ERROR, 'message' => 'Unreported', 'file' => 'f', 'line' => 1]);
        $this->assertCount(0, $this->handler->getRecords());

        // An ignored error does not prevent a later reported one from being logged
        $capture->handleFatalError(['type' => E_ERROR, 'message' => 'Reported', 'file' => 'f', 'line' => 1]);
        $this->assertSame('Reported', $this->onlyRecord()->context['message']);
    }

    // phpcs:ignore
    public function test_handleFatalError_ignores_all_errors_when_error_reporting_is_zero(): void
    {
        error_reporting(0);
        $capture = new ErrorCapture($this->logger);

        $capture->handleFatalError(['type' => E_ERROR, 'message' => 'Fatal!', 'file' => 'f', 'line' => 1]);

        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_filterPhpErrorMessage_ignores_errors_not_in_error_reporting(): void
    {
        error_reporting(0);
        $capture = new ErrorCapture($this->logger);

        $result = $capture->filterPhpErrorMessage('message', ['type' => E_ERROR, 'message' => 'Fatal!', 'file' => 'f', 'line' => 1]);

        $this->assertSame('message', $result);
        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleFatalError_logs_only_once(): void
    {
        $capture = new ErrorCapture($this->logger);
        $error = ['type' => E_ERROR, 'message' => 'Fatal!', 'file' => 'f', 'line' => 1];

        $capture->handleFatalError($error);
        $capture->handleFatalError($error);

        $this->assertCount(1, $this->handler->getRecords());
    }

    //==========================================================================
    // handleShutdown
    //==========================================================================
    // phpcs:ignore
    public function test_handleShutdown_ignores_non_fatal_last_error(): void
    {
        error_clear_last();
        @trigger_error('Non fatal', E_USER_NOTICE);
        $capture = new ErrorCapture($this->logger);

        $capture->handleShutdown();

        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_handleShutdown_does_nothing_without_last_error(): void
    {
        error_clear_last();
        $capture = new ErrorCapture($this->logger);

        $capture->handleShutdown();

        $this->assertCount(0, $this->handler->getRecords());
    }

    //==========================================================================
    // filterPhpErrorMessage
    //==========================================================================
    // phpcs:ignore
    public function test_filterPhpErrorMessage_logs_fatal_error_and_returns_message(): void
    {
        $capture = new ErrorCapture($this->logger);
        $error = ['type' => E_ERROR, 'message' => 'Fatal!', 'file' => 'f', 'line' => 1];

        $result = $capture->filterPhpErrorMessage('<p>Critical error</p>', $error);

        $this->assertSame('<p>Critical error</p>', $result);
        $this->assertSame(Level::Critical, $this->onlyRecord()->level);
    }

    // phpcs:ignore
    public function test_filterPhpErrorMessage_ignores_invalid_error(): void
    {
        $capture = new ErrorCapture($this->logger);

        $result = $capture->filterPhpErrorMessage('message', null);

        $this->assertSame('message', $result);
        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_filterPhpErrorMessage_and_handleFatalError_do_not_duplicate(): void
    {
        $capture = new ErrorCapture($this->logger);
        $error = ['type' => E_ERROR, 'message' => 'Fatal!', 'file' => 'f', 'line' => 1];

        $capture->filterPhpErrorMessage('message', $error);
        $capture->handleFatalError($error);

        $this->assertCount(1, $this->handler->getRecords());
    }

    //==========================================================================
    // WordPress debug messages
    //==========================================================================
    // phpcs:ignore
    public function test_logDoingItWrong(): void
    {
        (new ErrorCapture($this->logger))->logDoingItWrong('wp_enqueue_script', 'Scripts should be enqueued later.', '3.3.0');

        $record = $this->onlyRecord();
        $this->assertSame(Level::Notice, $record->level);
        $this->assertSame(
            'wp_enqueue_script was called incorrectly. Scripts should be enqueued later. (This message was added in version 3.3.0.)',
            $record->message,
        );
        $this->assertSame(
            ['type' => 'doing_it_wrong', 'function' => 'wp_enqueue_script', 'version' => '3.3.0'],
            $record->context,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedFunction_with_replacement(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedFunction('old_func', 'new_func', '6.0.0');

        $record = $this->onlyRecord();
        $this->assertSame(Level::Notice, $record->level);
        $this->assertSame('Function old_func is deprecated since version 6.0.0! Use new_func instead.', $record->message);
        $this->assertSame(
            ['type' => 'deprecated_function', 'function' => 'old_func', 'replacement' => 'new_func', 'version' => '6.0.0'],
            $record->context,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedFunction_without_replacement(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedFunction('old_func', '', '6.0.0');

        $this->assertSame(
            'Function old_func is deprecated since version 6.0.0 with no alternative available.',
            $this->onlyRecord()->message,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedConstructor_with_parent_class(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedConstructor('My_Widget', '4.3.0', 'WP_Widget');

        $record = $this->onlyRecord();
        $this->assertSame(Level::Notice, $record->level);
        $this->assertSame(
            'The called constructor method for My_Widget in WP_Widget is deprecated since version 4.3.0! Use __construct() instead.',
            $record->message,
        );
        $this->assertSame(
            ['type' => 'deprecated_constructor', 'class' => 'My_Widget', 'parent_class' => 'WP_Widget', 'version' => '4.3.0'],
            $record->context,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedConstructor_without_parent_class(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedConstructor('My_Widget', '4.3.0');

        $this->assertSame(
            'The called constructor method for My_Widget is deprecated since version 4.3.0! Use __construct() instead.',
            $this->onlyRecord()->message,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedClass_with_replacement(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedClass('Old_Class', 'New_Class', '6.4.0');

        $record = $this->onlyRecord();
        $this->assertSame(Level::Notice, $record->level);
        $this->assertSame('Class Old_Class is deprecated since version 6.4.0! Use New_Class instead.', $record->message);
        $this->assertSame(
            ['type' => 'deprecated_class', 'class' => 'Old_Class', 'replacement' => 'New_Class', 'version' => '6.4.0'],
            $record->context,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedClass_without_replacement(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedClass('Old_Class', '', '6.4.0');

        $this->assertSame(
            'Class Old_Class is deprecated since version 6.4.0 with no alternative available.',
            $this->onlyRecord()->message,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedFile_with_replacement_and_message(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedFile('old.php', 'new.php', '5.0.0', 'Extra info.');

        $record = $this->onlyRecord();
        $this->assertSame(Level::Notice, $record->level);
        $this->assertSame('File old.php is deprecated since version 5.0.0! Use new.php instead. Extra info.', $record->message);
        $this->assertSame(
            ['type' => 'deprecated_file', 'file' => 'old.php', 'replacement' => 'new.php', 'version' => '5.0.0'],
            $record->context,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedFile_without_replacement_and_message(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedFile('old.php', '', '5.0.0');

        $this->assertSame(
            'File old.php is deprecated since version 5.0.0 with no alternative available.',
            $this->onlyRecord()->message,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedArgument_with_message(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedArgument('get_posts', 'Use $args instead.', '5.5.0');

        $record = $this->onlyRecord();
        $this->assertSame(Level::Notice, $record->level);
        $this->assertSame(
            'Function get_posts was called with an argument that is deprecated since version 5.5.0! Use $args instead.',
            $record->message,
        );
        $this->assertSame(
            ['type' => 'deprecated_argument', 'function' => 'get_posts', 'version' => '5.5.0'],
            $record->context,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedArgument_without_message(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedArgument('get_posts', '', '5.5.0');

        $this->assertSame(
            'Function get_posts was called with an argument that is deprecated since version 5.5.0 with no alternative available.',
            $this->onlyRecord()->message,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedHook_with_replacement_and_message(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedHook('old_hook', 'new_hook', '4.0.0', 'Extra info.');

        $record = $this->onlyRecord();
        $this->assertSame(Level::Notice, $record->level);
        $this->assertSame('Hook old_hook is deprecated since version 4.0.0! Use new_hook instead. Extra info.', $record->message);
        $this->assertSame(
            ['type' => 'deprecated_hook', 'hook' => 'old_hook', 'replacement' => 'new_hook', 'version' => '4.0.0'],
            $record->context,
        );
    }

    // phpcs:ignore
    public function test_logDeprecatedHook_without_replacement_and_message(): void
    {
        (new ErrorCapture($this->logger))->logDeprecatedHook('old_hook', '', '4.0.0');

        $this->assertSame(
            'Hook old_hook is deprecated since version 4.0.0 with no alternative available.',
            $this->onlyRecord()->message,
        );
    }

    public static function wordPressDebugMessageProvider(): array
    {
        return [
            'doing_it_wrong' => ['logDoingItWrong', ['func', 'message', '1.0.0'], E_USER_NOTICE],
            'deprecated_function' => ['logDeprecatedFunction', ['func', 'new_func', '1.0.0'], E_USER_DEPRECATED],
            'deprecated_constructor' => ['logDeprecatedConstructor', ['Klass', '1.0.0', 'Parent'], E_USER_DEPRECATED],
            'deprecated_class' => ['logDeprecatedClass', ['Klass', 'NewKlass', '1.0.0'], E_USER_DEPRECATED],
            'deprecated_file' => ['logDeprecatedFile', ['old.php', 'new.php', '1.0.0', 'message'], E_USER_DEPRECATED],
            'deprecated_argument' => ['logDeprecatedArgument', ['func', 'message', '1.0.0'], E_USER_DEPRECATED],
            'deprecated_hook' => ['logDeprecatedHook', ['hook', 'new_hook', '1.0.0', 'message'], E_USER_DEPRECATED],
        ];
    }

    /**
     * @dataProvider wordPressDebugMessageProvider
     */
    // phpcs:ignore
    public function test_wordpress_debug_message_is_logged_when_its_error_type_is_reported(string $method, array $args, int $errno): void
    {
        error_reporting($errno);

        (new ErrorCapture($this->logger))->{$method}(...$args);

        $this->assertSame(Level::Notice, $this->onlyRecord()->level);
    }

    /**
     * @dataProvider wordPressDebugMessageProvider
     */
    // phpcs:ignore
    public function test_wordpress_debug_message_is_not_logged_when_its_error_type_is_not_reported(string $method, array $args, int $errno): void
    {
        error_reporting(E_ALL & ~$errno);

        (new ErrorCapture($this->logger))->{$method}(...$args);

        $this->assertCount(0, $this->handler->getRecords());
    }

    /**
     * @dataProvider wordPressDebugMessageProvider
     */
    // phpcs:ignore
    public function test_wordpress_debug_message_is_not_logged_with_at_operator(string $method, array $args): void
    {
        error_reporting(E_ALL);
        $capture = new ErrorCapture($this->logger);

        @$capture->{$method}(...$args);

        $this->assertCount(0, $this->handler->getRecords());
    }

    // phpcs:ignore
    public function test_wp_debug_hooks_cover_all_data_provider_cases(): void
    {
        $this->assertSame(
            array_column(ErrorCapture::WP_DEBUG_HOOKS, 0),
            array_column(self::wordPressDebugMessageProvider(), 0),
        );
    }

    //==========================================================================
    // errorName
    //==========================================================================
    // phpcs:ignore
    public function test_errorName(): void
    {
        $this->assertSame('E_WARNING', ErrorCapture::errorName(E_WARNING));
        $this->assertSame('E_USER_DEPRECATED', ErrorCapture::errorName(E_USER_DEPRECATED));
        $this->assertSame('Unknown error', ErrorCapture::errorName(1 << 30));
    }

    // phpcs:ignore
    public function test_every_error_type_has_a_name_and_a_level(): void
    {
        $this->assertSame(array_keys(ErrorCapture::ERROR_NAMES), array_keys(ErrorCapture::ERROR_LEVEL_MAP));
        foreach (ErrorCapture::FATAL_ERRORS as $type) {
            $this->assertArrayHasKey($type, ErrorCapture::ERROR_NAMES);
        }
    }
}
