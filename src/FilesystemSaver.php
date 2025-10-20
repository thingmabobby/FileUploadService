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
            // Fallback: if move_uploaded_file fails, try binary-safe copy
            // This happens when the file wasn't uploaded via HTTP POST (e.g., temp converted files)
            $tempPath = $targetPath . '.tmp.' . uniqid();
            
            // Use binary-safe file operations to preserve image data
            $sourceContent = file_get_contents($sourcePath);
            if ($sourceContent === false) {
                throw new RuntimeException("Failed to read source file: {$sourcePath}");
            }
            
            if (file_put_contents($tempPath, $sourceContent, LOCK_EX) === false) {
                throw new RuntimeException("Failed to write to temporary file: {$tempPath}");
            }

            // Atomically move the temporary file to the final location
            if (!rename($tempPath, $targetPath)) {
                // Clean up temp file if rename fails
                unlink($tempPath);
                throw new RuntimeException("Failed to move file from {$sourcePath} to {$targetPath}");
            }

            // Clean up the source file after successful atomic move
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

        // Note: Path traversal checks are now handled in convertToRelativePath() where we can
        // distinguish between legitimate directory navigation (../images/) and malicious filenames

        // Normalize directory separators to platform-specific separator
        $targetPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);

        // Combine base path with target path using DIRECTORY_SEPARATOR
        $resolvedPath = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($targetPath, DIRECTORY_SEPARATOR);

        // Normalize the base path to get the canonical path
        $normalizedBasePath = $this->getCanonicalPath($this->basePath);
        if ($normalizedBasePath === false) {
            throw new RuntimeException("Base path is invalid: {$this->basePath}");
        }

        // For validation, we need to check if the resolved path is within the base path
        // We can't use realpath on non-existent paths, so we'll manually build the expected canonical path
        $resolvedDir = dirname($resolvedPath);

        // Create the directory if it doesn't exist (for validation purposes)
        if (!is_dir($resolvedDir)) {
            // Build expected canonical path by combining normalized base with relative components
            $relativeToBase = str_replace($this->basePath, '', $resolvedDir);
            $relativeToBase = trim($relativeToBase, DIRECTORY_SEPARATOR);
            $normalizedResolvedDir = $normalizedBasePath;

            if (!empty($relativeToBase)) {
                $normalizedResolvedDir .= DIRECTORY_SEPARATOR . $relativeToBase;
            }
        } else {
            $normalizedResolvedDir = $this->getCanonicalPath($resolvedDir);
            if ($normalizedResolvedDir === false) {
                throw new RuntimeException("Failed to resolve directory path: {$resolvedDir}");
            }
        }

        $normalizedPath = $normalizedResolvedDir . DIRECTORY_SEPARATOR . basename($resolvedPath);

        // Normalize both paths for comparison (convert to lowercase on Windows for case-insensitive comparison)
        $normalizedPathForComparison = $this->normalizeForComparison($normalizedPath);
        $normalizedBasePathForComparison = $this->normalizeForComparison($normalizedBasePath);

        if (!str_starts_with($normalizedPathForComparison, $normalizedBasePathForComparison)) {
            throw new RuntimeException("Resolved path escapes base directory: {$targetPath}");
        }

        return $normalizedPath;
    }


    /**
     * Get canonical path
     * On Windows, both paths will have the same format (short or long) from realpath()
     * so they'll be consistent for comparison
     *
     * @param string $path Path to canonicalize
     * @return string|false Canonical path or false on failure
     */
    private function getCanonicalPath(string $path): string|false
    {
        return realpath($path);
    }


    /**
     * Normalize path for comparison (handle case sensitivity on Windows)
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private function normalizeForComparison(string $path): string
    {
        // Normalize directory separators
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // On Windows, paths are case-insensitive
        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized = strtolower($normalized);
        }

        // Ensure trailing separator for consistent comparison
        return rtrim($normalized, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
        // Validate filename for path traversal attacks (this is where the real security risk is)
        if (str_contains($filename, '..') || str_contains($filename, './')) {
            throw new RuntimeException("Path traversal detected in filename: {$filename}");
        }

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

        // If uploadDestination is relative, allow it (including ../ for legitimate directory navigation)
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
        // Check if it's already an absolute path within our base path
        $isAbsolute = str_starts_with($uploadDestination, '/') || preg_match('/^[A-Za-z]:/', $uploadDestination);

        if ($isAbsolute) {
            // Normalize path separators
            $normalizedUploadDest = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadDestination);
            $normalizedBasePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->basePath);

            // Check if it's within the base path
            if (str_starts_with($normalizedUploadDest, $normalizedBasePath)) {
                // It's already a full path within base - use it directly
                $fullDir = $normalizedUploadDest;
            } else {
                throw new RuntimeException("Upload destination '{$uploadDestination}' is outside the allowed base path '{$this->basePath}'");
            }
        } else {
            // It's relative - combine with base path
            $relative = ltrim($uploadDestination, '/\\');
            $fullDir = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . $relative;
        }

        // For filesystem, ensure the directory exists and is writable
        if (!is_dir($fullDir)) {
            if (!$this->createDirectory) {
                throw new RuntimeException("Upload directory does not exist: {$fullDir}");
            }

            if (!mkdir($fullDir, $this->directoryPermissions, true)) {
                throw new RuntimeException("Failed to create upload destination: {$fullDir}");
            }
        }

        if (!is_writable($fullDir)) {
            throw new RuntimeException("Upload destination is not writable: {$fullDir}");
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
