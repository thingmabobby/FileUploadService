<?php

declare(strict_types=1);

namespace FileUploadService;

use FileUploadService\FileServiceValidator;
use FileUploadService\Enum\CollisionStrategyEnum;
use FileUploadService\Enum\FileTypeEnum;

/**
 * Service for resolving filename collisions in file uploads
 * Handles different collision resolution strategies: increment, UUID, timestamp, and custom callables
 * 
 * @package FileUploadService
 */
class FileCollisionResolver
{
    /**
     * Constructor
     *
     * @param FileServiceValidator $validator File validator instance
     * @param string|callable $collisionStrategy Strategy for resolving filename collisions
     */
    public function __construct(
        private FileServiceValidator $validator,
        private mixed $collisionStrategy = CollisionStrategyEnum::INCREMENT->value
    ) {}


    /**
     * Generate unique filenames for a list of base filenames
     *
     * @param string $uploadDir The upload directory
     * @param array<string> $baseFilenames Array of base filenames
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return array<string> Array of unique filenames
     */
    public function generateUniqueFilenames(string $uploadDir, array $baseFilenames, array $usedFilenames = []): array
    {
        $uniqueFilenames = [];
        $currentUsedFilenames = $usedFilenames;

        foreach ($baseFilenames as $baseFilename) {
            $uniqueFilename = $this->generateUniqueFilename($uploadDir, $baseFilename, $currentUsedFilenames);
            $uniqueFilenames[] = $uniqueFilename;
            $currentUsedFilenames[] = $uniqueFilename;
        }

        return $uniqueFilenames;
    }


    /**
     * Generate a unique filename for a single base filename
     *
     * @param string $uploadDir The upload directory
     * @param string $baseFilename The base filename
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return string The unique filename
     */
    public function generateUniqueFilename(string $uploadDir, string $baseFilename, array $usedFilenames = []): string
    {
        // Check if this is a complete filename with extension
        $pathInfo = pathinfo($baseFilename);
        $hasExtension = !empty($pathInfo['extension']);

        if ($hasExtension) {
            // For complete filenames, check if the file exists directly
            $testFilePath = rtrim($uploadDir, '/') . '/' . $baseFilename;
            $hasConflict = file_exists($testFilePath);
        } else {
            // For base filenames, check against all possible extensions
            $possibleExtensions = $this->getAllPossibleExtensions();
            $hasConflict = false;

            foreach ($possibleExtensions as $ext) {
                $testFilename = $baseFilename . '.' . $ext;
                $testFilePath = rtrim($uploadDir, '/') . '/' . $testFilename;

                if (file_exists($testFilePath)) {
                    $hasConflict = true;
                    break;
                }
            }
        }

        // Check if filename is already used in this batch
        if (in_array($baseFilename, $usedFilenames, true)) {
            $hasConflict = true;
        }

        // If no conflict, return original filename
        if (!$hasConflict) {
            return $baseFilename;
        }

        if ($hasExtension) {
            // For complete filenames, generate unique name with proper extension handling
            $actualBaseFilename = $pathInfo['filename'] ?? $baseFilename;
            $extension = $pathInfo['extension'] ?? '';

            $possibleExtensions = $this->getAllPossibleExtensions();
            $uniqueBaseFilename = $this->resolveCollision(
                baseFilename: $actualBaseFilename,
                uploadDir: $uploadDir,
                possibleExtensions: $possibleExtensions,
                usedFilenames: $usedFilenames
            );

            return $uniqueBaseFilename . '.' . $extension;
        } else {
            // For base filenames, use the original logic
            $possibleExtensions = $this->getAllPossibleExtensions();
            return $this->resolveCollision(
                baseFilename: $baseFilename,
                uploadDir: $uploadDir,
                possibleExtensions: $possibleExtensions,
                usedFilenames: $usedFilenames
            );
        }
    }


