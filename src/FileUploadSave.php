<?php

declare(strict_types=1);

namespace FileUploadService;

use FileUploadService\FileUploadError;
use FileUploadService\FileServiceValidator;
use FileUploadService\DTO\FileDTO;
use FileUploadService\Enum\UploadErrorCodeEnum;
use FileUploadService\Enum\FileTypeEnum;
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
     * Constructor
     *
     * @param FileServiceValidator $validator File validator instance
     * @param FileSaverInterface $fileSaver File saver implementation
     * @param bool $convertHeicToJpg Whether to convert HEIC/HEIF files to JPEG
     */
    public function __construct(
        private FileServiceValidator $validator,
        private FileSaverInterface $fileSaver,
        private readonly bool $convertHeicToJpg = true
    ) {}


    /**
     * Process a single file upload using FileDTO
     *
     * @param FileDTO $fileDTO The file data transfer object
     * @param string $uploadDir The upload directory
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @param array $allowedFileTypes Array of allowed file types
     * @return array{success: bool, filePath?: string, error?: FileUploadError}
     */
    public function processFileUpload(
        FileDTO $fileDTO,
        string $uploadDir,
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

        // Validate uploaded file before processing
        if (!$this->validator->validateUploadedFile($fileDTO->tmpPath, $fileDTO->originalName)) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, "Invalid or corrupted file")
            ];
        }

        // Check if file type is allowed by extension
        if (!$this->isFileTypeAllowedByExtension($fileDTO->extension, $allowedFileTypes)) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, "File type not allowed: {$fileDTO->extension}")
            ];
        }

        // Move uploaded file using the file saver
        // Construct the relative path within the upload directory
        $relativePath = ltrim($uploadDir, '/') . '/' . $fileDTO->filename;

        try {
            $savedPath = $this->fileSaver->moveUploadedFile($fileDTO->tmpPath, $relativePath, $overwriteExisting);

            // Handle HEIC/HEIF conversion if needed
            $processedFilePath = $this->handleHeicConversion(
                filePath: $savedPath,
                extension: $fileDTO->extension,
                filename: $fileDTO->filename
            );

            if ($processedFilePath === null) {
                return [
                    'success' => false,
                    'error' => new FileUploadError($fileDTO->filename, "HEIC conversion failed")
                ];
            }

            return [
                'success' => true,
                'filePath' => $processedFilePath
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, $e->getMessage())
            ];
        }
    }


    /**
     * Process a single base64 data URI input using FileDTO
     *
     * @param FileDTO $fileDTO The file data transfer object
     * @param string $uploadDir The upload directory
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @param array $allowedFileTypes Array of allowed file types
     * @return array{success: bool, filePath?: string, error?: FileUploadError}
     */
    public function processBase64Input(
        FileDTO $fileDTO,
        string $uploadDir,
        bool $overwriteExisting = false,
        array $allowedFileTypes = []
    ): array {
        if (!$fileDTO->isDataUri() || !$fileDTO->dataUri || trim($fileDTO->dataUri) === '') {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, "Empty or invalid data URI")
            ];
        }

        // Validate data URI before processing
        if (!$this->validator->validateBase64DataUri($fileDTO->dataUri)) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, "Invalid data URI format")
            ];
        }

        try {
            $filePath = $this->saveDataUriToFile(
                dataUri: $fileDTO->dataUri,
                uploadDir: $uploadDir,
                filename: $fileDTO->filename,
                overwriteExisting: $overwriteExisting,
                allowedFileTypes: $allowedFileTypes
            );

            return [
                'success' => true,
                'filePath' => $filePath
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error' => new FileUploadError($fileDTO->filename, $e->getMessage())
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
        return class_exists('Maestroerror\HeicToJpg') && method_exists('Maestroerror\HeicToJpg', 'convertImage');
    }


    /**
     * Handle HEIC/HEIF file detection and conversion to JPEG
     *
     * @param string $filePath Path to the uploaded file
     * @param string $extension File extension
     * @param string $filename Original filename
     * @return string|null Path to the file (converted if HEIC/HEIF), or null if conversion failed
     */
    private function handleHeicConversion(string $filePath, string $extension, string $filename): ?string
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
            if (!mkdir($tempDir, 0777, true)) {
                throw new RuntimeException("Failed to create temporary directory for HEIC conversion");
            }
        }

        // Check if HEIC conversion is available
        if (!$this->isHeicConversionAvailable()) {
            // Graceful degradation: save HEIC file as-is if conversion library is not available
            $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);
            $heicFilename = $baseFilename . '.heic';

            // Copy the original HEIC file to the destination
            $this->fileSaver->saveFile(file_get_contents($heicFilePath), $heicFilename, true);

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

            return $jpgFilename;
        } catch (RuntimeException) {
            // If conversion fails, gracefully degrade to saving the original HEIC file
            $baseFilename = pathinfo($originalFilename, PATHINFO_FILENAME);
            $heicFilename = $baseFilename . '.heic';

            // Copy the original HEIC file to the destination
            $this->fileSaver->saveFile(file_get_contents($heicFilePath), $heicFilename, true);

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
     * Check if file type is allowed by extension
     *
     * @param string $extension File extension
     * @param array $allowedFileTypes Array of allowed file types
     * @return bool True if allowed, false otherwise
     */
    private function isFileTypeAllowedByExtension(string $extension, array $allowedFileTypes): bool
    {
        // If no restrictions, allow all
        if (empty($allowedFileTypes)) {
            return true;
        }

        // Check if specific extension is allowed
        foreach ($allowedFileTypes as $allowedType) {
            if (strtolower($allowedType) === strtolower($extension)) {
                return true;
            }
        }

        // Check if file type category is allowed
        $fileTypeCategory = $this->validator->getFileTypeCategoryFromExtension($extension);

        return $fileTypeCategory !== null && in_array($fileTypeCategory->value, $allowedFileTypes, true);
    }


    /**
     * Save data URI to file with proper handling based on file type
     *
     * @param string $dataUri The data URI
     * @param string $uploadDir The upload directory
     * @param string $filename The target filename
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @param array $allowedFileTypes Array of allowed file types
     * @return string Path to the saved file
     * @throws RuntimeException If saving fails
     */
    private function saveDataUriToFile(
        string $dataUri,
        string $uploadDir,
        string $filename,
        bool $overwriteExisting,
        array $allowedFileTypes
    ): string {
        // Determine file extension based on data URI type, append only if filename lacks an extension
        $extension = $this->validator->getFileExtension($dataUri);
        $existingExt = pathinfo($filename, PATHINFO_EXTENSION);
        if ($extension && $existingExt === '') {
            $filename = rtrim($filename, '.') . '.' . $extension;
        }

        // Construct the relative path within the upload directory
        $relativePath = ltrim($uploadDir, '/') . '/' . $filename;

        // Check if file type is allowed based on restriction mode
        if (!$this->isFileTypeAllowed($dataUri, $allowedFileTypes)) {
            $allowedTypes = implode(', ', $allowedFileTypes ?: ['all']);
            throw new RuntimeException("File type not allowed with current restrictions. Allowed types: {$allowedTypes}");
        }

        // Determine file type and save accordingly
        $fileTypeCategory = $this->validator->getFileTypeCategoryFromDataUri($dataUri);

        // Handle special case for images (need processing for different formats)
        if ($fileTypeCategory === FileTypeEnum::IMAGE->value || $fileTypeCategory === FileTypeEnum::IMAGE) {
            return $this->saveImageFile($dataUri, $relativePath, $overwriteExisting);
        }

        // For all other file types, use the generic save method
        if ($fileTypeCategory === 'unknown' && !$this->isUnrestricted($allowedFileTypes)) {
            throw new RuntimeException("Unrecognized data URI format");
        }

        return $this->saveFileFromDataUri($dataUri, $relativePath, $overwriteExisting);
    }


    /**
     * Check if file type is allowed based on current restriction mode
     *
     * @param string $dataUri The data URI to check
     * @param array $allowedFileTypes Array of allowed file types
     * @return bool True if the file type is allowed, false otherwise
     */
    private function isFileTypeAllowed(string $dataUri, array $allowedFileTypes): bool
    {
        // If FILE_TYPE_ALL is allowed, accept any file type
        if (in_array(FileTypeEnum::ALL->value, $allowedFileTypes, true)) {
            return true;
        }

        // Check if specific file extension is allowed
        $extension = $this->validator->getFileExtension($dataUri);
        if ($extension) {
            foreach ($allowedFileTypes as $allowedType) {
                if (strtolower($allowedType) === strtolower($extension)) {
                    return true;
                }
            }
        }

        // Check if file type category is allowed
        $fileTypeCategory = $this->validator->getFileTypeCategoryFromDataUri($dataUri);

        return $fileTypeCategory !== null && in_array($fileTypeCategory->value, $allowedFileTypes, true);
    }


    /**
     * Check if the service is in unrestricted mode
     *
     * @param array $allowedFileTypes Array of allowed file types
     * @return bool True if unrestricted, false otherwise
     */
    private function isUnrestricted(array $allowedFileTypes): bool
    {
        return in_array(FileTypeEnum::ALL->value, $allowedFileTypes, true);
    }


    /**
     * Save image file from data URI
     *
     * @param string $dataUri The data URI
     * @param string $relativePath The relative target path
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @return string Path to the saved file
     * @throws RuntimeException If saving fails
     */
    private function saveImageFile(string $dataUri, string $relativePath, bool $overwriteExisting): string
    {
        $imageData = $this->extractDataFromUri($dataUri);
        $image = imagecreatefromstring($imageData);

        if ($image === false) {
            throw new RuntimeException("Failed to create image from data URI");
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        // Create a temporary file to save the image data
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        $success = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $tempPath, 90),
            'png'         => imagepng($image, $tempPath, 9),
            'gif'         => imagegif($image, $tempPath),
            'webp'        => imagewebp($image, $tempPath, 90),
            'bmp'         => imagebmp($image, $tempPath),
            default => throw new RuntimeException("Unsupported image format: {$extension}"),
        };

        imagedestroy($image);

        if (!$success) {
            fclose($tempFile);
            throw new RuntimeException("Failed to create temporary image file");
        }

        // Read the temporary file content and save using FileSaverInterface
        $imageContent = file_get_contents($tempPath);
        fclose($tempFile);

        if ($imageContent === false) {
            throw new RuntimeException("Failed to read temporary image file");
        }

        $this->fileSaver->saveFile($imageContent, $relativePath, $overwriteExisting);

        return $relativePath;
    }


    /**
     * Save file from data URI (generic method for all non-image file types)
     *
     * @param string $dataUri The data URI
     * @param string $relativePath The relative target path
     * @param bool $overwriteExisting Whether to overwrite existing files
     * @return string Path to the saved file
     * @throws RuntimeException If saving fails
     */
    private function saveFileFromDataUri(string $dataUri, string $relativePath, bool $overwriteExisting): string
    {
        $fileData = $this->extractDataFromUri($dataUri);
        $this->fileSaver->saveFile($fileData, $relativePath, $overwriteExisting);

        return $relativePath;
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
}
