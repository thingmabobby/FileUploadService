<?php

declare(strict_types=1);

namespace FileUploadService\Enum;

/**
 * Enum for collision resolution strategies
 * 
 * @package FileUploadService\Enum
 */
enum CollisionStrategyEnum: string
{
    case INCREMENT = 'increment'; // filename_1, filename_2, etc.
    case UUID      = 'uuid';      // filename_uuid
    case TIMESTAMP = 'timestamp'; // filename_timestamp

    /**
     * Get human-readable label for the strategy
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::INCREMENT => 'Increment',
            self::UUID      => 'UUID',
            self::TIMESTAMP => 'Timestamp',
        };
    }

    /**
     * Get description of how the strategy works
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::INCREMENT => 'Appends incremental numbers (filename_1, filename_2)',
            self::UUID      => 'Appends UUID for uniqueness (filename_a1b2c3d4)',
            self::TIMESTAMP => 'Appends timestamp (filename_1234567890)',
        };
    }

    /**
     * Check if this strategy is suitable for high-performance scenarios
     */
    public function isHighPerformance(): bool
    {
        return $this === self::UUID;
    }
}
