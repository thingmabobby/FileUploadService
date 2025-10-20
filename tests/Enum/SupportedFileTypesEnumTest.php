<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Enum;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\SupportedFileTypesEnum;
use PHPUnit\Framework\TestCase;

class SupportedFileTypesEnumTest extends TestCase
{
    public function testImageTypes(): void
    {
        $this->assertSame('jpg', SupportedFileTypesEnum::IMAGE_JPEG->getExtension());
        $this->assertSame('png', SupportedFileTypesEnum::IMAGE_PNG->getExtension());
        $this->assertSame('gif', SupportedFileTypesEnum::IMAGE_GIF->getExtension());
        $this->assertSame('webp', SupportedFileTypesEnum::IMAGE_WEBP->getExtension());
        $this->assertSame('avif', SupportedFileTypesEnum::IMAGE_AVIF->getExtension());
        $this->assertSame('jxl', SupportedFileTypesEnum::IMAGE_JXL->getExtension());
        $this->assertSame('bmp', SupportedFileTypesEnum::IMAGE_BMP->getExtension());
        $this->assertSame('tiff', SupportedFileTypesEnum::IMAGE_TIFF->getExtension());
        $this->assertSame('heic', SupportedFileTypesEnum::IMAGE_HEIC->getExtension());
        $this->assertSame('heif', SupportedFileTypesEnum::IMAGE_HEIF->getExtension());
    }


    public function testImageMimeTypes(): void
    {
        $this->assertSame('image/jpeg', SupportedFileTypesEnum::IMAGE_JPEG->getMimeType());
        $this->assertSame('image/png', SupportedFileTypesEnum::IMAGE_PNG->getMimeType());
        $this->assertSame('image/gif', SupportedFileTypesEnum::IMAGE_GIF->getMimeType());
        $this->assertSame('image/webp', SupportedFileTypesEnum::IMAGE_WEBP->getMimeType());
        $this->assertSame('image/avif', SupportedFileTypesEnum::IMAGE_AVIF->getMimeType());
        $this->assertSame('image/jxl', SupportedFileTypesEnum::IMAGE_JXL->getMimeType());
        $this->assertSame('image/bmp', SupportedFileTypesEnum::IMAGE_BMP->getMimeType());
        $this->assertSame('image/tiff', SupportedFileTypesEnum::IMAGE_TIFF->getMimeType());
        $this->assertSame('image/heic', SupportedFileTypesEnum::IMAGE_HEIC->getMimeType());
        $this->assertSame('image/heif', SupportedFileTypesEnum::IMAGE_HEIF->getMimeType());
    }


    public function testImageStandardExtensions(): void
    {
        $this->assertSame('jpg', SupportedFileTypesEnum::IMAGE_JPEG->getStandardExtension());
        $this->assertSame('png', SupportedFileTypesEnum::IMAGE_PNG->getStandardExtension());
        $this->assertSame('gif', SupportedFileTypesEnum::IMAGE_GIF->getStandardExtension());
        $this->assertSame('webp', SupportedFileTypesEnum::IMAGE_WEBP->getStandardExtension());
        $this->assertSame('avif', SupportedFileTypesEnum::IMAGE_AVIF->getStandardExtension());
        $this->assertSame('jxl', SupportedFileTypesEnum::IMAGE_JXL->getStandardExtension());
        $this->assertSame('bmp', SupportedFileTypesEnum::IMAGE_BMP->getStandardExtension());
        $this->assertSame('tiff', SupportedFileTypesEnum::IMAGE_TIFF->getStandardExtension());
        $this->assertSame('heic', SupportedFileTypesEnum::IMAGE_HEIC->getStandardExtension());
        $this->assertSame('heif', SupportedFileTypesEnum::IMAGE_HEIF->getStandardExtension());
    }


    public function testPdfTypes(): void
    {
        $this->assertSame('pdf', SupportedFileTypesEnum::PDF_STANDARD->getExtension());
        $this->assertSame('x-pdf', SupportedFileTypesEnum::PDF_X_PDF->getExtension());
        $this->assertSame('acrobat', SupportedFileTypesEnum::PDF_ACROBAT->getExtension());
        $this->assertSame('vnd-pdf', SupportedFileTypesEnum::PDF_VND_PDF->getExtension());
    }


    public function testPdfMimeTypes(): void
    {
        $this->assertSame('application/pdf', SupportedFileTypesEnum::PDF_STANDARD->getMimeType());
        $this->assertSame('application/x-pdf', SupportedFileTypesEnum::PDF_X_PDF->getMimeType());
        $this->assertSame('application/acrobat', SupportedFileTypesEnum::PDF_ACROBAT->getMimeType());
        $this->assertSame('application/vnd.pdf', SupportedFileTypesEnum::PDF_VND_PDF->getMimeType());
    }


