<?php

declare(strict_types=1);

namespace FileUploadService\DTO;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Utils\FilenameSanitizer;

/**
 * Data Transfer Object for file uploads from $_FILES
 * Represents a file that was uploaded via HTTP form
 * 
 * @package FileUploadService
 */
final readonly class FileUploadDTO
{
    /**
     * Constructor
     *
     * @param string $filename The target filename
     * @param string $originalName The original filename (from upload)
     * @param string $tmpPath Temporary file path (from $_FILES)
     * @param string $extension The file extension (lowercase)
     * @param string|null $mimeType The detected MIME type
     * @param FileTypeEnum|string|null $fileTypeCategory The file type category
     * @param int|null $size File size in bytes
     * @param int $uploadError Upload error code (0 = success)
     */
    public function __construct(
        public string $filename,
        public string $originalName,
        public string $tmpPath,
        public string $extension,
        public ?string $mimeType = null,
        public FileTypeEnum|string|null $fileTypeCategory = null,
        public ?int $size = null,
        public int $uploadError = 0
    ) {}

    /**
     * Create FileUploadDTO from $_FILES array
     *
     * @param array<string, mixed> $files $_FILES array element
     * @param string $targetFilename Target filename (optional)
     * @return self
     */
    public static function fromFilesArray(array $files, string $targetFilename = ''): self
    {
        $nameValue = $files['name'] ?? '';

        $originalName = FilenameSanitizer::cleanFilename(is_string($nameValue) ? $nameValue : '');
        $filename = FilenameSanitizer::cleanFilename($targetFilename ?: $originalName);

        $tmpPathValue = $files['tmp_name'] ?? '';
        $tmpPath = is_string($tmpPathValue) ? $tmpPathValue : '';

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $sizeValue = $files['size'] ?? null;
        $size = isset($sizeValue) && is_numeric($sizeValue) ? (int)$sizeValue : null;

        $errorValue = $files['error'] ?? 0;
        $uploadError = is_numeric($errorValue) ? (int)$errorValue : 0;

        return new self(
            filename: $filename,
            originalName: $originalName,
            tmpPath: $tmpPath,
            extension: $extension,
            size: $size,
            uploadError: $uploadError
        );
    }

    /**
     * Check if upload was successful
     *
     * @return bool True if upload was successful, false otherwise
     */
    public function isUploadSuccessful(): bool
    {
        return $this->uploadError === 0;
    }

    /**
     * Get the file size in a human-readable format
     *
     * @return string Human-readable file size
     */
    public function getFormattedSize(): string
    {
        if ($this->size === null) {
            return 'Unknown size';
        }

        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
