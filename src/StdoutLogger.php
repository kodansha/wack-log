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

    public function debug($message): void
    {
        $this->log->debug($message);
    }

    public function info($message): void
    {
        $this->log->info($message);
    }

    public function notice($message): void
    {
        $this->log->notice($message);
    }

    public function warning($message): void
    {
        $this->log->warning($message);
    }

    public function error($message): void
    {
        $this->log->error($message);
    }

    public function critical($message): void
    {
        $this->log->critical($message);
    }

    public function alert($message): void
    {
        $this->log->alert($message);
    }

    public function emergency($message): void
    {
        $this->log->emergency($message);
    }
}