    public function testPdfStandardExtensions(): void
    {
        $this->assertSame('pdf', SupportedFileTypesEnum::PDF_STANDARD->getStandardExtension());
        $this->assertSame('pdf', SupportedFileTypesEnum::PDF_X_PDF->getStandardExtension());
        $this->assertSame('pdf', SupportedFileTypesEnum::PDF_ACROBAT->getStandardExtension());
        $this->assertSame('pdf', SupportedFileTypesEnum::PDF_VND_PDF->getStandardExtension());
    }


    public function testDocumentTypes(): void
    {
        $this->assertSame('doc', SupportedFileTypesEnum::DOC_WORD->getExtension());
        $this->assertSame('docx', SupportedFileTypesEnum::DOC_WORDX->getExtension());
        $this->assertSame('xls', SupportedFileTypesEnum::DOC_EXCEL->getExtension());
        $this->assertSame('xlsx', SupportedFileTypesEnum::DOC_EXCELX->getExtension());
        $this->assertSame('ppt', SupportedFileTypesEnum::DOC_POWERPOINT->getExtension());
        $this->assertSame('pptx', SupportedFileTypesEnum::DOC_POWERPOINTX->getExtension());
        $this->assertSame('txt', SupportedFileTypesEnum::DOC_TEXT->getExtension());
        $this->assertSame('rtf', SupportedFileTypesEnum::DOC_RTF->getExtension());
        $this->assertSame('csv', SupportedFileTypesEnum::DOC_CSV->getExtension());
        $this->assertSame('xml', SupportedFileTypesEnum::DOC_XML->getExtension());
        $this->assertSame('json', SupportedFileTypesEnum::DOC_JSON->getExtension());
        $this->assertSame('odt', SupportedFileTypesEnum::DOC_ODT->getExtension());
        $this->assertSame('ods', SupportedFileTypesEnum::DOC_ODS->getExtension());
        $this->assertSame('odp', SupportedFileTypesEnum::DOC_ODP->getExtension());
    }


    public function testCadTypes(): void
    {
        $this->assertSame('dwg', SupportedFileTypesEnum::CAD_DWG->getExtension());
        $this->assertSame('dxf', SupportedFileTypesEnum::CAD_DXF->getExtension());
        $this->assertSame('step', SupportedFileTypesEnum::CAD_STEP->getExtension());
        $this->assertSame('iges', SupportedFileTypesEnum::CAD_IGES->getExtension());
        $this->assertSame('stl', SupportedFileTypesEnum::CAD_STL->getExtension());
        $this->assertSame('sldprt', SupportedFileTypesEnum::CAD_SLDPRT->getExtension());
        $this->assertSame('sldasm', SupportedFileTypesEnum::CAD_SLDASM->getExtension());
    }


    public function testArchiveTypes(): void
    {
        $this->assertSame('zip', SupportedFileTypesEnum::ARCHIVE_ZIP->getExtension());
        $this->assertSame('rar', SupportedFileTypesEnum::ARCHIVE_RAR->getExtension());
        $this->assertSame('7z', SupportedFileTypesEnum::ARCHIVE_7Z->getExtension());
        $this->assertSame('tar', SupportedFileTypesEnum::ARCHIVE_TAR->getExtension());
        $this->assertSame('gz', SupportedFileTypesEnum::ARCHIVE_GZ->getExtension());
    }


    public function testGetCategory(): void
    {
        // Image types
        $this->assertSame(FileTypeEnum::IMAGE, SupportedFileTypesEnum::IMAGE_JPEG->getCategory());
        $this->assertSame(FileTypeEnum::IMAGE, SupportedFileTypesEnum::IMAGE_PNG->getCategory());

        // PDF types
        $this->assertSame(FileTypeEnum::PDF, SupportedFileTypesEnum::PDF_STANDARD->getCategory());
        $this->assertSame(FileTypeEnum::PDF, SupportedFileTypesEnum::PDF_X_PDF->getCategory());

        // Document types
        $this->assertSame(FileTypeEnum::DOC, SupportedFileTypesEnum::DOC_WORD->getCategory());
        $this->assertSame(FileTypeEnum::DOC, SupportedFileTypesEnum::DOC_EXCEL->getCategory());

        // CAD types
        $this->assertSame(FileTypeEnum::CAD, SupportedFileTypesEnum::CAD_DWG->getCategory());
        $this->assertSame(FileTypeEnum::CAD, SupportedFileTypesEnum::CAD_DXF->getCategory());

        // Archive types
        $this->assertSame(FileTypeEnum::ARCHIVE, SupportedFileTypesEnum::ARCHIVE_ZIP->getCategory());
        $this->assertSame(FileTypeEnum::ARCHIVE, SupportedFileTypesEnum::ARCHIVE_RAR->getCategory());
    }


