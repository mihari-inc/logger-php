<?php

declare(strict_types=1);

namespace Mihari\Tests;

use Mihari\Client;
use Mihari\Configuration;
use Mihari\LogEntry;
use Mihari\Mihari;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const TOKEN = 'test-token-abc123';

    protected function tearDown(): void
    {
        Mihari::reset();
    }

    public function testConstructRequiresToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('token');
        new Client([]);
    }

    public function testConstructWithEmptyTokenThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(['token' => '']);
    }

    public function testConstructWithValidToken(): void
    {
        $client = new Client(['token' => self::TOKEN]);
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testConfigurationDefaults(): void
    {
        $client = new Client(['token' => self::TOKEN]);
        $config = $client->getConfiguration();

        $this->assertSame(self::TOKEN, $config->token);
        $this->assertSame(Configuration::DEFAULT_ENDPOINT, $config->endpoint);
        $this->assertSame(Configuration::DEFAULT_BATCH_SIZE, $config->batchSize);
        $this->assertSame(Configuration::DEFAULT_MAX_RETRIES, $config->maxRetries);
        $this->assertTrue($config->gzipEnabled);
    }

    public function testConfigurationCustomValues(): void
    {
        $client = new Client([
            'token' => self::TOKEN,
            'endpoint' => 'https://custom.api/logs',
            'batch_size' => 50,
            'max_retries' => 5,
            'gzip' => false,
        ]);

        $config = $client->getConfiguration();
        $this->assertSame('https://custom.api/logs', $config->endpoint);
        $this->assertSame(50, $config->batchSize);
        $this->assertSame(5, $config->maxRetries);
        $this->assertFalse($config->gzipEnabled);
    }

    public function testSystemMetaCapture(): void
    {
        $client = new Client(['token' => self::TOKEN]);
        $config = $client->getConfiguration();
        $meta = $config->systemMeta();

        $this->assertArrayHasKey('hostname', $meta);
        $this->assertArrayHasKey('php_version', $meta);
        $this->assertArrayHasKey('sapi', $meta);
        $this->assertSame(PHP_VERSION, $meta['php_version']);
    }

    public function testInfoBuffersEntry(): void
    {
        $client = new Client([
            'token' => self::TOKEN,
            'batch_size' => 100,
        ]);

        $client->info('test message');
        $this->assertSame(1, $client->getTransport()->bufferCount());
    }

    public function testMultipleLogLevelsBuffer(): void
    {
        $client = new Client([
            'token' => self::TOKEN,
            'batch_size' => 100,
        ]);

        $client->info('info message');
        $client->warn('warn message');
        $client->error('error message');
        $client->debug('debug message');
        $client->fatal('fatal message');

        $this->assertSame(5, $client->getTransport()->bufferCount());
    }

    public function testDefaultMeta(): void
    {
        $client = new Client([
            'token' => self::TOKEN,
            'default_meta' => ['app' => 'test-app', 'env' => 'testing'],
        ]);

        $config = $client->getConfiguration();
        $this->assertSame('test-app', $config->defaultMeta['app']);
        $this->assertSame('testing', $config->defaultMeta['env']);
    }

    public function testPsr3LogMethod(): void
    {
        $client = new Client([
            'token' => self::TOKEN,
            'batch_size' => 100,
        ]);

        $client->log('info', 'PSR-3 log message', ['key' => 'value']);
        $this->assertSame(1, $client->getTransport()->bufferCount());
    }

    public function testStaticFacadeInit(): void
    {
        $client = Mihari::init(['token' => self::TOKEN]);
        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($client, Mihari::getClient());
    }

    public function testStaticFacadeRequiresInit(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not been initialized');
        Mihari::info('test');
    }

    public function testStaticFacadeMethods(): void
    {
        Mihari::init([
            'token' => self::TOKEN,
            'batch_size' => 100,
        ]);

        Mihari::info('info');
        Mihari::warn('warn');
        Mihari::error('error');
        Mihari::debug('debug');
        Mihari::fatal('fatal');

        $this->assertSame(5, Mihari::getClient()->getTransport()->bufferCount());
    }

    public function testStaticFacadeReset(): void
    {
        Mihari::init(['token' => self::TOKEN]);
        $this->assertNotNull(Mihari::getClient());

        Mihari::reset();
        $this->assertNull(Mihari::getClient());
    }
}
