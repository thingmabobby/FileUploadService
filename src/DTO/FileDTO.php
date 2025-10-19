<?php

declare(strict_types=1);

namespace FileUploadService\DTO;

use FileUploadService\FileServiceValidator;
use FileUploadService\Enum\FileTypeEnum;

/**
 * Data Transfer Object for file information
 * Encapsulates all file-related data that gets passed between service layers
 * 
 * @package FileUploadService
 */
final readonly class FileDTO
{
    /**
     * Constructor
     *
     * @param string $filename The target filename
     * @param string $originalName The original filename (from upload)
     * @param string $extension The file extension (lowercase)
     * @param string|null $mimeType The detected MIME type
     * @param FileTypeEnum|string|null $fileTypeCategory The file type category
     * @param string|null $tmpPath Temporary file path (for $_FILES uploads)
     * @param string|null $dataUri Base64 data URI (for data URI uploads)
     * @param int|null $size File size in bytes
     * @param int $uploadError Upload error code (0 = success)
     */
    public function __construct(
        public string $filename,
        public string $originalName,
        public string $extension,
        public ?string $mimeType = null,
        public FileTypeEnum|string|null $fileTypeCategory = 'unknown',
        public ?string $tmpPath = null,
        public ?string $dataUri = null,
        public ?int $size = null,
        public int $uploadError = 0
    ) {}


    /**
     * Create FileDTO from $_FILES array
     *
     * @param array $files The $_FILES array for a single file
     * @param string $targetFilename The target filename
     * @param FileTypeEnum|string|null $fileTypeCategory The file type category
     * @return self
     */
    public static function fromFilesArray(
        array $files,
        string $targetFilename,
        FileTypeEnum|string|null $fileTypeCategory = null
    ): self {
        $extension = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));

        return new self(
            filename: $targetFilename,
            originalName: $files['name'],
            extension: $extension,
            mimeType: $files['type'] ?? null,
            fileTypeCategory: $fileTypeCategory ?? 'unknown',
            tmpPath: $files['tmp_name'] ?? null,
            size: $files['size'] ?? null,
            uploadError: $files['error'] ?? UPLOAD_ERR_NO_FILE
        );
    }


    /**
     * Create FileDTO from base64 data URI
     * Requires a FileServiceValidator for proper MIME type and file type detection
     *
     * @param string $dataUri The base64 data URI
     * @param string $targetFilename The target filename
     * @param FileServiceValidator $validator Validator instance for MIME type handling
     * @param FileTypeEnum|string|null $fileTypeCategory The file type category (optional, will be detected if not provided)
     * @return self
     */
    public static function fromDataUri(
        string $dataUri,
        string $targetFilename,
        FileServiceValidator $validator,
        FileTypeEnum|string|null $fileTypeCategory = null
    ): self {
        // Extract MIME type from data URI
        $mimeType = null;
        if (preg_match('/^data:([^;]+);/', $dataUri, $matches)) {
            $mimeType = $matches[1];
        }

        // Use validator to get extension from MIME type if needed
        $extension = pathinfo($targetFilename, PATHINFO_EXTENSION);
        if (empty($extension) && $mimeType) {
            $extension = $validator->getExtensionFromMimeType($mimeType);
        }

        // Use validator to determine file type category if not provided
        if ($fileTypeCategory === null) {
            $fileTypeCategory = $validator->getFileTypeCategoryFromDataUri($dataUri);
        }

        return new self(
            filename: $targetFilename,
            originalName: $targetFilename,
            extension: strtolower($extension),
            mimeType: $mimeType,
            fileTypeCategory: $fileTypeCategory,
            dataUri: $dataUri,
            size: null
        );
    }


    /**
     * Check if this is a file upload (has tmpPath)
     */
    public function isFileUpload(): bool
    {
        return $this->tmpPath !== null;
    }


    /**
     * Check if this is a data URI (has dataUri)
     */
    public function isDataUri(): bool
    {
        return $this->dataUri !== null;
    }


    /**
     * Check if upload was successful
     */
    public function isUploadSuccessful(): bool
    {
        return $this->uploadError === UPLOAD_ERR_OK;
    }


    /**
     * Get the file type category as string
     */
    public function getFileTypeCategoryAsString(): string
    {
        if ($this->fileTypeCategory === null) {
            return 'unknown';
        }

        return $this->fileTypeCategory instanceof FileTypeEnum
            ? $this->fileTypeCategory->value
            : $this->fileTypeCategory;
    }


    /**
     * Check if this is an image file
     */
    public function isImage(): bool
    {
        return $this->getFileTypeCategoryAsString() === FileTypeEnum::IMAGE->value;
    }


    /**
     * Check if this is a HEIC/HEIF file that might need conversion
     */
    public function needsHeicConversion(): bool
    {
        return $this->isImage() && in_array($this->extension, ['heic', 'heif']);
    }


    /**
     * Get a display-friendly file type description
     * Delegates to FileTypeEnum::getLabel() for proper labels
     */
    public function getFileTypeDescription(): string
    {
        if ($this->fileTypeCategory instanceof FileTypeEnum) {
            return $this->fileTypeCategory->getLabel();
        }

        // Try to convert string to enum for proper labeling
        $enumValue = FileTypeEnum::tryFrom($this->getFileTypeCategoryAsString());
        return $enumValue ? $enumValue->getLabel() : 'Unknown File Type';
    }


    /**
     * Create a copy with updated filename (for collision resolution)
     */
    public function withFilename(string $newFilename): self
    {
        return new self(
            filename: $newFilename,
            originalName: $this->originalName,
            extension: $this->extension,
            mimeType: $this->mimeType,
            fileTypeCategory: $this->fileTypeCategory,
            tmpPath: $this->tmpPath,
            dataUri: $this->dataUri,
            size: $this->size,
            uploadError: $this->uploadError
        );
    }


    /**
     * Create a copy with updated file type category
     */
    public function withFileTypeCategory(FileTypeEnum|string $fileTypeCategory): self
    {
        return new self(
            filename: $this->filename,
            originalName: $this->originalName,
            extension: $this->extension,
            mimeType: $this->mimeType,
            fileTypeCategory: $fileTypeCategory,
            tmpPath: $this->tmpPath,
            dataUri: $this->dataUri,
            size: $this->size,
            uploadError: $this->uploadError
        );
    }
}
