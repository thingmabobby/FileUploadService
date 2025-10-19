<?php

declare(strict_types=1);

namespace FileUploadService\Enum;

/**
 * Enum for upload error codes
 * 
 * @package FileUploadService\Enum
 */
enum UploadErrorCodeEnum: int
{
    case OK         = UPLOAD_ERR_OK;          // 0
    case INI_SIZE   = UPLOAD_ERR_INI_SIZE;    // 1
    case FORM_SIZE  = UPLOAD_ERR_FORM_SIZE;   // 2
    case PARTIAL    = UPLOAD_ERR_PARTIAL;     // 3
    case NO_FILE    = UPLOAD_ERR_NO_FILE;     // 4
    case NO_TMP_DIR = UPLOAD_ERR_NO_TMP_DIR;  // 6
    case CANT_WRITE = UPLOAD_ERR_CANT_WRITE;  // 7
    case EXTENSION  = UPLOAD_ERR_EXTENSION;   // 8

    /**
     * Get human-readable error message
     */
    public function getMessage(): string
    {
        return match ($this) {
            self::OK         => 'No error',
            self::INI_SIZE   => 'File exceeds upload_max_filesize directive',
            self::FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive',
            self::PARTIAL    => 'File was only partially uploaded',
            self::NO_FILE    => 'No file was uploaded',
            self::NO_TMP_DIR => 'Missing temporary folder',
            self::CANT_WRITE => 'Failed to write file to disk',
            self::EXTENSION  => 'File upload stopped by extension',
        };
    }

    /**
     * Create from PHP upload error code
     */
    public static function fromInt(int $code): ?self
    {
        return match ($code) {
            UPLOAD_ERR_OK         => self::OK,
            UPLOAD_ERR_INI_SIZE   => self::INI_SIZE,
            UPLOAD_ERR_FORM_SIZE  => self::FORM_SIZE,
            UPLOAD_ERR_PARTIAL    => self::PARTIAL,
            UPLOAD_ERR_NO_FILE    => self::NO_FILE,
            UPLOAD_ERR_NO_TMP_DIR => self::NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE => self::CANT_WRITE,
            UPLOAD_ERR_EXTENSION  => self::EXTENSION,
            default => null,
        };
    }

    /**
     * Check if this represents a successful upload
     */
    public function isSuccess(): bool
    {
        return $this === self::OK;
    }
}
