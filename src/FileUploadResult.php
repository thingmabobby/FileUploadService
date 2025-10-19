<?php

declare(strict_types=1);

namespace FileUploadService;

/**
 * Result object for file upload operations
 * Provides detailed information about successful uploads and any errors
 */
class FileUploadResult
{
    /**
     * @param array<string> $successfulFiles Array of successfully uploaded file paths
     * @param array<FileUploadError> $errors Array of upload errors
     * @param int $totalFiles Total number of files attempted
     * @param int $successfulCount Number of successfully uploaded files
     */
    public function __construct(
        public readonly array $successfulFiles,
        public readonly array $errors,
        public readonly int $totalFiles,
        public readonly int $successfulCount
    ) {}


    /**
     * Check if there were any errors during upload
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }


    /**
     * Check if all files were uploaded successfully
     */
    public function isCompleteSuccess(): bool
    {
        return $this->successfulCount === $this->totalFiles;
    }


    /**
     * Check if any files were uploaded successfully
     */
    public function hasSuccessfulUploads(): bool
    {
        return $this->successfulCount > 0;
    }


    /**
     * Get all error messages as an array
     */
    public function getErrorMessages(): array
    {
        return array_map(fn(FileUploadError $error) => $error->message, $this->errors);
    }


    /**
     * Get error details for a specific filename
     */
    public function getErrorForFile(string $filename): ?FileUploadError
    {
        foreach ($this->errors as $error) {
            if ($error->filename === $filename) {
                return $error;
            }
        }
        return null;
    }
}
