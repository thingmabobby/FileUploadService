<?php

declare(strict_types=1);

namespace FileUploadService;

use RuntimeException;

/**
 * Example cloud storage implementation of FileSaverInterface
 * This demonstrates how different implementations can handle path resolution differently
 * 
 * @package FileUploadService
 */
class CloudStorageSaver implements FileSaverInterface
{
    public function __construct(
        private readonly string $bucketName,
        private readonly string $region = 'us-east-1'
    ) {
        // Region is stored for potential future use in cloud provider API calls
        // Currently not used in this demo implementation but would be needed for real cloud storage
        // In a real implementation, this would be used to configure the cloud client
    }

    /**
     * Get the configured region for this cloud storage instance
     * 
     * @return string The region identifier
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Ensure the upload destination exists and is accessible
     * For cloud storage, this validates bucket access and permissions
     *
     * @param string $uploadDestination Upload destination to validate
     * @throws RuntimeException If destination cannot be accessed
     */
    public function ensureUploadDestinationExists(string $uploadDestination): void
    {
        // For cloud storage, we would validate:
        // 1. Bucket exists and is accessible
        // 2. User has write permissions
        // 3. Destination path/key prefix is valid

        // This is a demo implementation - real implementation would:
        // - Check if bucket exists using cloud provider API
        // - Validate user permissions
        // - Check if destination path is valid

        if (empty($uploadDestination)) {
            throw new RuntimeException("Upload destination cannot be empty for cloud storage");
        }

        // Demo: Just validate the destination format
        if (str_contains($uploadDestination, '..') || str_contains($uploadDestination, '//')) {
            throw new RuntimeException("Invalid upload destination format: {$uploadDestination}");
        }

        // In a real implementation, you might do:
        // $this->cloudClient->validateBucketAccess($this->bucketName);
        // $this->cloudClient->validateWritePermissions($this->bucketName, $uploadDestination);
    }

    /**
     * Resolve the target path for cloud storage
     * For cloud storage, this creates a bucket/key path
     *
     * @param string $uploadDestination Upload destination/prefix
     * @param string $filename Target filename
     * @return string The resolved bucket/key path
     */
    public function resolveTargetPath(string $uploadDestination, string $filename): string
    {
        // For cloud storage, we might use bucket/key format
        $key = empty($uploadDestination) ? $filename : $uploadDestination . '/' . $filename;

        // Return bucket/key format for cloud storage
        return "{$this->bucketName}/{$key}";
    }

    public function saveFile(string $source, string $targetPath, bool $overwriteExisting = false): string
    {
        // Detect if source is file content or file path
        if (file_exists($source) && is_file($source)) {
            // Source is a file path - upload the file
            // Implementation would read file and upload to cloud storage
            return $targetPath;
        } else {
            // Source is file content - upload directly
            // Implementation would upload content to cloud storage
            return $targetPath;
        }
    }

    public function fileExists(string $targetPath): bool
    {
        // Implementation would check if file exists in cloud storage
        return false; // Demo implementation
    }

    public function deleteFile(string $targetPath): bool
    {
        // Implementation would delete file from cloud storage
        return true; // Demo implementation
    }

    public function getBasePath(): string
    {
        return $this->bucketName;
    }
}
