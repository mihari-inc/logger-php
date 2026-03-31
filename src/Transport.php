<?php

declare(strict_types=1);

namespace Mihari;

final class Transport
{
    private readonly Configuration $config;

    /** @var LogEntry[] */
    private array $buffer = [];

    private bool $shutdownRegistered = false;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public function send(LogEntry $entry): void
    {
        $this->buffer[] = $entry;

        if (count($this->buffer) >= $this->config->batchSize) {
            $this->flush();
        }

        if (!$this->shutdownRegistered) {
            register_shutdown_function([$this, 'flush']);
            $this->shutdownRegistered = true;
        }
    }

    public function flush(): void
    {
        if (count($this->buffer) === 0) {
            return;
        }

        $entries = $this->buffer;
        $this->buffer = [];

        $this->sendBatch($entries);
    }

    /**
     * @param LogEntry[] $entries
     */
    private function sendBatch(array $entries): void
    {
        $payload = json_encode($entries, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return;
        }

        $headers = [
            'Authorization: Bearer ' . $this->config->token,
            'Content-Type: application/json',
            'User-Agent: mihari-php/1.0.0',
        ];

        $body = $payload;

        if ($this->config->gzipEnabled) {
            $compressed = gzencode($payload, 6);
            if ($compressed !== false) {
                $body = $compressed;
                $headers[] = 'Content-Encoding: gzip';
            }
        }

        $attempt = 0;
        $lastError = '';

        while ($attempt < $this->config->maxRetries) {
            $attempt++;

            $result = $this->executeCurl($body, $headers);

            if ($result['success']) {
                return;
            }

            $lastError = $result['error'];

            if (!$this->isRetryable($result['http_code'])) {
                error_log(sprintf(
                    '[Mihari] Non-retryable error (HTTP %d): %s',
                    $result['http_code'],
                    $lastError,
                ));
                return;
            }

            if ($attempt < $this->config->maxRetries) {
                $delayUs = $this->config->retryDelayMs * 1000 * (2 ** ($attempt - 1));
                usleep($delayUs);
            }
        }

        error_log(sprintf(
            '[Mihari] Failed to send %d log entries after %d attempts: %s',
            count($entries),
            $this->config->maxRetries,
            $lastError,
        ));
    }

    /**
     * @param string $body
     * @param string[] $headers
     * @return array{success: bool, http_code: int, error: string, response: string}
     */
    private function executeCurl(string $body, array $headers): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->config->endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'http_code' => 0,
                'error' => 'cURL error: ' . $curlError,
                'response' => '',
            ];
        }

        $responseStr = is_string($response) ? $response : '';

        return [
            'success' => $httpCode === 202,
            'http_code' => $httpCode,
            'error' => $httpCode !== 202
                ? sprintf('HTTP %d: %s', $httpCode, $responseStr)
                : '',
            'response' => $responseStr,
        ];
    }

    private function isRetryable(int $httpCode): bool
    {
        if ($httpCode === 0) {
            return true;
        }

        if ($httpCode >= 500) {
            return true;
        }

        if ($httpCode === 429) {
            return true;
        }

        return false;
    }

    public function bufferCount(): int
    {
        return count($this->buffer);
    }
}
