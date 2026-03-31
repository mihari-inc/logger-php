<?php

declare(strict_types=1);

namespace Mihari;

final class LogEntry implements \JsonSerializable
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARN = 'warn';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_FATAL = 'fatal';

    private const VALID_LEVELS = [
        self::LEVEL_DEBUG,
        self::LEVEL_INFO,
        self::LEVEL_WARN,
        self::LEVEL_ERROR,
        self::LEVEL_FATAL,
    ];

    public readonly string $dt;
    public readonly string $level;
    public readonly string $message;

    /** @var array<string, mixed> */
    public readonly array $meta;

    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        string $level,
        string $message,
        array $meta = [],
        ?string $dt = null,
    ) {
        if (!in_array($level, self::VALID_LEVELS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid log level "%s". Valid levels: %s',
                    $level,
                    implode(', ', self::VALID_LEVELS),
                ),
            );
        }

        $this->level = $level;
        $this->message = $message;
        $this->meta = $meta;
        $this->dt = $dt ?? gmdate('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            [
                'dt' => $this->dt,
                'level' => $this->level,
                'message' => $this->message,
            ],
            $this->meta,
        );
    }

    public static function isValidLevel(string $level): bool
    {
        return in_array($level, self::VALID_LEVELS, true);
    }
}
