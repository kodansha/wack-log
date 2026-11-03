<?php

namespace WackLogTest;

use PHPUnit\Framework\TestCase;

/**
 * Runs tests/fixtures/error-capture.php in a child PHP process to test behavior
 * that ends the process: fatal errors, uncaught exceptions and shutdown functions.
 */
final class ErrorCaptureProcessTest extends TestCase
{
    /**
     * Run the fixture and return [exit code, decoded JSON log records from stdout, stdout, stderr]
     */
    private function runScenario(string $scenario, bool $wp_fatal_handler = false, bool $previous_handler = false, bool $lazy_logger = false): array
    {
        $command = [
            PHP_BINARY,
            '-n', // Do not load php.ini, so that the environment does not affect the result
            '-d', 'error_reporting=-1',
            '-d', 'display_errors=0',
            '-d', 'log_errors=1',
            '-d', 'error_log=', // PHP's standard error log goes to stderr in CLI
            __DIR__ . '/fixtures/error-capture.php',
            $scenario,
        ];
        if ($wp_fatal_handler) {
            $command[] = '--wp-fatal-handler';
        }
        if ($previous_handler) {
            $command[] = '--previous-handler';
        }
        if ($lazy_logger) {
            $command[] = '--lazy-logger';
        }

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        $records = [];
        foreach (explode("\n", $stdout) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return [$exit_code, $records, $stdout, $stderr];
    }

    // phpcs:ignore
    public function test_warning_is_logged_instead_of_php_error_log(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('warning');

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('CONTINUED', $stdout);
        $this->assertCount(1, $records);
        $this->assertSame('WARNING', $records[0]['level_name']);
        $this->assertSame('E_WARNING', $records[0]['context']['type']);
        $this->assertStringContainsString('Undefined array key "undefined_key"', $records[0]['message']);
        $this->assertStringNotContainsString('Undefined array key', $stderr, 'Should not reach PHP standard error log');
    }

    // phpcs:ignore
    public function test_warning_is_not_passed_to_php_error_log_even_if_previous_handler_returns_false(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('warning', false, true);

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('PREVIOUS_HANDLER_CALLED', $stdout);
        $this->assertCount(1, $records);
        $this->assertSame('E_WARNING', $records[0]['context']['type']);
        $this->assertStringNotContainsString('Undefined array key', $stderr, 'Should not reach PHP standard error log');
    }

    // phpcs:ignore
    public function test_deprecation_is_logged(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('deprecated');

        $this->assertSame(0, $exit_code);
        $this->assertCount(1, $records);
        $this->assertSame('NOTICE', $records[0]['level_name']);
        $this->assertSame('E_USER_DEPRECATED', $records[0]['context']['type']);
        $this->assertSame('', $stderr);
    }

    // phpcs:ignore
    public function test_suppressed_error_is_not_logged(): void
    {
        [$exit_code, $records, $stdout] = $this->runScenario('suppressed');

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('CONTINUED', $stdout);
        $this->assertCount(0, $records);
    }

    // phpcs:ignore
    public function test_doing_it_wrong_is_logged_once_with_context(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('doing_it_wrong');

        $this->assertSame(0, $exit_code);
        $this->assertCount(1, $records, 'trigger_error() should be suppressed to avoid duplicates');
        $this->assertSame('NOTICE', $records[0]['level_name']);
        $this->assertSame(
            ['type' => 'doing_it_wrong', 'function' => 'some_function', 'version' => '6.0.0'],
            $records[0]['context'],
        );
        $this->assertSame('', $stderr);
    }

    // phpcs:ignore
    public function test_doing_it_wrong_with_at_operator_is_not_logged(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('suppressed_doing_it_wrong');

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('CONTINUED', $stdout);
        $this->assertCount(0, $records);
        $this->assertSame('', $stderr);
    }

    // phpcs:ignore
    public function test_unreported_fatal_error_is_not_logged(): void
    {
        [$exit_code, $records, $stdout] = $this->runScenario('unreported_fatal');

        $this->assertSame(255, $exit_code);
        $this->assertStringNotContainsString('CONTINUED', $stdout);
        $this->assertCount(0, $records);
    }

    // phpcs:ignore
    public function test_fatal_error_is_logged_on_shutdown(): void
    {
        [$exit_code, $records, $stdout] = $this->runScenario('fatal');

        $this->assertSame(255, $exit_code);
        $this->assertStringNotContainsString('CONTINUED', $stdout);
        $this->assertCount(1, $records);
        $this->assertSame('CRITICAL', $records[0]['level_name']);
        $this->assertSame('E_ERROR', $records[0]['context']['type']);
        $this->assertStringContainsString('Call to undefined function undefined_function()', $records[0]['message']);
    }

    // phpcs:ignore
    public function test_fatal_error_is_logged_when_wp_fatal_handler_exits_first(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('fatal', true);

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('WP_DIE', $stderr);
        $this->assertCount(1, $records);
        $this->assertSame('CRITICAL', $records[0]['level_name']);
        $this->assertStringContainsString('Call to undefined function undefined_function()', $records[0]['message']);
    }

    // phpcs:ignore
    public function test_uncaught_exception_is_logged_as_fatal_error(): void
    {
        [$exit_code, $records] = $this->runScenario('exception');

        $this->assertSame(255, $exit_code);
        $this->assertCount(1, $records);
        $this->assertSame('CRITICAL', $records[0]['level_name']);
        $this->assertSame('E_ERROR', $records[0]['context']['type']);
        $this->assertStringContainsString('Uncaught RuntimeException: Uncaught!', $records[0]['message']);
    }

    // phpcs:ignore
    public function test_uncaught_exception_is_logged_when_wp_fatal_handler_exits_first(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('exception', true);

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('WP_DIE', $stderr);
        $this->assertCount(1, $records);
        $this->assertStringContainsString('Uncaught RuntimeException: Uncaught!', $records[0]['message']);
    }

    // phpcs:ignore
    public function test_user_error_terminates_script_and_is_logged(): void
    {
        [$exit_code, $records, $stdout] = $this->runScenario('user_error');

        $this->assertSame(255, $exit_code);
        $this->assertStringNotContainsString('CONTINUED', $stdout);

        $fatal_records = array_values(array_filter($records, fn($r) => $r['level_name'] === 'CRITICAL'));
        $this->assertCount(1, $fatal_records);
        $this->assertSame('E_USER_ERROR', $fatal_records[0]['context']['type']);
        $this->assertSame('User fatal', $fatal_records[0]['context']['message']);
    }

    // phpcs:ignore
    public function test_out_of_memory_error_is_logged(): void
    {
        [$exit_code, $records] = $this->runScenario('out_of_memory');

        $this->assertSame(255, $exit_code);
        $this->assertCount(1, $records);
        $this->assertSame('CRITICAL', $records[0]['level_name']);
        $this->assertStringContainsString('Allowed memory size', $records[0]['message']);
    }

    // phpcs:ignore
    public function test_out_of_memory_error_is_logged_when_wp_fatal_handler_exits_first(): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('out_of_memory', true);

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('WP_DIE', $stderr);
        $this->assertCount(1, $records);
        $this->assertStringContainsString('Allowed memory size', $records[0]['message']);
    }

    public static function smallAllocationOutOfMemoryProvider(): array
    {
        return [
            'logger created up front' => [false, false],
            'logger created on first log' => [false, true],
            'logger created on first log, WP fatal handler' => [true, true],
        ];
    }

    /**
     * Small allocations exhaust memory without leaving a free 2 MiB chunk, so freeing the
     * reserved memory alone is not enough to load the logger classes and log the error
     *
     * @dataProvider smallAllocationOutOfMemoryProvider
     */
    // phpcs:ignore
    public function test_out_of_memory_error_by_small_allocations_is_logged(bool $wp_fatal_handler, bool $lazy_logger): void
    {
        [$exit_code, $records, $stdout, $stderr] = $this->runScenario('out_of_memory_small', $wp_fatal_handler, false, $lazy_logger);

        $this->assertSame($wp_fatal_handler ? 1 : 255, $exit_code);
        $this->assertCount(1, $records, $stderr);
        $this->assertSame('CRITICAL', $records[0]['level_name']);
        $this->assertStringContainsString('Allowed memory size', $records[0]['message']);
        $this->assertSame(1, substr_count($stderr, 'Allowed memory size'), 'Logging itself should not run out of memory');
    }
}
