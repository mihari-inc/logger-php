<?php

declare(strict_types=1);

namespace Mihari;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler that sends log records to Mihari.
 *
 * Usage with Monolog:
 *   $handler = new \Mihari\MonologHandler(['token' => 'your-token']);
 *   $logger = new \Monolog\Logger('app');
 *   $logger->pushHandler($handler);
 *   $logger->info('Hello from Monolog');
 */
final class MonologHandler extends AbstractProcessingHandler
{
    private readonly Client $client;

    private const MONOLOG_TO_MIHARI = [
        Level::Emergency->value => LogEntry::LEVEL_FATAL,
        Level::Alert->value     => LogEntry::LEVEL_FATAL,
        Level::Critical->value  => LogEntry::LEVEL_FATAL,
        Level::Error->value     => LogEntry::LEVEL_ERROR,
        Level::Warning->value   => LogEntry::LEVEL_WARN,
        Level::Notice->value    => LogEntry::LEVEL_INFO,
        Level::Info->value      => LogEntry::LEVEL_INFO,
        Level::Debug->value     => LogEntry::LEVEL_DEBUG,
    ];

    /**
     * @param array{
     *     token: string,
     *     endpoint?: string,
     *     batch_size?: int,
     *     max_retries?: int,
     *     retry_delay_ms?: int,
     *     timeout?: int,
     *     connect_timeout?: int,
     *     gzip?: bool,
     *     default_meta?: array<string, mixed>
     * }|Client $clientOrOptions
     */
    public function __construct(
        array|Client $clientOrOptions,
        Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        if ($clientOrOptions instanceof Client) {
            $this->client = $clientOrOptions;
        } else {
            $this->client = new Client($clientOrOptions);
        }
    }

    protected function write(LogRecord $record): void
    {
        $mihariLevel = self::MONOLOG_TO_MIHARI[$record->level->value] ?? LogEntry::LEVEL_INFO;

        $meta = $record->context;
        $meta['channel'] = $record->channel;

        if (!empty($record->extra)) {
            $meta['extra'] = $record->extra;
        }

        $entry = new LogEntry(
            $mihariLevel,
            $record->message,
            array_merge(
                $this->client->getConfiguration()->systemMeta(),
                $this->client->getConfiguration()->defaultMeta,
                $meta,
            ),
        );

        $this->client->getTransport()->send($entry);
    }

    public function close(): void
    {
        $this->client->flush();
        parent::close();
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
