<?php

namespace WackLog;

use Monolog;

/**
 * Class StdoutLogger
 *
 * A simple logger class to output logs to stdout.
 * This class is implemented as thin Monolog wrapper.
 *
 * [Usage]
 * use WackLog\StdoutLogger;
 *
 * $log = new StdoutLogger();
 * $log->warning('This is warning message.');
 * $log->error('This is error message.');
 */
class StdoutLogger
{
    private MonoLog\Logger $log;

    public function __construct(bool $json = false)
    {
        $this->log = new MonoLog\Logger('stdout');

        $stream_handler = new MonoLog\Handler\StreamHandler('php://stdout', MonoLog\Level::Debug);

        // If $json is true, output logs as JSON format.
        if ($json) {
            $stream_handler->setFormatter(new Monolog\Formatter\JsonFormatter());
        }

        $this->log->pushHandler($stream_handler);
    }

    /**
     * Get the underlying Monolog logger instance
     */
    public function getMonolog(): MonoLog\Logger
    {
        return $this->log;
    }

    public function debug($message, array $context = []): void
    {
        $this->log->debug($message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log->info($message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log->notice($message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log->warning($message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log->error($message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log->critical($message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log->alert($message, $context);
    }

    public function emergency($message, array $context = []): void
    {
        $this->log->emergency($message, $context);
    }
}
