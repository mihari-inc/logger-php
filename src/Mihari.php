<?php

declare(strict_types=1);

namespace Mihari;

/**
 * Static facade for quick logging.
 *
 * Usage:
 *   Mihari::init(['token' => 'your-token']);
 *   Mihari::info('Something happened', ['user_id' => 42]);
 */
final class Mihari
{
    private static ?Client $client = null;

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
    public static function init(array $options): Client
    {
        self::$client = new Client($options);
        return self::$client;
    }

    public static function getClient(): ?Client
    {
        return self::$client;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function info(string $message, array $meta = []): void
    {
        self::requireClient()->info($message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function warn(string $message, array $meta = []): void
    {
        self::requireClient()->warn($message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function error(string $message, array $meta = []): void
    {
        self::requireClient()->error($message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function debug(string $message, array $meta = []): void
    {
        self::requireClient()->debug($message, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function fatal(string $message, array $meta = []): void
    {
        self::requireClient()->fatal($message, $meta);
    }

    public static function flush(): void
    {
        self::$client?->flush();
    }

    /**
     * Reset the singleton (primarily for testing).
     */
    public static function reset(): void
    {
        self::$client = null;
    }

    private static function requireClient(): Client
    {
        if (self::$client === null) {
            throw new \RuntimeException(
                'Mihari has not been initialized. Call Mihari::init([\'token\' => \'...\']) first.',
            );
        }

        return self::$client;
    }
}
