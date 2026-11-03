<?php

namespace WackLogTest;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use WackLog\StdoutLogger;
use WP_Mock;

final class StdoutLoggerTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // getMonolog
    //==========================================================================
    // phpcs:ignore
    public function test_getMonolog_returns_logger_with_stdout_handler(): void
    {
        $monolog = (new StdoutLogger())->getMonolog();

        $this->assertInstanceOf(Logger::class, $monolog);
        $handlers = $monolog->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(StreamHandler::class, $handlers[0]);
        $this->assertSame('php://stdout', $handlers[0]->getUrl());
        $this->assertSame(Level::Debug, $handlers[0]->getLevel());
    }

    // phpcs:ignore
    public function test_getMonolog_uses_line_formatter_by_default(): void
    {
        $handler = (new StdoutLogger())->getMonolog()->getHandlers()[0];

        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());
    }

    // phpcs:ignore
    public function test_getMonolog_uses_json_formatter_when_json_is_enabled(): void
    {
        $handler = (new StdoutLogger(true))->getMonolog()->getHandlers()[0];

        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
    }

    //==========================================================================
    // Log methods
    //==========================================================================
    public static function levelProvider(): array
    {
        return [
            'debug' => ['debug', Level::Debug],
            'info' => ['info', Level::Info],
            'notice' => ['notice', Level::Notice],
            'warning' => ['warning', Level::Warning],
            'error' => ['error', Level::Error],
            'critical' => ['critical', Level::Critical],
            'alert' => ['alert', Level::Alert],
            'emergency' => ['emergency', Level::Emergency],
        ];
    }

    /**
     * @dataProvider levelProvider
     */
    // phpcs:ignore
    public function test_log_method_without_context(string $method, Level $level): void
    {
        $logger = new StdoutLogger();
        $handler = new TestHandler();
        $logger->getMonolog()->setHandlers([$handler]);

        $logger->{$method}('Hello');

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame($level, $records[0]->level);
        $this->assertSame('Hello', $records[0]->message);
        $this->assertSame([], $records[0]->context);
    }

    /**
     * @dataProvider levelProvider
     */
    // phpcs:ignore
    public function test_log_method_with_context(string $method, Level $level): void
    {
        $logger = new StdoutLogger();
        $handler = new TestHandler();
        $logger->getMonolog()->setHandlers([$handler]);

        $logger->{$method}('Hello', ['user_id' => 1]);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame($level, $records[0]->level);
        $this->assertSame(['user_id' => 1], $records[0]->context);
    }
}
