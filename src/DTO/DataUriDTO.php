<?php

declare(strict_types=1);

namespace FileUploadService\DTO;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\SupportedFileTypesEnum;
use FileUploadService\Utils\FilenameSanitizer;

/**
 * Data Transfer Object for base64 data URI uploads
 * Represents a file that was uploaded as a base64 data URI
 * 
 * @package FileUploadService
 */
final readonly class DataUriDTO
{
    /**
     * Constructor
     *
     * @param string $filename The target filename
     * @param string $dataUri The base64 data URI
     * @param string $extension The file extension (lowercase)
     * @param string|null $mimeType The detected MIME type
     * @param FileTypeEnum|string|null $fileTypeCategory The file type category
     * @param int|null $size File size in bytes
     */
    public function __construct(
        public string $filename,
        public string $dataUri,
        public string $extension,
        public ?string $mimeType = null,
        public FileTypeEnum|string|null $fileTypeCategory = null,
        public ?int $size = null
    ) {}


    /**
     * Create DataUriDTO from data URI string
     *
     * @param string $dataUri The base64 data URI
     * @param string $targetFilename Target filename (optional)
     * @return self
     */
    public static function fromDataUri(string $dataUri, string $targetFilename = ''): self
    {
        // Extract MIME type from data URI
        $mimeType = null;
        if (preg_match('/^data:([^;]+);base64,/', $dataUri, $matches)) {
            $mimeType = $matches[1];
        }

        // Generate filename if not provided
        $filename = $targetFilename;
        if (empty($filename)) {
            $extension = self::getExtensionFromMimeType($mimeType);
            $filename = 'data_uri_file' . ($extension ? '.' . $extension : '');
        }

        $filename = FilenameSanitizer::cleanFilename($filename);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Calculate size from base64 data
        $size = null;
        if (preg_match('/^data:[^;]+;base64,(.+)$/', $dataUri, $matches)) {
            $decodedData = base64_decode($matches[1], true);
            if ($decodedData !== false) {
                $size = strlen($decodedData);
            }
        }

        return new self(
            filename: $filename,
            dataUri: $dataUri,
            extension: $extension,
            mimeType: $mimeType,
            size: $size
        );
    }


    /**
     * Get file extension from MIME type using SupportedFileTypesEnum
     *
     * @param string|null $mimeType MIME type
     * @return string|null File extension
     */
    private static function getExtensionFromMimeType(?string $mimeType): ?string
    {
        if ($mimeType === null) {
            return null;
        }

        $supportedType = SupportedFileTypesEnum::findByMimeType($mimeType);
        return $supportedType?->getStandardExtension();
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
