# Mihari Logger (PHP)

Log collection and transport library for PHP. Sends structured JSON logs to the Mihari API with batching, gzip compression, and automatic retries.

## Requirements

- PHP 8.1+
- ext-curl
- ext-json
- ext-zlib

## Installation

```bash
composer require mihari/logger
```

## Quick Start

### Static Facade

```php
use Mihari\Mihari;

Mihari::init(['token' => 'your-api-token']);

Mihari::info('User signed in', ['user_id' => 42]);
Mihari::warn('Slow query detected', ['duration_ms' => 1500]);
Mihari::error('Payment failed', ['order_id' => 'ord_123']);
Mihari::debug('Cache miss', ['key' => 'users:42']);
Mihari::fatal('Database connection lost');
```

### Client Instance

```php
use Mihari\Client;

$logger = new Client([
    'token'       => 'your-api-token',
    'endpoint'    => 'https://api.mihari.io/v1/logs',
    'batch_size'  => 10,
    'max_retries' => 3,
    'gzip'        => true,
    'default_meta' => [
        'app'         => 'my-app',
        'environment' => 'production',
    ],
]);

$logger->info('Order created', ['order_id' => 'ord_456']);
$logger->error('Validation failed', ['fields' => ['email', 'name']]);
```

### PSR-3 Compatible

The `Client` class implements `Psr\Log\LoggerInterface`, so it works anywhere a PSR-3 logger is expected:

```php
use Mihari\Client;

function processOrder(Psr\Log\LoggerInterface $logger): void
{
    $logger->info('Processing order');
}

$client = new Client(['token' => 'your-api-token']);
processOrder($client);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `token` | string | (required) | API bearer token |
| `endpoint` | string | `https://api.mihari.io/v1/logs` | API endpoint URL |
| `batch_size` | int | `10` | Number of entries to buffer before sending |
| `max_retries` | int | `3` | Maximum retry attempts on failure |
| `retry_delay_ms` | int | `1000` | Base delay between retries (doubles each attempt) |
| `timeout` | int | `5` | cURL request timeout in seconds |
| `connect_timeout` | int | `2` | cURL connect timeout in seconds |
| `gzip` | bool | `true` | Enable gzip compression |
| `default_meta` | array | `[]` | Default metadata added to every log entry |

## Auto-Captured Metadata

Every log entry automatically includes:

- `hostname` -- server hostname
- `php_version` -- PHP version
- `sapi` -- PHP SAPI name (cli, fpm-fcgi, etc.)

## Log Entry Format

Each log entry is sent as JSON:

```json
{
    "dt": "2024-01-15T10:30:00.000Z",
    "level": "info",
    "message": "User signed in",
    "user_id": 42,
    "hostname": "web-01",
    "php_version": "8.3.1",
    "sapi": "fpm-fcgi"
}
```

## Monolog Integration

Use `MonologHandler` to send Monolog records to Mihari alongside your existing handlers:

```php
use Monolog\Logger;
use Mihari\MonologHandler;

$handler = new MonologHandler(['token' => 'your-api-token']);

$logger = new Logger('app');
$logger->pushHandler($handler);

$logger->info('Application started');
$logger->error('Something went wrong', ['exception' => $e->getMessage()]);
```

You can also pass an existing `Client` instance:

```php
use Mihari\Client;
use Mihari\MonologHandler;
use Monolog\Logger;

$client = new Client(['token' => 'your-api-token']);
$handler = new MonologHandler($client);

$logger = new Logger('app');
$logger->pushHandler($handler);
```

## Laravel Integration

### Service Provider

Create `app/Providers/MihariServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Mihari\Client;
use Mihari\MonologHandler;

class MihariServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            return new Client([
                'token'        => config('services.mihari.token'),
                'endpoint'     => config('services.mihari.endpoint', 'https://api.mihari.io/v1/logs'),
                'default_meta' => [
                    'app'         => config('app.name'),
                    'environment' => config('app.env'),
                ],
            ]);
        });
    }
}
```

### Monolog Channel

Add to `config/logging.php`:

```php
'channels' => [
    'mihari' => [
        'driver'  => 'monolog',
        'handler' => \Mihari\MonologHandler::class,
        'handler_with' => [
            'clientOrOptions' => [
                'token'        => env('MIHARI_TOKEN'),
                'default_meta' => [
                    'app'         => env('APP_NAME'),
                    'environment' => env('APP_ENV'),
                ],
            ],
        ],
    ],

    'stack' => [
        'driver'   => 'stack',
        'channels' => ['daily', 'mihari'],
    ],
],
```

Then use it:

```php
Log::channel('mihari')->info('Order created', ['order_id' => $order->id]);

// Or set MIHARI as part of the stack in LOG_STACK
Log::info('This goes to both daily log and Mihari');
```

### Environment Variables

Add to `.env`:

```
MIHARI_TOKEN=your-api-token
```

Add to `config/services.php`:

```php
'mihari' => [
    'token'    => env('MIHARI_TOKEN'),
    'endpoint' => env('MIHARI_ENDPOINT', 'https://api.mihari.io/v1/logs'),
],
```

## Symfony Integration

### Service Configuration

Add to `config/services.yaml`:

```yaml
services:
    Mihari\Client:
        arguments:
            - token: '%env(MIHARI_TOKEN)%'
              default_meta:
                  app: '%kernel.project_dir%'
                  environment: '%kernel.environment%'

    Mihari\MonologHandler:
        arguments:
            $clientOrOptions: '@Mihari\Client'
```

### Monolog Configuration

Add to `config/packages/monolog.yaml`:

```yaml
monolog:
    handlers:
        mihari:
            type: service
            id: Mihari\MonologHandler
            channels: ['!event']
```

### Environment Variables

Add to `.env`:

```
MIHARI_TOKEN=your-api-token
```

## Batching and Flushing

Logs are buffered and sent in batches (default: 10 entries). The buffer is automatically flushed:

- When the batch size is reached
- At script shutdown via `register_shutdown_function`
- When you call `$client->flush()` or `Mihari::flush()` manually

For long-running processes (queue workers, daemons), flush periodically:

```php
while ($job = $queue->pop()) {
    processJob($job);
    $logger->flush(); // flush after each job
}
```

## Retry Behavior

Failed requests are retried with exponential backoff:

- Attempt 1: immediate
- Attempt 2: 1s delay
- Attempt 3: 2s delay

Only retryable errors trigger retries:
- Network errors (cURL failures)
- HTTP 5xx (server errors)
- HTTP 429 (rate limited)

Client errors (4xx except 429) are not retried.

## Testing

```bash
composer install
composer test
```

## License

MIT
