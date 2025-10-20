<?php

declare(strict_types=1);

namespace FileUploadService\Enum;

/**
 * Enum for supported file types with their extensions and MIME types
 * Replaces the class constants in FileServiceValidator for better type safety
 * 
 * @package FileUploadService\Enum
 */
enum SupportedFileTypesEnum: string
{
    // Image types
    case IMAGE_JPEG = 'jpg';
    case IMAGE_PNG  = 'png';
    case IMAGE_GIF  = 'gif';
    case IMAGE_WEBP = 'webp';
    case IMAGE_AVIF = 'avif';
    case IMAGE_JXL  = 'jxl';
    case IMAGE_BMP  = 'bmp';
    case IMAGE_TIFF = 'tiff';
    case IMAGE_HEIC = 'heic';
    case IMAGE_HEIF = 'heif';

        // PDF types (all map to .pdf extension but have different MIME types)
    case PDF_STANDARD = 'pdf';
    case PDF_X_PDF    = 'x-pdf';
    case PDF_ACROBAT  = 'acrobat';
    case PDF_VND_PDF  = 'vnd-pdf';

        // Document types
    case DOC_WORD        = 'doc';
    case DOC_WORDX       = 'docx';
    case DOC_EXCEL       = 'xls';
    case DOC_EXCELX      = 'xlsx';
    case DOC_POWERPOINT  = 'ppt';
    case DOC_POWERPOINTX = 'pptx';
    case DOC_TEXT        = 'txt';
    case DOC_RTF         = 'rtf';
    case DOC_CSV         = 'csv';
    case DOC_XML         = 'xml';
    case DOC_JSON        = 'json';
    case DOC_ODT         = 'odt';
    case DOC_ODS         = 'ods';
    case DOC_ODP         = 'odp';

        // CAD types
    case CAD_DWG    = 'dwg';
    case CAD_DXF    = 'dxf';
    case CAD_STEP   = 'step';
    case CAD_IGES   = 'iges';
    case CAD_STL    = 'stl';
    case CAD_SLDPRT = 'sldprt';
    case CAD_SLDASM = 'sldasm';

        // Archive types
    case ARCHIVE_ZIP = 'zip';
    case ARCHIVE_RAR = 'rar';
    case ARCHIVE_7Z  = '7z';
    case ARCHIVE_TAR = 'tar';
    case ARCHIVE_GZ  = 'gz';

        // Video types
    case VIDEO_MP4  = 'mp4';
    case VIDEO_AVI  = 'avi';
    case VIDEO_MOV  = 'mov';
    case VIDEO_WMV  = 'wmv';
    case VIDEO_FLV  = 'flv';
    case VIDEO_WEBM = 'webm';
    case VIDEO_MKV  = 'mkv';
    case VIDEO_MPEG = 'mpeg';
    case VIDEO_MPG  = 'mpg';
    case VIDEO_3GP  = '3gp';
    case VIDEO_M4V  = 'm4v';
    case VIDEO_OGV  = 'ogv';


    /**
     * Get the file extension for this type
     */
    public function getExtension(): string
    {
        return $this->value;
    }


    /**
     * Get the standard extension (for cases where multiple formats map to same extension)
     * This is needed for PDF types that have different MIME types but same extension
     */
    public function getStandardExtension(): string
    {
        return match ($this) {
            // PDF types all map to pdf extension
            self::PDF_STANDARD, self::PDF_X_PDF, self::PDF_ACROBAT, self::PDF_VND_PDF => 'pdf',

            // All other types use their enum value as the extension
            default => $this->getExtension(),
        };
    }


