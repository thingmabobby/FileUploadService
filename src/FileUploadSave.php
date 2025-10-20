<?php

declare(strict_types=1);

namespace FileUploadService;

use FileUploadService\FileUploadError;
use FileUploadService\FileServiceValidator;
use FileUploadService\DTO\FileUploadDTO;
use FileUploadService\DTO\DataUriDTO;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\UploadErrorCodeEnum;
use RuntimeException;

/**
 * Service for handling file saving operations
 * Handles the actual file system operations for uploading files from $_FILES and base64 data URIs
 * 
 * @package FileUploadService
 */
class FileUploadSave
{
    /**
     * Array of temp files to clean up
     * 
     * @var array<string>
     */
    private array $tempFilesToCleanup = [];

    /**
     * Constructor
     *
     * @param FileServiceValidator $validator File validator instance
     * @param FileSaverInterface $fileSaver File saver implementation
     * @param bool $convertHeicToJpg Whether to convert HEIC/HEIF files to JPEG
     */
    public function __construct(
        private readonly FileServiceValidator $validator,
        private readonly FileSaverInterface $fileSaver,
        private readonly bool $convertHeicToJpg = true
    ) {}

    /**
     * Destructor - clean up any remaining temp files
     */
    public function __destruct()
    {
        $this->cleanupTempFiles();
    }


