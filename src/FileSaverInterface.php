<?php

declare(strict_types=1);

namespace FileUploadService;

use RuntimeException;

/**
 * Interface for file saving strategies
 * Allows different implementations for filesystem, cloud storage, etc.
 * 
 * @package FileUploadService
 */
interface FileSaverInterface
{
    /**
     * Save file to the target location
     * Can handle both file content (string) and file paths (for uploaded files)
     * The implementation should detect if the source is file content or a file path
     *
     * @param string $source File content (string) or file path (string)
     * @param string $targetPath Target path/identifier for the file
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @return string The final path/identifier where the file was saved
     * @throws RuntimeException If saving fails
     */
    public function saveFile(string $source, string $targetPath, bool $overwriteExisting = false): string;

    /**
     * Check if a file exists at the target location
     *
     * @param string $targetPath Target path/identifier to check
     * @return bool True if file exists, false otherwise
     */
    public function fileExists(string $targetPath): bool;

    /**
     * Delete a file from the target location
     *
     * @param string $targetPath Target path/identifier to delete
     * @return bool True if deletion succeeded, false otherwise
     */
    public function deleteFile(string $targetPath): bool;

    /**
     * Ensure the upload destination exists and is accessible
     * Each implementation handles this differently:
     * - FilesystemSaver: Creates directories and checks permissions
     * - CloudStorageSaver: Validates bucket access
     * - DatabaseSaver: Validates table/collection access
     * - APISaver: Validates endpoint accessibility
     *
     * @param string $uploadDestination Upload destination to validate
     * @throws RuntimeException If destination cannot be created or is not accessible
     */
    public function ensureUploadDestinationExists(string $uploadDestination): void;

    /**
     * Resolve the target path based on upload destination and filename
     * Each implementation can handle this differently (relative paths for filesystem, 
     * bucket/key for cloud storage, etc.)
     *
     * @param string $uploadDestination Upload destination/prefix (directory, bucket/key prefix, etc.)
     * @param string $filename Target filename
     * @return string The resolved path/identifier for this implementation
     */
    public function resolveTargetPath(string $uploadDestination, string $filename): string;

    /**
     * Get the base directory/path for this saver
     *
     * @return string Base directory or identifier
     */
    public function getBasePath(): string;
}
