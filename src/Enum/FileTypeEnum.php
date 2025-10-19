<?php

declare(strict_types=1);

namespace FileUploadService\Enum;

/**
 * Enum for file type categories
 * 
 * @package FileUploadService\Enum
 */
enum FileTypeEnum: string
{
    case IMAGE   = 'image';
    case PDF     = 'pdf';
    case CAD     = 'cad';
    case DOC     = 'doc';
    case ARCHIVE = 'archive';
    case ALL     = 'all';

    /**
     * Get human-readable label for the file type
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::IMAGE   => 'Images',
            self::PDF     => 'PDF Documents',
            self::CAD     => 'CAD Files',
            self::DOC     => 'Documents',
            self::ARCHIVE => 'Archives',
            self::ALL     => 'All Files',
        };
    }


    /**
     * Get file types as string array
     *
     * @return array<string>
     */
    public static function getAllValues(): array
    {
        return array_map(fn(self $type) => $type->value, self::cases());
    }
}