    /**
     * Resolve filename collision using the configured strategy
     *
     * @param string $baseFilename The base filename
     * @param string $uploadDir The upload directory
     * @param array<string> $possibleExtensions Array of possible file extensions
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return string Unique filename
     */
    private function resolveCollision(
        string $baseFilename,
        string $uploadDir,
        array $possibleExtensions,
        array $usedFilenames
    ): string {
        // If custom callable provided, use it
        if (is_callable($this->collisionStrategy)) {
            return $this->resolveWithCustomStrategy(
                $this->collisionStrategy,
                $baseFilename,
                $uploadDir,
                $possibleExtensions,
                $usedFilenames
            );
        }

        // Use built-in strategy
        return match ($this->collisionStrategy) {
            CollisionStrategyEnum::UUID->value      => $this->resolveWithUuid($baseFilename, $uploadDir, $possibleExtensions, $usedFilenames),
            CollisionStrategyEnum::TIMESTAMP->value => $this->resolveWithTimestamp($baseFilename, $uploadDir, $possibleExtensions, $usedFilenames),
            CollisionStrategyEnum::INCREMENT->value => $this->resolveWithIncrement($baseFilename, $uploadDir, $possibleExtensions, $usedFilenames),
            default => $this->resolveWithIncrement($baseFilename, $uploadDir, $possibleExtensions, $usedFilenames),
        };
    }


    /**
     * Resolve filename collision using increment strategy (filename_1, filename_2, etc.)
     *
     * @param string $baseFilename The base filename
     * @param string $uploadDir The upload directory
     * @param array<string> $possibleExtensions Array of possible file extensions
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return string Unique filename
     */
    public function resolveWithIncrement(
        string $baseFilename,
        string $uploadDir,
        array $possibleExtensions,
        array $usedFilenames
    ): string {
        $counter = 1;

        while (true) {
            $candidateFilename = $baseFilename . '_' . $counter;

            // Safety limit: fallback to cryptographically secure random identifier
            if ($counter >= 1000) {
                return $baseFilename . '_' . bin2hex(random_bytes(8));
            }

            // Check if unique
            if ($this->isFilenameUnique($candidateFilename, $uploadDir, $possibleExtensions, $usedFilenames)) {
                return $candidateFilename;
            }

            $counter++;
        }
    }


    /**
     * Resolve collision using UUID strategy
     *
     * @param string $baseFilename The base filename
     * @param string $uploadDir The upload directory
     * @param array<string> $possibleExtensions Array of possible file extensions
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return string Unique filename
     */
    public function resolveWithUuid(
        string $baseFilename,
        string $uploadDir,
        array $possibleExtensions,
        array $usedFilenames
    ): string {
        $maxAttempts = 100;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $uuid = $this->generateShortUuid();
            $candidateFilename = $baseFilename . '_' . $uuid;

            if ($this->isFilenameUnique($candidateFilename, $uploadDir, $possibleExtensions, $usedFilenames)) {
                return $candidateFilename;
            }
        }

