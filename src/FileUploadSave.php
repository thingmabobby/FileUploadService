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
use Exception;

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

        /** @var string $processedFilePath */
        $processedFilePath = $fileDTO->tmpPath;
        
        /** @var string $finalFilename */
        $finalFilename = $fileDTO->filename;

        // Optionally convert HEIC/HEIF in tmp and derive final filename accordingly
        if ($this->convertHeicToJpg && $this->isHeicContent($fileDTO->tmpPath, $fileDTO->mimeType)) {
            $conversion = $this->handleHeicConversion($fileDTO->tmpPath, $fileDTO->filename);
            $processedFilePath = $conversion['path'];
            $finalFilename = $conversion['filename'];
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
     * @param string $originalFilename Original filename (from FileUploadDTO)
     * @return array{path: string, filename: string} Path to the converted JPEG file (temporary) and its final filename
     */
    private function handleHeicConversion(string $filePath, string $originalFilename): array
    {
        // Detect HEIC/HEIF by content
        if (!$this->isHeicContent($filePath)) {
            return ['path' => $filePath, 'filename' => $originalFilename];
        }

        // If HEIC conversion is disabled, return the original path
        if (!$this->convertHeicToJpg) {
            return ['path' => $filePath, 'filename' => $originalFilename];
        }

        // Attempt to convert HEIC/HEIF to JPEG
        try {
            $jpgFilePath = $this->convertHeicToJpg($filePath);

            // swap extension to .jpg for final filename
            $finalName = pathinfo($originalFilename, PATHINFO_FILENAME) . '.jpg';

            return ['path' => $jpgFilePath, 'filename' => $finalName];
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to convert HEIC to JPEG: " . $e->getMessage());
        } finally {
            // Clean up the uploaded HEIC temp file directly
            unlink($filePath);
        }
    }


    /**
     * Best-effort HEIC content detection using MIME (finfo) and header brand check
     */
    private function isHeicContent(string $filePath, ?string $mime = null): bool
    {
        // 1) Trust provided MIME (from DTO) if present
        if ($mime && ($mime === 'image/heic' || $mime === 'image/heif')) {
            return true;
        }

        // 2) Fallback to finfo()
        if (function_exists('finfo_open')) {
            $f = finfo_open(\FILEINFO_MIME_TYPE);
            if ($f) {
                $detected = finfo_file($f, $filePath) ?: '';
                finfo_close($f);
                if ($detected === 'image/heic' || $detected === 'image/heif') {
                    return true;
                }
            }
        }

        // 3) Last resort: brand header check
        $h = @fopen($filePath, 'rb');
        if ($h) {
            $head = fread($h, 64) ?: '';
            fclose($h);
            $l = strtolower($head);
            if (str_contains($l, 'ftypheic') || str_contains($l, 'ftypheif') || str_contains($l, 'ftypmif1')) {
                return true;
            }
        }
        return false;
    }


    /**
     * Convert HEIC/HEIF file to JPEG
     *
     * @param string $heicFilePath Path to the HEIC/HEIF file
     * @return string Path to the converted JPEG file (temporary)
     * @throws RuntimeException If conversion fails
     */
    private function convertHeicToJpg(string $heicFilePath): string
    {
        // Check if HEIC conversion is available
        if (!$this->isHeicConversionAvailable()) {
            // Conversion library not available - throw exception
            throw new RuntimeException("HEIC conversion library (maestroerror/php-heic-to-jpg) is not installed");
        }

        try {
            // Use the HeicToJpg library to convert
            $converter = new \Maestroerror\HeicToJpg();
            $convertedImageData = $converter->convertImage($heicFilePath)->get();

            // Validate that we got image data (raw JPEG binary)
            if (empty($convertedImageData)) {
                throw new RuntimeException("HEIC conversion returned empty data");
            }

            // Write directly to a temp file under system tmp
            $jpgTempPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'heic_' . uniqid('', true) . '.jpg';
            if (file_put_contents($jpgTempPath, $convertedImageData, LOCK_EX) === false) {
                throw new RuntimeException("Failed to write converted JPEG to temporary file");
            }

            return $jpgTempPath;
        } catch (Exception $e) {
            // Re-throw with more context
            throw new RuntimeException("HEIC to JPG conversion failed: " . $e->getMessage(), 0, $e);
        }
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
