<?php

declare(strict_types=1);

namespace FileUploadService;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\SupportedFileTypesEnum;

/**
 * Validator class for file upload operations
 * Handles validation of file types, data URIs, and uploaded files
 * 
 * @package FileUploadService
 */
class FileServiceValidator
{
    public function __construct(private readonly int $maxFileSize = 100 * 1024 * 1024) {}


    /**
     * Validate base64 data URI for corruption
     *
     * @param string $dataUri The data URI to validate
     * @return bool True if valid, false if corrupted
     */
    public function validateBase64DataUri(string $dataUri): bool
    {
        // Check if it's a valid data URI format
        if (!preg_match('#^data:[^;]+;base64,#', $dataUri)) {
            return false;
        }

        // Extract base64 data
        $base64Data = preg_replace('#^data:[^;]+;base64,#', '', $dataUri);

        // Check if base64 data is valid
        if (empty($base64Data)) {
            return false;
        }

        // Check if base64 string contains only valid characters
        if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $base64Data)) {
            return false;
        }

        // Try to decode and check if it produces valid binary data
        $decoded = base64_decode($base64Data, true);
        if ($decoded === false) {
            return false;
        }

        // Check if decoded data has reasonable length (not empty, not extremely long)
        $length = strlen($decoded);
        if ($length === 0 || $length > $this->maxFileSize) {
            return false;
        }

        return true;
    }


    /**
     * Validate uploaded file for corruption and basic properties
     *
     * @param string $tmpPath Temporary file path
     * @param string $originalName Original filename
     * @return bool True if valid, false if corrupted
     */
    public function validateUploadedFile(string $tmpPath, string $originalName): bool
    {
        // Basic file checks
        if (!$this->validateBasicFileProperties($tmpPath)) {
            return false;
        }

        // Get file extension
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Type-specific validation
        // Validate based on file type
        if (array_key_exists($extension, $this->getSupportedImageTypes())) {
            return $this->validateImageFile($tmpPath, $extension);
        }

        if ($extension === 'pdf') {
            return $this->validatePdfFile($tmpPath);
        }

        // Validate video files
        if (array_key_exists($extension, $this->getSupportedVideoTypes())) {
            return $this->validateVideoFile($tmpPath, $extension);
        }

        // For other file types, basic validation is sufficient
        return true;
    }


    /**
     * Validate basic file properties (existence, readability, size, type)
     *
     * @param string $tmpPath Temporary file path
     * @return bool True if valid, false otherwise
     */
    public function validateBasicFileProperties(string $tmpPath): bool
    {
        // Check if temporary file exists and is readable
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            return false;
        }

        // Check file size (not empty, not extremely large)
        $fileSize = filesize($tmpPath);
        if ($fileSize === false || $fileSize === 0 || $fileSize > 100 * 1024 * 1024) { // 100MB max
            return false;
        }

        // Check if file is actually a file (not directory, symlink, etc.)
        if (!is_file($tmpPath)) {
            return false;
        }

        return true;
    }


    /**
     * Validate image file based on format
     *
     * @param string $tmpPath Temporary file path
     * @param string $extension File extension (lowercase)
     * @return bool True if valid image, false otherwise
     */
    public function validateImageFile(string $tmpPath, string $extension): bool
    {
        // Special validation for formats that getimagesize() may not support
        if ($extension === 'jxl') {
            return $this->validateJxlFile($tmpPath);
        }

        if (in_array($extension, ['heic', 'heif'], true)) {
            return $this->validateHeicFile($tmpPath);
        }

        // For standard image formats, use getimagesize()
        $imageInfo = getimagesize($tmpPath);
        return $imageInfo !== false;
    }


    /**
     * Validate JPEG XL file by checking magic bytes
     *
     * @param string $tmpPath Temporary file path
     * @return bool True if valid JPEG XL file, false otherwise
     */
    public function validateJxlFile(string $tmpPath): bool
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        if ($header === false) {
            return false;
        }

        // JPEG XL files start with specific magic bytes
        if (strlen($header) < 2) {
            return false;
        }

        // Naked JPEG XL format: FF 0A
        $isJxlNaked = (ord($header[0]) === 0xFF && ord($header[1]) === 0x0A);

        // Container JPEG XL format: 00 00 00 0C 4A 58 4C 20 (starts with "JXL ")
        $isJxlContainer = (strlen($header) >= 12 &&
            ord($header[0]) === 0x00 && ord($header[1]) === 0x00 &&
            ord($header[2]) === 0x00 && ord($header[3]) === 0x0C &&
            substr($header, 4, 4) === 'JXL ');

        return $isJxlNaked || $isJxlContainer;
    }


    /**
     * Validate HEIC/HEIF file by checking file signature
     *
     * @param string $tmpPath Temporary file path
     * @return bool True if valid HEIC/HEIF file, false otherwise
     */
    public function validateHeicFile(string $tmpPath): bool
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        if ($header === false) {
            return false;
        }

        // HEIC/HEIF files have 'ftyp' at bytes 4-7
        return strpos($header, 'ftyp') !== false;
    }


    /**
     * Validate PDF file by checking file signature
     *
     * @param string $tmpPath Temporary file path
     * @return bool True if valid PDF file, false otherwise
     */
    public function validatePdfFile(string $tmpPath): bool
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        // PDF files start with '%PDF'
        return $header === '%PDF';
    }


    /**
     * Validate video file by checking file signature
     *
     * @param string $tmpPath Temporary file path
     * @param string $extension File extension (lowercase)
     * @return bool True if valid video file, false otherwise
     */
    public function validateVideoFile(string $tmpPath, string $extension): bool
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        if ($header === false) {
            return false;
        }

        if (strlen($header) < 4) {
            return false;
        }

        // Check for common video file signatures
        return match ($extension) {
            'mp4' => strpos($header, 'ftyp') !== false,
            'avi' => substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'AVI ',
            'mov' => strpos($header, 'ftyp') !== false,
            'wmv' => substr($header, 0, 4) === 'RIFF' && strpos($header, 'WAVE') !== false,
            'flv' => substr($header, 0, 3) === 'FLV',
            'webm' => substr($header, 0, 4) === "\x1a\x45\xdf\xa3",
            'mkv' => substr($header, 0, 4) === "\x1a\x45\xdf\xa3",
            'mpeg', 'mpg' => substr($header, 0, 4) === "\x00\x00\x01\xba",
            '3gp' => strpos($header, 'ftyp') !== false,
            'm4v' => strpos($header, 'ftyp') !== false,
            'ogv' => substr($header, 0, 4) === 'OggS',
            default => true // For unknown video formats, assume valid
        };
    }


    /**
     * Check if data URI is a PDF
     *
     * @param string $dataUri The data URI to check
     * @return bool True if the data URI is a PDF, false otherwise
     */
    public function isPdfDataUri(string $dataUri): bool
    {
        // Get PDF MIME types from enum
        $pdfTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::PDF);
        $pdfMimeTypes = array_map(fn($type) => $type->getMimeType(), $pdfTypes);

        foreach ($pdfMimeTypes as $mimeType) {
            if (strpos($dataUri, "data:{$mimeType};base64,") === 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * Check if data URI is an image
     *
     * @param string $dataUri The data URI to check
     * @return bool True if the data URI is an image, false otherwise
     */
    public function isImageDataUri(string $dataUri): bool
    {
        // Get image MIME types from enum
        $imageTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::IMAGE);
        $imageMimeTypes = array_map(fn($type) => str_replace('image/', '', $type->getMimeType()), $imageTypes);
        $imageTypesPattern = implode('|', $imageMimeTypes);
        return (bool)preg_match("#^data:image/({$imageTypesPattern});base64,#i", $dataUri);
    }


    /**
     * Check if data URI is a CAD or technical drawing file
     *
     * @param string $dataUri The data URI to check
     * @return bool True if the data URI is a CAD file, false otherwise
     */
    public function isCadDataUri(string $dataUri): bool
    {
        // Get CAD MIME types from enum
        $cadTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::CAD);
        $cadMimeTypes = array_map(fn($type) => $type->getMimeType(), $cadTypes);

        foreach ($cadMimeTypes as $mimeType) {
            if (strpos($dataUri, "data:{$mimeType};base64,") === 0) {
                return true;
            }
        }

        // Check for specific CAD file extensions in the filename
        $cadExtensions = array_keys($this->getSupportedCadTypes());
        foreach ($cadExtensions as $ext) {
            if (strpos($dataUri, "data:application/{$ext};base64,") === 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * Check if data URI is a document file
     *
     * @param string $dataUri The data URI to check
     * @return bool True if the data URI is a document file, false otherwise
     */
    public function isDocumentDataUri(string $dataUri): bool
    {
        // Get document MIME types from enum
        $documentTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::DOC);
        $documentMimeTypes = array_map(fn($type) => $type->getMimeType(), $documentTypes);

        foreach ($documentMimeTypes as $mimeType) {
            if (strpos($dataUri, "data:{$mimeType};base64,") === 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * Check if data URI is an archive file
     *
     * @param string $dataUri The data URI to check
     * @return bool True if the data URI is an archive file, false otherwise
     */
    public function isArchiveDataUri(string $dataUri): bool
    {
        // Get archive MIME types from enum
        $archiveTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::ARCHIVE);
        $archiveMimeTypes = array_map(fn($type) => $type->getMimeType(), $archiveTypes);

        foreach ($archiveMimeTypes as $mimeType) {
            if (strpos($dataUri, "data:{$mimeType};base64,") === 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * Get the file type category from a data URI
     *
     * @param string $dataUri The data URI to analyze
     * @return FileTypeEnum|null The file type enum or null for unrecognized types
     */
    public function getFileTypeCategoryFromDataUri(string $dataUri): ?FileTypeEnum
    {
        return match (true) {
            $this->isImageDataUri($dataUri)    => FileTypeEnum::IMAGE,
            $this->isPdfDataUri($dataUri)      => FileTypeEnum::PDF,
            $this->isCadDataUri($dataUri)      => FileTypeEnum::CAD,
            $this->isDocumentDataUri($dataUri) => FileTypeEnum::DOC,
            $this->isArchiveDataUri($dataUri)  => FileTypeEnum::ARCHIVE,
            default => null,
        };
    }


    /**
     * Get file extension based on data URI type
     *
     * @param string $dataUri The data URI to analyze
     * @return string|null The file extension (without dot) or null if unknown
     */
    public function getFileExtension(string $dataUri): ?string
    {
        // Check for PDF
        if ($this->isPdfDataUri($dataUri)) {
            // Extract MIME type from data URI
            if (preg_match('#^data:application/([^;]+);base64,#i', $dataUri, $matches)) {
                $mimeType = strtolower($matches[1]);
                return $this->getSupportedPdfTypes()[$mimeType] ?? 'pdf';
            }
            return 'pdf'; // fallback
        }

        // Check for images
        if ($this->isImageDataUri($dataUri)) {
            // Extract MIME type from data URI
            if (preg_match('#^data:image/([^;]+);base64,#i', $dataUri, $matches)) {
                $mimeType = strtolower($matches[1]);
                return $this->getSupportedImageTypes()[$mimeType] ?? 'jpg';
            }
        }

        // Check for CAD files
        if ($this->isCadDataUri($dataUri)) {
            // Extract MIME type from data URI
            if (preg_match('#^data:application/([^;]+);base64,#i', $dataUri, $matches)) {
                $mimeType = strtolower($matches[1]);
                return $this->getSupportedCadTypes()[$mimeType] ?? 'dwg';
            }
        }

        // Check for documents
        if ($this->isDocumentDataUri($dataUri)) {
            // Extract MIME type from data URI
            if (preg_match('#^data:application/([^;]+);base64,#i', $dataUri, $matches)) {
                $mimeType = strtolower($matches[1]);
                return $this->getSupportedDocumentTypes()[$mimeType] ?? 'doc';
            }
        }

        // Check for archives
        if ($this->isArchiveDataUri($dataUri)) {
            // Extract MIME type from data URI
            if (preg_match('#^data:application/([^;]+);base64,#i', $dataUri, $matches)) {
                $mimeType = strtolower($matches[1]);
                return $this->getSupportedArchiveTypes()[$mimeType] ?? 'zip';
            }
        }

        return null;
    }


    /**
     * Get supported image types
     *
     * @return array<string, string> Supported image types mapping (extension => standard extension)
     */
    public function getSupportedImageTypes(): array
    {
        $types = [];
        foreach (SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::IMAGE) as $type) {
            $extension = $type->getStandardExtension();
            $types[$extension] = $extension; // Extension to extension mapping
            $types[$type->getMimeType()] = $extension; // MIME type to extension mapping
        }
        return $types;
    }


    /**
     * Get supported document types
     *
     * @return array<string, string> Supported document types mapping (extension => standard extension)
     */
    public function getSupportedDocumentTypes(): array
    {
        $types = [];
        foreach (SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::DOC) as $type) {
            $types[$type->getStandardExtension()] = $type->getStandardExtension();
        }
        return $types;
    }


    /**
     * Get supported CAD types
     *
     * @return array<string, string> Supported CAD types mapping (extension => standard extension)
     */
    public function getSupportedCadTypes(): array
    {
        $types = [];
        foreach (SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::CAD) as $type) {
            $types[$type->getStandardExtension()] = $type->getStandardExtension();
        }
        return $types;
    }


    /**
     * Get supported archive types
     *
     * @return array<string, string> Supported archive types mapping (extension => standard extension)
     */
    public function getSupportedArchiveTypes(): array
    {
        $types = [];
        foreach (SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::ARCHIVE) as $type) {
            $types[$type->getStandardExtension()] = $type->getStandardExtension();
        }
        return $types;
    }


    /**
     * Get supported PDF types
     *
     * @return array<string, string> Supported PDF types mapping (extension => standard extension)
     */
    public function getSupportedPdfTypes(): array
    {
        $types = [];
        foreach (SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::PDF) as $type) {
            $types[$type->getStandardExtension()] = $type->getStandardExtension();
        }
        return $types;
    }


    /**
     * Get supported video types
     *
     * @return array<string, string> Supported video types mapping (extension => standard extension)
     */
    public function getSupportedVideoTypes(): array
    {
        $types = [];
        foreach (SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::VIDEO) as $type) {
            $types[$type->getStandardExtension()] = $type->getStandardExtension();
        }
        return $types;
    }


    /**
     * Get the file type category from a file extension
     *
     * @param string $extension The file extension (without dot)
     * @return FileTypeEnum|null The file type enum or null for unrecognized types
     */
    public function getFileTypeCategoryFromExtension(string $extension): ?FileTypeEnum
    {
        $extension = strtolower($extension);

        return match (true) {
            array_key_exists($extension, $this->getSupportedImageTypes())    => FileTypeEnum::IMAGE,
            array_key_exists($extension, $this->getSupportedVideoTypes())    => FileTypeEnum::VIDEO,
            array_key_exists($extension, $this->getSupportedPdfTypes())      => FileTypeEnum::PDF,
            array_key_exists($extension, $this->getSupportedDocumentTypes()) => FileTypeEnum::DOC,
            array_key_exists($extension, $this->getSupportedCadTypes())      => FileTypeEnum::CAD,
            array_key_exists($extension, $this->getSupportedArchiveTypes())  => FileTypeEnum::ARCHIVE,
            default => null,
        };
    }


    /**
     * Get file extension from MIME type
     *
     * @param string $mimeType The MIME type
     * @return string The file extension (empty string if not found)
     */
    public function getExtensionFromMimeType(string $mimeType): string
    {
        // Try to find exact MIME type match first
        $supportedType = SupportedFileTypesEnum::findByMimeType($mimeType);
        if ($supportedType) {
            return $supportedType->getStandardExtension();
        }

        // Fallback: check if MIME type contains any supported extension
        foreach (SupportedFileTypesEnum::cases() as $type) {
            $extension = $type->getExtension();
            if (str_contains($mimeType, $extension) || str_contains($mimeType, $type->getMimeType())) {
                return $type->getStandardExtension();
            }
        }

        return '';
    }


    /**
     * Check if file type is allowed using MIME-type focused validation
     * Handles both file uploads (with file path) and data URIs
     *
     * @param string $extensionOrDataUri The file extension or data URI
     * @param string|null $filePath Path to the file for MIME detection (null for data URIs)
     * @param array<FileTypeEnum|string> $allowedFileTypes Array of allowed file types
     * @return bool True if allowed, false otherwise
     */
    public function isFileTypeAllowed(string $extensionOrDataUri, ?string $filePath, array $allowedFileTypes): bool
    {
        // Empty array means no types allowed
        if (empty($allowedFileTypes)) {
            return false;
        }

        // If 'all' is explicitly allowed, allow all file types
        if (in_array('all', $allowedFileTypes, true)) {
            return true;
        }

        // Assemble all allowed MIME types from user specifications
        $allowedMimeTypes = $this->assembleAllowedMimeTypes($allowedFileTypes);
        $allowedExtensions = $this->assembleAllowedExtensions($allowedFileTypes);

        // Determine if this is a data URI or file extension
        $isDataUri = str_starts_with($extensionOrDataUri, 'data:');

        if ($isDataUri) {
            // Handle data URI validation
            return $this->validateDataUriWithMimeTypes($extensionOrDataUri, $allowedMimeTypes, $allowedExtensions);
        } else {
            // Handle file upload validation with MIME-first approach
            return $this->validateFileUploadWithMimeTypes($extensionOrDataUri, $filePath, $allowedMimeTypes, $allowedExtensions);
        }
    }


    /**
     * Assemble all allowed MIME types from user specifications
     *
     * @param array<FileTypeEnum|string> $allowedFileTypes Array of allowed file types
     * @return array<string> Array of allowed MIME types
     */
    private function assembleAllowedMimeTypes(array $allowedFileTypes): array
    {
        $allowedMimeTypes = [];

        foreach ($allowedFileTypes as $type) {
            // Handle FileTypeEnum objects
            if ($type instanceof FileTypeEnum) {
                $fileTypeEnum = $type;
                // Get all MIME types for this category
                $categoryMimeTypes = $this->getMimeTypesForCategory($fileTypeEnum);
                $allowedMimeTypes = array_merge($allowedMimeTypes, $categoryMimeTypes);
                continue;
            }

            // Handle custom MIME types (prefixed with 'mime:')
            if (str_starts_with($type, 'mime:')) {
                $mimeType = substr($type, 5); // Remove 'mime:' prefix
                $allowedMimeTypes[] = $mimeType;
                continue;
            }

            // Handle file type categories (image, pdf, doc, etc.)
            $fileTypeEnum = FileTypeEnum::tryFrom($type);
            if ($fileTypeEnum) {
                // Get all MIME types for this category
                $categoryMimeTypes = $this->getMimeTypesForCategory($fileTypeEnum);
                $allowedMimeTypes = array_merge($allowedMimeTypes, $categoryMimeTypes);
                continue;
            }

            // Handle custom extensions - check if we know the MIME type
            $knownMimeType = $this->getMimeTypeForExtension($type);
            if ($knownMimeType) {
                $allowedMimeTypes[] = $knownMimeType;
            }
            // If we don't know the MIME type for this extension, it will be handled as whitelisted
        }

        return array_unique($allowedMimeTypes);
    }


    /**
     * Assemble all allowed extensions from user specifications
     *
     * @param array<FileTypeEnum|string> $allowedFileTypes Array of allowed file types
     * @return array<string> Array of allowed extensions
     */
    private function assembleAllowedExtensions(array $allowedFileTypes): array
    {
        $allowedExtensions = [];

        foreach ($allowedFileTypes as $type) {
            // Handle FileTypeEnum objects
            if ($type instanceof FileTypeEnum) {
                $fileTypeEnum = $type;
                // Get all extensions for this category
                $categoryExtensions = $this->getExtensionsForCategory($fileTypeEnum);
                $allowedExtensions = array_merge($allowedExtensions, $categoryExtensions);
                continue;
            }

            // Skip MIME types (prefixed with 'mime:')
            if (str_starts_with($type, 'mime:')) {
                continue;
            }

            // Handle file type categories (image, pdf, doc, etc.)
            $fileTypeEnum = FileTypeEnum::tryFrom($type);
            if ($fileTypeEnum) {
                // Get all extensions for this category
                $categoryExtensions = $this->getExtensionsForCategory($fileTypeEnum);
                $allowedExtensions = array_merge($allowedExtensions, $categoryExtensions);
                continue;
            }

            // Handle custom extensions
            $allowedExtensions[] = strtolower($type);
        }

        return array_unique($allowedExtensions);
    }


    /**
     * Get MIME types for a file type category
     *
     * @param FileTypeEnum $category The file type category
     * @return array<string> Array of MIME types for this category
     */
    private function getMimeTypesForCategory(FileTypeEnum $category): array
    {
        $mimeTypes = [];
        $supportedTypes = SupportedFileTypesEnum::getTypesForCategory($category);

        foreach ($supportedTypes as $type) {
            $mimeTypes[] = $type->getMimeType();
        }

        return array_unique($mimeTypes);
    }


    /**
     * Get extensions for a file type category
     *
     * @param FileTypeEnum $category The file type category
     * @return array<string> Array of extensions for this category
     */
    private function getExtensionsForCategory(FileTypeEnum $category): array
    {
        return SupportedFileTypesEnum::getExtensionsForCategory($category);
    }


    /**
     * Get MIME type for a specific extension
     *
     * @param string $extension The file extension
     * @return string|null The MIME type or null if unknown
     */
    public function getMimeTypeForExtension(string $extension): ?string
    {
        $supportedType = SupportedFileTypesEnum::findByExtension($extension);
        return $supportedType ? $supportedType->getMimeType() : null;
    }


    /**
     * Validate file upload using MIME-first approach
     *
     * @param string $extension The file extension
     * @param string|null $filePath Path to the file for MIME detection
     * @param array<string> $allowedMimeTypes Array of allowed MIME types
     * @param array<string> $allowedExtensions Array of allowed extensions
     * @return bool True if allowed, false otherwise
     */
    private function validateFileUploadWithMimeTypes(string $extension, ?string $filePath, array $allowedMimeTypes, array $allowedExtensions): bool
    {
        $extension = strtolower($extension);

        // Step 1: MIME type validation (highest priority)
        if ($filePath) {
            $detectedMimeType = $this->getDetectedMimeType($filePath);
            if ($detectedMimeType) {
                // Special case: HEIC/HEIF files with jpg extension (will be converted)
                // Allow HEIC files to pass validation even with wrong/missing extension if images are allowed
                if (in_array($detectedMimeType, ['image/heic', 'image/heif'], true)) {
                    // Check if any image MIME types are allowed
                    foreach ($allowedMimeTypes as $allowedMime) {
                        if (str_starts_with($allowedMime, 'image/')) {
                            return true; // HEIC is an image type, will be converted to JPG
                        }
                    }
                }
                
                // Check if detected MIME type matches expected MIME type for extension
                $expectedMimeType = $this->getMimeTypeForExtension($extension);
                if ($expectedMimeType && $detectedMimeType !== $expectedMimeType) {
                    return false; // MIME type mismatch
                }

                // Check if detected MIME type is in allowed MIME types
                if (in_array($detectedMimeType, $allowedMimeTypes, true)) {
                    return true;
                }
            }
        }

        // Step 2: Extension validation
        if (in_array($extension, $allowedExtensions, true)) {
            return true;
        }

        // Step 3: Unknown extension handling
        // If the extension is not in our known extensions, check if it's whitelisted
        $knownMimeType = $this->getMimeTypeForExtension($extension);
        if ($knownMimeType === null) {
            // This is an unknown extension - check if it was explicitly allowed
            foreach ($allowedExtensions as $allowedExt) {
                if ($allowedExt === $extension) {
                    return true; // Unknown extension explicitly whitelisted
                }
            }
        }

        return false;
    }


    /**
     * Validate data URI using MIME-first approach
     *
     * @param string $dataUri The data URI
     * @param array<string> $allowedMimeTypes Array of allowed MIME types
     * @param array<string> $allowedExtensions Array of allowed extensions
     * @return bool True if allowed, false otherwise
     */
    private function validateDataUriWithMimeTypes(string $dataUri, array $allowedMimeTypes, array $allowedExtensions): bool
    {
        // Get extension from data URI
        $extension = $this->getFileExtension($dataUri);
        if (!$extension) {
            return false;
        }

        $extension = strtolower($extension);

        // Step 1: Extension validation
        if (in_array($extension, $allowedExtensions, true)) {
            return true;
        }

        // Step 2: MIME type validation (if we can determine it)
        $knownMimeType = $this->getMimeTypeForExtension($extension);
        if ($knownMimeType && in_array($knownMimeType, $allowedMimeTypes, true)) {
            return true;
        }

        // Step 3: Unknown extension handling
        if ($knownMimeType === null) {
            // This is an unknown extension - check if it was explicitly allowed
            foreach ($allowedExtensions as $allowedExt) {
                if ($allowedExt === $extension) {
                    return true; // Unknown extension explicitly whitelisted
                }
            }
        }

        return false;
    }


    /**
     * Get the detected MIME type for a file
     *
     * @param string $filePath Path to the file
     * @return string|null The detected MIME type or null if detection fails
     */
    private function getDetectedMimeType(string $filePath): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mimeType !== false ? $mimeType : null;
    }
}
