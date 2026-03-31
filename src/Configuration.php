<?php

declare(strict_types=1);

namespace Mihari;

final class Configuration
{
    public const DEFAULT_ENDPOINT = 'https://api.mihari.io/v1/logs';
    public const DEFAULT_BATCH_SIZE = 10;
    public const DEFAULT_MAX_RETRIES = 3;
    public const DEFAULT_RETRY_DELAY_MS = 1000;
    public const DEFAULT_TIMEOUT = 5;
    public const DEFAULT_CONNECT_TIMEOUT = 2;
    public const DEFAULT_GZIP_ENABLED = true;

    public readonly string $token;
    public readonly string $endpoint;
    public readonly int $batchSize;
    public readonly int $maxRetries;
    public readonly int $retryDelayMs;
    public readonly int $timeout;
    public readonly int $connectTimeout;
    public readonly bool $gzipEnabled;
    public readonly string $hostname;
    public readonly string $phpVersion;
    public readonly string $sapiName;

    /** @var array<string, mixed> */
    public readonly array $defaultMeta;

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
        if (!isset($options['token']) || $options['token'] === '') {
            throw new \InvalidArgumentException('A non-empty "token" is required in configuration.');
        }

        $this->token = $options['token'];
        $this->endpoint = $options['endpoint'] ?? self::DEFAULT_ENDPOINT;
        $this->batchSize = $options['batch_size'] ?? self::DEFAULT_BATCH_SIZE;
        $this->maxRetries = $options['max_retries'] ?? self::DEFAULT_MAX_RETRIES;
        $this->retryDelayMs = $options['retry_delay_ms'] ?? self::DEFAULT_RETRY_DELAY_MS;
        $this->timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $this->connectTimeout = $options['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT;
        $this->gzipEnabled = $options['gzip'] ?? self::DEFAULT_GZIP_ENABLED;
        $this->defaultMeta = $options['default_meta'] ?? [];

        $this->hostname = gethostname() ?: 'unknown';
        $this->phpVersion = PHP_VERSION;
        $this->sapiName = php_sapi_name() ?: 'unknown';
    }

    /**
     * @return array<string, string>
     */
    public function systemMeta(): array
    {
        return [
            'hostname' => $this->hostname,
            'php_version' => $this->phpVersion,
            'sapi' => $this->sapiName,
        ];
    }
}
