<?php

declare(strict_types=1);

namespace Mihari\Tests;

use Mihari\Configuration;
use Mihari\LogEntry;
use Mihari\Transport;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    private function makeConfig(array $overrides = []): Configuration
    {
        return new Configuration(array_merge(
            ['token' => 'test-token'],
            $overrides,
        ));
    }

    public function testBufferCountStartsAtZero(): void
    {
        $transport = new Transport($this->makeConfig());
        $this->assertSame(0, $transport->bufferCount());
    }

    public function testSendBuffersEntries(): void
    {
        $transport = new Transport($this->makeConfig(['batch_size' => 100]));

        $entry = new LogEntry(LogEntry::LEVEL_INFO, 'test message');
        $transport->send($entry);

        $this->assertSame(1, $transport->bufferCount());
    }

    public function testFlushClearsBuffer(): void
    {
        $config = $this->makeConfig(['batch_size' => 100]);
        $transport = new Transport($config);

        $transport->send(new LogEntry(LogEntry::LEVEL_INFO, 'message 1'));
        $transport->send(new LogEntry(LogEntry::LEVEL_INFO, 'message 2'));

        $this->assertSame(2, $transport->bufferCount());

        // Flush will attempt HTTP and fail, but buffer should still clear
        $transport->flush();
        $this->assertSame(0, $transport->bufferCount());
    }

    public function testFlushOnEmptyBufferIsNoop(): void
    {
        $transport = new Transport($this->makeConfig());
        $transport->flush();
        $this->assertSame(0, $transport->bufferCount());
    }

    public function testLogEntryValidLevels(): void
    {
        foreach (['debug', 'info', 'warn', 'error', 'fatal'] as $level) {
            $entry = new LogEntry($level, 'test');
            $this->assertSame($level, $entry->level);
        }
    }

    public function testLogEntryInvalidLevelThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level');
        new LogEntry('critical', 'test');
    }

    public function testLogEntryJsonSerialization(): void
    {
        $entry = new LogEntry(
            LogEntry::LEVEL_ERROR,
            'something broke',
            ['user_id' => 42, 'request_id' => 'abc'],
            '2024-01-15T10:30:00.000Z',
        );

        $json = $entry->jsonSerialize();

        $this->assertSame('2024-01-15T10:30:00.000Z', $json['dt']);
        $this->assertSame('error', $json['level']);
        $this->assertSame('something broke', $json['message']);
        $this->assertSame(42, $json['user_id']);
        $this->assertSame('abc', $json['request_id']);
    }

    public function testLogEntryAutoTimestamp(): void
    {
        $before = gmdate('Y-m-d\TH:i');
        $entry = new LogEntry(LogEntry::LEVEL_INFO, 'test');
        $after = gmdate('Y-m-d\TH:i');

        // Timestamp should start with the current UTC minute
        $this->assertTrue(
            str_starts_with($entry->dt, $before) || str_starts_with($entry->dt, $after),
            'Auto-generated timestamp should be close to current UTC time',
        );
    }

    public function testLogEntryIsValidLevel(): void
    {
        $this->assertTrue(LogEntry::isValidLevel('info'));
        $this->assertTrue(LogEntry::isValidLevel('fatal'));
        $this->assertFalse(LogEntry::isValidLevel('critical'));
        $this->assertFalse(LogEntry::isValidLevel(''));
    }

    public function testLogEntryJsonEncoding(): void
    {
        $entry = new LogEntry(
            LogEntry::LEVEL_INFO,
            'hello world',
            ['service' => 'api'],
            '2024-01-01T00:00:00.000Z',
        );

        $json = json_encode($entry, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('2024-01-01T00:00:00.000Z', $decoded['dt']);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('hello world', $decoded['message']);
        $this->assertSame('api', $decoded['service']);
    }

    public function testBatchSizeTriggersFlush(): void
    {
        // With batch_size=3, the 3rd entry should trigger flush
        $config = $this->makeConfig(['batch_size' => 3]);
        $transport = new Transport($config);

        $transport->send(new LogEntry(LogEntry::LEVEL_INFO, 'msg 1'));
        $this->assertSame(1, $transport->bufferCount());

        $transport->send(new LogEntry(LogEntry::LEVEL_INFO, 'msg 2'));
        $this->assertSame(2, $transport->bufferCount());

        // This triggers flush (batch_size reached), buffer clears after HTTP attempt
        $transport->send(new LogEntry(LogEntry::LEVEL_INFO, 'msg 3'));
        $this->assertSame(0, $transport->bufferCount());
    }

    public function testConfigurationGzipDefault(): void
    {
        $config = $this->makeConfig();
        $this->assertTrue($config->gzipEnabled);
    }

    public function testConfigurationGzipDisabled(): void
    {
        $config = $this->makeConfig(['gzip' => false]);
        $this->assertFalse($config->gzipEnabled);
    }
}
