<?php

declare(strict_types=1);

namespace FileUploadService;

/**
 * Interface for file saving strategies
 * Allows different implementations for filesystem, cloud storage, etc.
 * 
 * @package FileUploadService
 */
interface FileSaverInterface
{
    /**
     * Save file content to the target location
     *
     * @param string $content File content to save
     * @param string $targetPath Target path/identifier for the file
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @return string The final path/identifier where the file was saved
     * @throws RuntimeException If saving fails
     */
    public function saveFile(string $content, string $targetPath, bool $overwriteExisting = false): string;

    /**
     * Move uploaded file to the target location
     *
     * @param string $sourcePath Source file path (from $_FILES)
     * @param string $targetPath Target path/identifier for the file
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @return string The final path/identifier where the file was saved
     * @throws RuntimeException If moving fails
     */
    public function moveUploadedFile(string $sourcePath, string $targetPath, bool $overwriteExisting = false): string;

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
     * Get the base directory/path for this saver
     *
     * @return string Base directory or identifier
     */
    public function getBasePath(): string;
}