    /**
     * Get the MIME type for this file type
     */
    public function getMimeType(): string
    {
        return match ($this) {
            // Image MIME types
            self::IMAGE_JPEG => 'image/jpeg',
            self::IMAGE_PNG  => 'image/png',
            self::IMAGE_GIF  => 'image/gif',
            self::IMAGE_WEBP => 'image/webp',
            self::IMAGE_AVIF => 'image/avif',
            self::IMAGE_JXL  => 'image/jxl',
            self::IMAGE_BMP  => 'image/bmp',
            self::IMAGE_TIFF => 'image/tiff',
            self::IMAGE_HEIC => 'image/heic',
            self::IMAGE_HEIF => 'image/heif',

            // PDF MIME types
            self::PDF_STANDARD => 'application/pdf',
            self::PDF_X_PDF    => 'application/x-pdf',
            self::PDF_ACROBAT  => 'application/acrobat',
            self::PDF_VND_PDF  => 'application/vnd.pdf',

            // Document MIME types
            self::DOC_WORD        => 'application/msword',
            self::DOC_WORDX       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::DOC_EXCEL       => 'application/vnd.ms-excel',
            self::DOC_EXCELX      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::DOC_POWERPOINT  => 'application/vnd.ms-powerpoint',
            self::DOC_POWERPOINTX => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            self::DOC_TEXT        => 'text/plain',
            self::DOC_RTF         => 'application/rtf',
            self::DOC_CSV         => 'text/csv',
            self::DOC_XML         => 'application/xml',
            self::DOC_JSON        => 'application/json',
            self::DOC_ODT         => 'application/vnd.oasis.opendocument.text',
            self::DOC_ODS         => 'application/vnd.oasis.opendocument.spreadsheet',
            self::DOC_ODP         => 'application/vnd.oasis.opendocument.presentation',

            // CAD MIME types
            self::CAD_DWG    => 'application/dwg',
            self::CAD_DXF    => 'application/dxf',
            self::CAD_STEP   => 'application/step',
            self::CAD_IGES   => 'application/iges',
            self::CAD_STL    => 'application/stl',
            self::CAD_SLDPRT => 'application/sldprt',
            self::CAD_SLDASM => 'application/sldasm',

            // Archive MIME types
            self::ARCHIVE_ZIP => 'application/zip',
            self::ARCHIVE_RAR => 'application/x-rar-compressed',
            self::ARCHIVE_7Z  => 'application/x-7z-compressed',
            self::ARCHIVE_TAR => 'application/x-tar',
            self::ARCHIVE_GZ  => 'application/gzip',

            // Video MIME types
            self::VIDEO_MP4  => 'video/mp4',
            self::VIDEO_AVI  => 'video/x-msvideo',
            self::VIDEO_MOV  => 'video/quicktime',
            self::VIDEO_WMV  => 'video/x-ms-wmv',
            self::VIDEO_FLV  => 'video/x-flv',
            self::VIDEO_WEBM => 'video/webm',
            self::VIDEO_MKV  => 'video/x-matroska',
            self::VIDEO_MPEG => 'video/mpeg',
            self::VIDEO_MPG  => 'video/mpeg',
            self::VIDEO_3GP  => 'video/3gpp',
            self::VIDEO_M4V  => 'video/x-m4v',
            self::VIDEO_OGV  => 'video/ogg',
        };
    }


    /**
     * Get the file type category for this supported type
     */
    public function getCategory(): FileTypeEnum
    {
        return match ($this) {
            // Image types
            self::IMAGE_JPEG, self::IMAGE_PNG, self::IMAGE_GIF, self::IMAGE_WEBP,
            self::IMAGE_AVIF, self::IMAGE_JXL, self::IMAGE_BMP, self::IMAGE_TIFF,
            self::IMAGE_HEIC, self::IMAGE_HEIF => FileTypeEnum::IMAGE,

            // PDF types
            self::PDF_STANDARD, self::PDF_X_PDF, self::PDF_ACROBAT, self::PDF_VND_PDF => FileTypeEnum::PDF,

            // Document types
            self::DOC_WORD, self::DOC_WORDX, self::DOC_EXCEL, self::DOC_EXCELX,
            self::DOC_POWERPOINT, self::DOC_POWERPOINTX, self::DOC_TEXT, self::DOC_RTF,
            self::DOC_CSV, self::DOC_XML, self::DOC_JSON, self::DOC_ODT,
            self::DOC_ODS, self::DOC_ODP => FileTypeEnum::DOC,

            // CAD types
            self::CAD_DWG, self::CAD_DXF, self::CAD_STEP, self::CAD_IGES,
            self::CAD_STL, self::CAD_SLDPRT, self::CAD_SLDASM => FileTypeEnum::CAD,

            // Archive types
            self::ARCHIVE_ZIP, self::ARCHIVE_RAR, self::ARCHIVE_7Z, self::ARCHIVE_TAR,
            self::ARCHIVE_GZ => FileTypeEnum::ARCHIVE,

            // Video types
            self::VIDEO_MP4, self::VIDEO_AVI, self::VIDEO_MOV, self::VIDEO_WMV,
            self::VIDEO_FLV, self::VIDEO_WEBM, self::VIDEO_MKV, self::VIDEO_MPEG,
            self::VIDEO_MPG, self::VIDEO_3GP, self::VIDEO_M4V, self::VIDEO_OGV => FileTypeEnum::VIDEO,
        };
    }


    /**
     * Get all supported types for a specific category
     * 
     * @return array<int, self> Array of supported file types for the category
     */
    public static function getTypesForCategory(FileTypeEnum $category): array
    {
        return array_filter(
            self::cases(),
            fn(self $type) => $type->getCategory() === $category
        );
    }


    /**
     * Get all extensions for a specific category
     * 
     * @return array<int, string> Array of extensions for the category
     */
    public static function getExtensionsForCategory(FileTypeEnum $category): array
    {
        return array_map(
            fn(self $type) => $type->getStandardExtension(),
            self::getTypesForCategory($category)
        );
    }


    /**
     * Find a supported type by extension
     */
    public static function findByExtension(string $extension): ?self
    {
        $extension = strtolower($extension);
        foreach (self::cases() as $type) {
            if ($type->getStandardExtension() === $extension) {
                return $type;
            }
        }
        return null;
    }


    /**
     * Find a supported type by MIME type
     */
    public static function findByMimeType(string $mimeType): ?self
    {
        $mimeType = strtolower($mimeType);
        foreach (self::cases() as $type) {
            if ($type->getMimeType() === $mimeType) {
                return $type;
            }
        }
        return null;
    }
}
