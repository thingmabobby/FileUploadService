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
        if ($length === 0 || $length > 100 * 1024 * 1024) { // 100MB max
            return false;
        }

        return true;
    }


    /**
     * Validate uploaded file for corruption
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

        // Type-specific validation
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Validate based on file type
        if (array_key_exists($extension, $this->getSupportedImageTypes())) {
            return $this->validateImageFile($tmpPath, $extension);
        }

        if ($extension === 'pdf') {
            return $this->validatePdfFile($tmpPath);
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
            $types[$type->getExtension()] = $type->getStandardExtension();
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
            $types[$type->getExtension()] = $type->getStandardExtension();
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
            $types[$type->getExtension()] = $type->getStandardExtension();
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
            $types[$type->getExtension()] = $type->getStandardExtension();
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
            $types[$type->getExtension()] = $type->getStandardExtension();
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
}
