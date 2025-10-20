<?php

declare(strict_types=1);

namespace FileUploadService;

use FileUploadService\Utils\FilenameSanitizer;
use RuntimeException;

/**
 * Filesystem implementation of FileSaverInterface
 * Handles saving files to the local filesystem
 * 
 * @package FileUploadService
 */
class FilesystemSaver implements FileSaverInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly int $directoryPermissions = 0775,
        private readonly bool $createDirectory = true
    ) {}


    public function saveFile(string $source, string $targetPath, bool $overwriteExisting = false): string
    {
        $fullPath = $this->resolvePath($targetPath);

        // Ensure directory exists first
        $this->ensureDirectoryExists(dirname($fullPath));

        // Use atomic file operations to prevent race conditions
        if (!$overwriteExisting && file_exists($fullPath)) {
            throw new RuntimeException("File already exists: {$targetPath}");
        }

        // Detect if source is file content or file path
        if (file_exists($source) && is_file($source)) {
            // Source is a file path - move/copy the file
            $this->moveFileAtomically($source, $fullPath);
        } else {
            // Source is file content - write directly
            $this->writeContentAtomically($source, $fullPath);
        }

        return $targetPath;
    }


    /**
     * Move file atomically (for uploaded files)
     *
     * @param string $sourcePath Source file path
     * @param string $targetPath Target file path
     * @throws RuntimeException If move fails
     */
    private function moveFileAtomically(string $sourcePath, string $targetPath): void
    {
        // Try move_uploaded_file first (for actual uploaded files)
        if (!move_uploaded_file($sourcePath, $targetPath)) {
            // Fallback for testing: if move_uploaded_file fails, try atomic copy
            // This happens when the file wasn't uploaded via HTTP POST (e.g., in tests)
            $tempPath = $targetPath . '.tmp.' . uniqid();
            if (!copy($sourcePath, $tempPath)) {
                throw new RuntimeException("Failed to copy uploaded file from {$sourcePath} to {$tempPath}");
            }

            // Atomically move the temporary file to the final location
            if (!rename($tempPath, $targetPath)) {
                // Clean up temp file if rename fails
                unlink($tempPath);
                throw new RuntimeException("Failed to move uploaded file from {$sourcePath} to {$targetPath}");
            }

            // Clean up the source file after successful atomic move
            // This handles the case where move_uploaded_file() failed (e.g., in tests)
            unlink($sourcePath);
        }
    }


    /**
     * Write content atomically (for file content strings)
     *
     * @param string $content File content
     * @param string $targetPath Target file path
     * @throws RuntimeException If write fails
     */
    private function writeContentAtomically(string $content, string $targetPath): void
    {
        // Write to temporary file first, then atomically move
        $tempPath = $targetPath . '.tmp.' . uniqid();
        if (file_put_contents($tempPath, $content) === false) {
            throw new RuntimeException("Failed to write temporary file: {$tempPath}");
        }

        // Atomically move the temporary file to the final location
        if (!rename($tempPath, $targetPath)) {
            // Clean up temp file if rename fails
            unlink($tempPath);
            throw new RuntimeException("Failed to save file to: {$targetPath}");
        }
    }


    public function fileExists(string $targetPath): bool
    {
        return file_exists($this->resolvePath($targetPath));
    }


    public function deleteFile(string $targetPath): bool
    {
        $fullPath = $this->resolvePath($targetPath);

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return true; // File doesn't exist, consider it "deleted"
    }


    public function getBasePath(): string
    {
        return $this->basePath;
    }


    /**
     * Resolve a relative path to a full filesystem path with security validation
     *
     * @param string $targetPath Relative target path
     * @return string Full filesystem path
     * @throws RuntimeException If path traversal is detected or path is invalid
     */
    private function resolvePath(string $targetPath): string
    {
        // Clean path to remove null bytes and dangerous characters
        $targetPath = FilenameSanitizer::cleanPath($targetPath);

        // Reject empty paths
        if (empty(trim($targetPath))) {
            throw new RuntimeException("Target path cannot be empty");
        }

        // Reject absolute paths for security - only allow relative paths within basePath
        if (str_starts_with($targetPath, '/') || preg_match('/^[A-Za-z]:/', $targetPath)) {
            throw new RuntimeException("Absolute paths are not allowed for security reasons");
        }

        // Detect and reject path traversal attempts
        if (str_contains($targetPath, '..') || str_contains($targetPath, './')) {
            throw new RuntimeException("Path traversal detected in target path: {$targetPath}");
        }

        // Combine base path with target path
        $resolvedPath = rtrim($this->basePath, '/') . '/' . ltrim($targetPath, '/');

        // Normalize the base path
        $normalizedBasePath = realpath($this->basePath);
        if ($normalizedBasePath === false) {
            throw new RuntimeException("Base path is invalid: {$this->basePath}");
        }

        // Normalize the resolved path
        $normalizedPath = realpath(dirname($resolvedPath));
        if ($normalizedPath === false) {
            // If the directory doesn't exist yet, use the resolved path as-is
            // but still validate it's within the base path
            $normalizedPath = $resolvedPath;
        } else {
            $normalizedPath = $normalizedPath . '/' . basename($resolvedPath);
        }

        // Ensure the resolved path is still within the base path
        // Normalize path separators for comparison
        $normalizedPathForComparison = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalizedPath);
        $normalizedBasePathForComparison = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalizedBasePath);

        if (!str_starts_with($normalizedPathForComparison, $normalizedBasePathForComparison)) {
            throw new RuntimeException("Resolved path escapes base directory: {$targetPath}");
        }

        return $normalizedPath;
    }


    /**
     * Ensure directory exists and is writable
     *
     * @param string $directoryPath Directory path to check/create
     * @throws RuntimeException If directory cannot be created or is not writable
     */
    private function ensureDirectoryExists(string $directoryPath): void
    {
        if (is_dir($directoryPath)) {
            if (!is_writable($directoryPath)) {
                throw new RuntimeException("Directory is not writable: {$directoryPath}");
            }
            return;
        }

        if (!$this->createDirectory) {
            throw new RuntimeException("Directory does not exist and creation is disabled: {$directoryPath}");
        }

        if (!mkdir($directoryPath, $this->directoryPermissions, true)) {
            throw new RuntimeException("Failed to create directory: {$directoryPath}");
        }
    }


    /**
     * Convert upload destination and filename to a relative path within the base path
     *
     * @param string $uploadDestination The upload destination (can be absolute or relative)
     * @param string $filename The filename
     * @return string The relative path within the base path
     * @throws RuntimeException If the upload destination is outside the base path
     */
    public function convertToRelativePath(string $uploadDestination, string $filename): string
    {
        // If uploadDestination is empty, just return the filename
        if (empty($uploadDestination)) {
            return $filename;
        }

        // If uploadDestination is absolute, check if it's within the base path
        if (str_starts_with($uploadDestination, '/') || preg_match('/^[A-Za-z]:/', $uploadDestination)) {
            // Normalize paths for comparison
            $normalizedUploadDestination = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadDestination);
            $normalizedBasePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->basePath);

            if (!str_starts_with($normalizedUploadDestination, $normalizedBasePath)) {
                throw new RuntimeException("Upload destination '{$uploadDestination}' is outside the allowed base path '{$this->basePath}'");
            }

            // Convert to relative path
            $relativeDir = substr($normalizedUploadDestination, strlen($normalizedBasePath));
            $relativeDir = ltrim($relativeDir, DIRECTORY_SEPARATOR);

            return empty($relativeDir) ? $filename : $relativeDir . DIRECTORY_SEPARATOR . $filename;
        }

        // If uploadDestination is already relative, use it as-is
        return ltrim($uploadDestination, '/') . '/' . $filename;
    }


    /**
     * Ensure the upload destination exists and is accessible
     * For filesystem storage, this creates directories and checks permissions
     *
     * @param string $uploadDestination Upload destination to validate
     * @throws RuntimeException If destination cannot be created or is not accessible
     */
    public function ensureUploadDestinationExists(string $uploadDestination): void
    {
        // If upload destination is empty, use base path
        if (empty($uploadDestination)) {
            $uploadDestination = $this->basePath;
        }

        // For filesystem, we need to ensure the directory exists and is writable
        if (!is_dir($uploadDestination)) {
            if (!$this->createDirectory) {
                throw new RuntimeException("Upload directory does not exist: {$uploadDestination}");
            }

            // Try to create the directory
            if (!mkdir($uploadDestination, $this->directoryPermissions, true)) {
                throw new RuntimeException("Failed to create upload destination: {$uploadDestination}");
            }
        }

        if (!is_writable($uploadDestination)) {
            throw new RuntimeException("Upload destination is not writable: {$uploadDestination}");
        }
    }


    /**
     * Resolve the target path based on upload destination and filename
     * For FilesystemSaver, this ensures the path is relative and within the base path
     *
     * @param string $uploadDestination Upload destination
     * @param string $filename Target filename
     * @return string The resolved relative path
     */
    public function resolveTargetPath(string $uploadDestination, string $filename): string
    {
        return $this->convertToRelativePath($uploadDestination, $filename);
    }
}