        // Fallback: extremely unlikely, but use cryptographically secure random identifier
        return $baseFilename . '_' . bin2hex(random_bytes(8));
    }


    /**
     * Resolve collision using timestamp strategy
     *
     * @param string $baseFilename The base filename
     * @param string $uploadDir The upload directory
     * @param array<string> $possibleExtensions Array of possible file extensions
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return string Unique filename
     */
    public function resolveWithTimestamp(
        string $baseFilename,
        string $uploadDir,
        array $possibleExtensions,
        array $usedFilenames
    ): string {
        $maxAttempts = 1000;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $timestamp = time();
            $microseconds = (int)(microtime(true) * 10000) % 10000;
            $candidateFilename = $baseFilename . '_' . $timestamp . ($attempt > 0 ? '_' . $microseconds : '');

            if ($this->isFilenameUnique($candidateFilename, $uploadDir, $possibleExtensions, $usedFilenames)) {
                return $candidateFilename;
            }

            usleep(100); // Small delay to ensure different timestamp
        }

        // Fallback with random suffix
        return $baseFilename . '_' . time() . '_' . random_int(1000, 9999);
    }


    /**
     * Resolve collision using custom callable strategy
     *
     * @param callable $strategy Custom collision resolution function
     * @param string $baseFilename The base filename
     * @param string $uploadDir The upload directory
     * @param array<string> $possibleExtensions Array of possible file extensions
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return string Unique filename
     */
    public function resolveWithCustomStrategy(
        callable $strategy,
        string $baseFilename,
        string $uploadDir,
        array $possibleExtensions,
        array $usedFilenames
    ): string {
        return call_user_func(
            $strategy,
            $baseFilename,
            $uploadDir,
            $possibleExtensions,
            $usedFilenames
        );
    }


    /**
     * Check if a filename is unique (not used in batch and doesn't exist on disk)
     *
     * Performance Note: This method performs filesystem checks.
     *
     * @param string $candidateFilename The filename to check
     * @param string $uploadDir The upload directory
     * @param array<string> $possibleExtensions Array of possible file extensions
     * @param array<string> $usedFilenames Array of already used filenames in this batch
     * @return bool True if unique, false otherwise
     */
    public function isFilenameUnique(
        string $candidateFilename,
        string $uploadDir,
        array $possibleExtensions,
        array $usedFilenames
    ): bool {
        // Quick check: memory lookup first (no I/O)
        if (in_array($candidateFilename, $usedFilenames, true)) {
            return false;
        }

        // Filesystem checks: iterate through possible extensions
        // Note: We check all extensions to prevent collisions across file types
        // (e.g., prevent photo.jpg when photo.png exists)
        $uploadDirPath = rtrim($uploadDir, '/') . '/';

        foreach ($possibleExtensions as $ext) {
            if (file_exists($uploadDirPath . $candidateFilename . '.' . $ext)) {
                return false; // Early return on first collision
            }
        }

        return true;
    }


    /**
     * Generate a short UUID (8 characters) using cryptographically secure random bytes
     *
     * @return string Short UUID (8 hexadecimal characters)
     */
    public function generateShortUuid(): string
    {
        return bin2hex(random_bytes(4)); // 4 bytes = 8 hex characters
    }


    /**
     * Get all possible file extensions from supported types
     *
     * @return array<string> Array of all possible file extensions
     */
    public function getAllPossibleExtensions(): array
    {
        // Build list of possible extensions from supported types constants
        $possibleExtensions = array_merge(
            array_values($this->validator->getSupportedImageTypes()), // All image formats
            array_values($this->validator->getSupportedPdfTypes()), // All PDF formats
            array_values($this->validator->getSupportedDocumentTypes()), // All document formats
            array_values($this->validator->getSupportedCadTypes()), // All CAD formats
            array_values($this->validator->getSupportedArchiveTypes()) // All archive formats
        );
        // Remove duplicates (e.g., 'jpg' and 'jpeg' both map to 'jpg')
        return array_unique($possibleExtensions);
    }


    /**
     * Filter extensions to only those matching allowed file types (performance optimization)
     *
     * @param array<string> $allExtensions All possible extensions
     * @param array<string> $allowedTypes Allowed file type categories
     * @return array<string> Filtered extensions that match allowed types
     */
    public function filterExtensionsByAllowedTypes(array $allExtensions, array $allowedTypes): array
    {
        $allowedExtensions = [];

        // Get all extensions from allowed type categories
        foreach ($allowedTypes as $allowedType) {
            // Check if it's a category constant
            $categoryExtensions = $this->getExtensionsForCategory($allowedType);
            if (!empty($categoryExtensions)) {
                $allowedExtensions = array_merge($allowedExtensions, $categoryExtensions);
            } else {
                // It's a specific extension
                $allowedExtensions[] = strtolower($allowedType);
            }
        }

        $allowedExtensions = array_unique($allowedExtensions);

        // Filter to only extensions that are both possible and allowed
        return array_values(array_intersect($allExtensions, $allowedExtensions));
    }


    /**
     * Get all extensions for a specific file type category
     *
     * @param string $category The file type category constant
     * @return array<string> Array of extensions for the category
     */
    private function getExtensionsForCategory(string $category): array
    {
        return match ($category) {
            FileTypeEnum::IMAGE->value   => array_values($this->validator->getSupportedImageTypes()),
            FileTypeEnum::PDF->value     => array_values($this->validator->getSupportedPdfTypes()),
            FileTypeEnum::CAD->value     => array_values($this->validator->getSupportedCadTypes()),
            FileTypeEnum::DOC->value     => array_values($this->validator->getSupportedDocumentTypes()),
            FileTypeEnum::ARCHIVE->value => array_values($this->validator->getSupportedArchiveTypes()),
            default => []
        };
    }
}
