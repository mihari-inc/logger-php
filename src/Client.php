<?php

declare(strict_types=1);

namespace Mihari;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

final class Client extends AbstractLogger
{
    private readonly Configuration $config;
    private readonly Transport $transport;

    private const PSR_TO_MIHARI = [
        LogLevel::EMERGENCY => LogEntry::LEVEL_FATAL,
        LogLevel::ALERT     => LogEntry::LEVEL_FATAL,
        LogLevel::CRITICAL  => LogEntry::LEVEL_FATAL,
        LogLevel::ERROR     => LogEntry::LEVEL_ERROR,
        LogLevel::WARNING   => LogEntry::LEVEL_WARN,
        LogLevel::NOTICE    => LogEntry::LEVEL_INFO,
        LogLevel::INFO      => LogEntry::LEVEL_INFO,
        LogLevel::DEBUG     => LogEntry::LEVEL_DEBUG,
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
     * } $options
     */
    public function __construct(array $options)
    {
        $this->config = new Configuration($options);
        $this->transport = new Transport($this->config);
    }

    /**
     * PSR-3 log method.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $mihariLevel = self::PSR_TO_MIHARI[(string) $level] ?? LogEntry::LEVEL_INFO;
        $this->sendEntry($mihariLevel, (string) $message, $context);
    }

    /**
     * PSR-3 impose Stringable|string : restreindre à string casse l'héritage
     * d'AbstractLogger et fait échouer le chargement de la classe.
     *
     * @param array<string, mixed> $meta
     */
    public function info(string|\Stringable $message, array $meta = []): void
    {
        $this->sendEntry(LogEntry::LEVEL_INFO, (string) $message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function warn(string|\Stringable $message, array $meta = []): void
    {
        $this->sendEntry(LogEntry::LEVEL_WARN, (string) $message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function error(string|\Stringable $message, array $meta = []): void
    {
        $this->sendEntry(LogEntry::LEVEL_ERROR, (string) $message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function debug(string|\Stringable $message, array $meta = []): void
    {
        $this->sendEntry(LogEntry::LEVEL_DEBUG, (string) $message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function fatal(string|\Stringable $message, array $meta = []): void
    {
        $this->sendEntry(LogEntry::LEVEL_FATAL, (string) $message, $meta);
    }

    public function flush(): void
    {
        $this->transport->flush();
    }

    public function getTransport(): Transport
    {
        return $this->transport;
    }

    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendEntry(string $level, string $message, array $context): void
    {
        $meta = array_merge(
            $this->config->systemMeta(),
            $this->config->defaultMeta,
            $context,
        );

        $entry = new LogEntry($level, $message, $meta);
        $this->transport->send($entry);
    }
}
