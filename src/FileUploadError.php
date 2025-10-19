<?php

declare(strict_types=1);

namespace FileUploadService;

/**
 * Represents an error that occurred during file upload
 */
class FileUploadError
{
    /**
     * @param string $filename The filename that caused the error
     * @param string $message The error message
     * @param string $code The error code (optional)
     */
    public function __construct(
        public readonly string $filename,
        public readonly string $message,
        public readonly string $code = ''
    ) {}


    /**
     * Get a human-readable error description
     */
    public function getDescription(): string
    {
        if ($this->code) {
            return "{$this->filename}: {$this->message} (Code: {$this->code})";
        }
        return "{$this->filename}: {$this->message}";
    }
}