    /**
     * Process a single file upload using FileUploadDTO
     *
     * @param FileUploadDTO $fileDTO The file upload data transfer object
     * @param string $uploadDestination The upload destination (directory, bucket/key prefix, etc.)
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @param array<FileTypeEnum|string> $allowedFileTypes Array of allowed file types
     * @return array{success: bool, filePath?: string, error?: FileUploadError}
     */
    public function processFileUpload(
        FileUploadDTO $fileDTO,
        string $uploadDestination,
        bool $overwriteExisting = false,
        array $allowedFileTypes = []
    ): array {
        // Check for upload errors
        if (!$fileDTO->isUploadSuccessful()) {
            $errorCode = UploadErrorCodeEnum::fromInt($fileDTO->uploadError);
            $errorMessage = $errorCode ? $errorCode->getMessage() : "Unknown upload error (code: {$fileDTO->uploadError})";

            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, $errorMessage, (string)$fileDTO->uploadError)
            ];
        }

        // Check file type allowance first
        $isAllowed = $this->validator->isFileTypeAllowed($fileDTO->extension, $fileDTO->tmpPath, $allowedFileTypes);
        if (!$isAllowed) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, "File type not allowed: {$fileDTO->extension}")
            ];
        }

        // Validate uploaded file for basic properties (existence, readability, etc.)
        if (!$this->validator->validateUploadedFile($fileDTO->tmpPath, $fileDTO->originalName)) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, "Invalid or corrupted file")
            ];
        }

        // Handle HEIC/HEIF conversion in the temp directory if needed
        $processedFilePath = $this->handleHeicConversion($fileDTO->tmpPath, $fileDTO->extension, $fileDTO->filename);

        // Update filename if it was converted (HEIC -> JPG)
        $finalFilename = $fileDTO->filename;
        if ($processedFilePath !== $fileDTO->tmpPath) {
            // File was converted, update the filename to .jpg
            $finalFilename = pathinfo($fileDTO->filename, PATHINFO_FILENAME) . '.jpg';
        }

        // Move processed file using the file saver
        // Let each implementation handle path resolution appropriately
        $targetPath = $this->fileSaver->resolveTargetPath($uploadDestination, $finalFilename);

        try {
            $savedPath = $this->fileSaver->saveFile($processedFilePath, $targetPath, $overwriteExisting);

            // Track temp files for cleanup in destructor
            $this->trackTempFileForCleanup($fileDTO->tmpPath);
            if ($processedFilePath !== $fileDTO->tmpPath) {
                $this->trackTempFileForCleanup($processedFilePath);
            }

            return [
                'success' => true,
                'filePath' => $savedPath
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, "Failed to save file: " . $e->getMessage())
            ];
        }
    }


    /**
     * Process a single base64 data URI input using DataUriDTO
     *
     * @param DataUriDTO $dataUriDTO The data URI data transfer object
     * @param string $uploadDestination The upload destination (directory, bucket/key prefix, etc.)
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @param array<FileTypeEnum|string> $allowedFileTypes Array of allowed file types
     * @return array{success: bool, filePath?: string, error?: FileUploadError}
     */
    public function processBase64Input(
        DataUriDTO $dataUriDTO,
        string $uploadDestination,
        bool $overwriteExisting = false,
        array $allowedFileTypes = []
    ): array {
        if (!$dataUriDTO->dataUri || trim($dataUriDTO->dataUri) === '') {
            return [
                'success' => false,
                'error' => new FileUploadError($dataUriDTO->filename, "Empty or invalid data URI")
            ];
        }

        // Validate data URI before processing
        if (!$this->validator->validateBase64DataUri($dataUriDTO->dataUri)) {
            return [
                'success' => false,
                'error' => new FileUploadError($dataUriDTO->filename, "Invalid data URI format")
            ];
        }

        try {
            // Create a temporary file for the data URI content (same flow as $_FILES)
            $tempFilePath = $this->createTempFileFromDataUri($dataUriDTO->dataUri);

            // Track temp file for cleanup in destructor
            $this->trackTempFileForCleanup($tempFilePath);

            // Create a FileUploadDTO with the temp path for processing
            $fileUploadDTO = new FileUploadDTO(
                filename: $dataUriDTO->filename,
                originalName: $dataUriDTO->filename, // Use filename as original name for data URIs
                tmpPath: $tempFilePath,
                extension: $dataUriDTO->extension,
                mimeType: $dataUriDTO->mimeType,
                fileTypeCategory: $dataUriDTO->fileTypeCategory,
                size: $dataUriDTO->size,
                uploadError: 0 // Success
            );

            // Use the same processing flow as file uploads
            $result = $this->processFileUpload($fileUploadDTO, $uploadDestination, $overwriteExisting, $allowedFileTypes);

            return $result;
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error' => new FileUploadError($dataUriDTO->filename, $e->getMessage())
            ];
        }
    }


    /**
     * Check if HEIC conversion is available
     *
     * @return bool True if HEIC conversion library is available, false otherwise
     */
    public function isHeicConversionAvailable(): bool
    {
        return class_exists('Maestroerror\HeicToJpg');
    }


    /**
     * Create a temporary file from data URI content
     *
     * @param string $dataUri The data URI
     * @return string Path to the temporary file
     * @throws RuntimeException If temp file creation fails
     */
    private function createTempFileFromDataUri(string $dataUri): string
    {
        // Extract file content from data URI
        $fileContent = $this->extractDataFromUri($dataUri);

        // Create temporary file using tempnam() for persistence
        $tempPath = tempnam(sys_get_temp_dir(), 'file_upload_');
        if ($tempPath === false) {
            throw new RuntimeException("Failed to create temporary file");
        }

        // Write content to temp file
        if (file_put_contents($tempPath, $fileContent) === false) {
            unlink($tempPath);
            throw new RuntimeException("Failed to write data URI content to temporary file");
        }

        return $tempPath;
    }


    /**
     * Handle HEIC/HEIF conversion
     *
     * @param string $filePath Path to the uploaded file
     * @param string $extension File extension
     * @param string $filename Original filename
     * @return string Path to the file (converted if HEIC/HEIF)
     */
    private function handleHeicConversion(string $filePath, string $extension, string $filename): string
    {
        // If not a HEIC/HEIF file, return the original path
        if (!in_array($extension, ['heic', 'heif'])) {
            return $filePath;
        }

        // If HEIC conversion is disabled, return the original path
        if (!$this->convertHeicToJpg) {
            return $filePath;
        }

        // Attempt to convert HEIC/HEIF to JPEG
        try {
            $jpgFilePath = $this->convertHeicToJpg($filePath, $filename);

            // Remove the original HEIC file after successful conversion
            $this->fileSaver->deleteFile($filePath);

            return $jpgFilePath;
        } catch (RuntimeException $e) {
            // Conversion failed - clean up the uploaded HEIC file
            $this->fileSaver->deleteFile($filePath);

            throw new RuntimeException("Failed to convert HEIC to JPEG: " . $e->getMessage());
        }
    }


    /**
     * Convert HEIC/HEIF file to JPEG
     *
     * @param string $heicFilePath Path to the HEIC/HEIF file
     * @param string $originalFilename Original filename (without extension)
     * @return string Path to the converted JPEG file
     * @throws RuntimeException If conversion fails
     */
    private function convertHeicToJpg(string $heicFilePath, string $originalFilename): string
    {
        // Create a temporary directory for conversion if it doesn't exist
        $tempDir = sys_get_temp_dir() . '/heic_conversion_' . uniqid();
        if (!is_dir($tempDir)) {
            // Use secure permissions (owner read/write/execute only)
            if (!mkdir($tempDir, 0700, true)) {
                throw new RuntimeException("Failed to create temporary directory for HEIC conversion");
            }
        }

        // Check if HEIC conversion is available
        if (!$this->isHeicConversionAvailable()) {
            // Graceful degradation: save HEIC file as-is if conversion library is not available
            $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);
            $heicFilename = $baseFilename . '.heic';

            // Copy the original HEIC file to the destination
            $fileContent = file_get_contents($heicFilePath);
            if ($fileContent !== false) {
                $this->fileSaver->saveFile($fileContent, $heicFilename, true);
            }

            return $heicFilename;
        }

        try {
            // Use the HeicToJpg library to convert
            $converter = new \Maestroerror\HeicToJpg();
            $convertedImageData = $converter->convertImage($heicFilePath)->get();

            // Generate unique filename for the converted JPEG
            $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);
            $jpgFilename = $baseFilename . '.jpg';

            // Save the converted image using the file saver
            $this->fileSaver->saveFile($convertedImageData, $jpgFilename, true);

            // Clean up temporary directory
            $this->cleanupTempDirectory($tempDir);

            return $jpgFilename;
        } catch (RuntimeException) {
            // Clean up temporary directory on failure
            $this->cleanupTempDirectory($tempDir);

            // If conversion fails, gracefully degrade to saving the original HEIC file
            $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);
            $heicFilename = $baseFilename . '.heic';

            // Copy the original HEIC file to the destination
            $fileContent = file_get_contents($heicFilePath);
            if ($fileContent !== false) {
                $this->fileSaver->saveFile($fileContent, $heicFilename, true);
            }

            return $heicFilename;
        } finally {
            // Clean up temporary directory
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
        }
    }


    /**
     * Recursively remove a directory and its contents
     *
     * @param string $dir Directory path
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }


    /**
     * Clean up temporary directory and its contents
     *
     * @param string $tempDir Path to temporary directory
     */
    private function cleanupTempDirectory(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }

        // Remove all files in the directory
        $files = glob($tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // Remove the directory itself
        rmdir($tempDir);
    }


    /**
     * Extract base64 data from data URI
     *
     * @param string $dataUri The data URI
     * @return string The extracted data
     * @throws RuntimeException If extraction fails
     */
    private function extractDataFromUri(string $dataUri): string
    {
        if (!preg_match('/^data:([^;]+);base64,(.+)$/', $dataUri, $matches)) {
            throw new RuntimeException("Invalid data URI format");
        }

        $data = base64_decode($matches[2], true);
        if ($data === false) {
            throw new RuntimeException("Failed to decode base64 data");
        }

        return $data;
    }


    /**
     * Track a temp file for cleanup in destructor
     *
     * @param string $filePath Path to the temp file
     */
    private function trackTempFileForCleanup(string $filePath): void
    {
        if (file_exists($filePath)) {
            $this->tempFilesToCleanup[] = $filePath;
        }
    }

    /**
     * Clean up all tracked temp files
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFilesToCleanup as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $this->tempFilesToCleanup = [];
    }

    /**
     * Get the file saver instance
     *
     * @return FileSaverInterface The file saver instance
     */
    public function getFileSaver(): FileSaverInterface
    {
        return $this->fileSaver;
    }
}