    public function testGetTypesForCategory(): void
    {
        $imageTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::IMAGE);
        $this->assertIsArray($imageTypes);
        $this->assertGreaterThan(0, count($imageTypes));

        foreach ($imageTypes as $type) {
            $this->assertSame(FileTypeEnum::IMAGE, $type->getCategory());
        }

        $pdfTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::PDF);
        $this->assertIsArray($pdfTypes);
        $this->assertCount(4, $pdfTypes);

        foreach ($pdfTypes as $type) {
            $this->assertSame(FileTypeEnum::PDF, $type->getCategory());
        }
    }


    public function testGetExtensionsForCategory(): void
    {
        $imageExtensions = SupportedFileTypesEnum::getExtensionsForCategory(FileTypeEnum::IMAGE);
        $this->assertIsArray($imageExtensions);
        $this->assertContains('jpg', $imageExtensions);
        $this->assertContains('png', $imageExtensions);
        $this->assertContains('gif', $imageExtensions);

        $pdfExtensions = SupportedFileTypesEnum::getExtensionsForCategory(FileTypeEnum::PDF);
        $this->assertIsArray($pdfExtensions);
        $this->assertContains('pdf', $pdfExtensions);
    }


    public function testFindByExtension(): void
    {
        $this->assertSame(SupportedFileTypesEnum::IMAGE_JPEG, SupportedFileTypesEnum::findByExtension('jpg'));
        $this->assertSame(SupportedFileTypesEnum::IMAGE_PNG, SupportedFileTypesEnum::findByExtension('png'));
        $this->assertSame(SupportedFileTypesEnum::PDF_STANDARD, SupportedFileTypesEnum::findByExtension('pdf'));
        $this->assertSame(SupportedFileTypesEnum::DOC_WORD, SupportedFileTypesEnum::findByExtension('doc'));
        $this->assertSame(SupportedFileTypesEnum::CAD_DWG, SupportedFileTypesEnum::findByExtension('dwg'));
        $this->assertSame(SupportedFileTypesEnum::ARCHIVE_ZIP, SupportedFileTypesEnum::findByExtension('zip'));
    }


    public function testFindByExtensionCaseInsensitive(): void
    {
        $this->assertSame(SupportedFileTypesEnum::IMAGE_JPEG, SupportedFileTypesEnum::findByExtension('JPG'));
        $this->assertSame(SupportedFileTypesEnum::IMAGE_PNG, SupportedFileTypesEnum::findByExtension('PNG'));
        $this->assertSame(SupportedFileTypesEnum::PDF_STANDARD, SupportedFileTypesEnum::findByExtension('PDF'));
    }


    public function testFindByExtensionInvalid(): void
    {
        $this->assertNull(SupportedFileTypesEnum::findByExtension('invalid'));
        $this->assertNull(SupportedFileTypesEnum::findByExtension(''));
        $this->assertNull(SupportedFileTypesEnum::findByExtension('xyz'));
    }


    public function testFindByMimeType(): void
    {
        $this->assertSame(SupportedFileTypesEnum::IMAGE_JPEG, SupportedFileTypesEnum::findByMimeType('image/jpeg'));
        $this->assertSame(SupportedFileTypesEnum::IMAGE_PNG, SupportedFileTypesEnum::findByMimeType('image/png'));
        $this->assertSame(SupportedFileTypesEnum::PDF_STANDARD, SupportedFileTypesEnum::findByMimeType('application/pdf'));
        $this->assertSame(SupportedFileTypesEnum::DOC_WORD, SupportedFileTypesEnum::findByMimeType('application/msword'));
    }


    public function testFindByMimeTypeCaseInsensitive(): void
    {
        $this->assertSame(SupportedFileTypesEnum::IMAGE_JPEG, SupportedFileTypesEnum::findByMimeType('IMAGE/JPEG'));
        $this->assertSame(SupportedFileTypesEnum::PDF_STANDARD, SupportedFileTypesEnum::findByMimeType('APPLICATION/PDF'));
    }


    public function testFindByMimeTypeInvalid(): void
    {
        $this->assertNull(SupportedFileTypesEnum::findByMimeType('invalid/mime'));
        $this->assertNull(SupportedFileTypesEnum::findByMimeType(''));
        $this->assertNull(SupportedFileTypesEnum::findByMimeType('application/unknown'));
    }
}
