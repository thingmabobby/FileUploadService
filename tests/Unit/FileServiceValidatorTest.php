<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\FileServiceValidator;
use PHPUnit\Framework\TestCase;

class FileServiceValidatorTest extends TestCase
{
    private FileServiceValidator $validator;


    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FileServiceValidator();
    }


    public function testValidateBase64DataUriValid(): void
    {
        $validDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $this->assertTrue($this->validator->validateBase64DataUri($validDataUri));
    }


    public function testValidateBase64DataUriInvalidFormat(): void
    {
        $invalidDataUri = 'not-a-data-uri';

        $this->assertFalse($this->validator->validateBase64DataUri($invalidDataUri));
    }


    public function testValidateBase64DataUriEmptyData(): void
    {
        $emptyDataUri = 'data:image/jpeg;base64,';

        $this->assertFalse($this->validator->validateBase64DataUri($emptyDataUri));
    }


    public function testValidateBase64DataUriInvalidBase64(): void
    {
        $invalidBase64DataUri = 'data:image/jpeg;base64,invalid-base64-data!@#';

        $this->assertFalse($this->validator->validateBase64DataUri($invalidBase64DataUri));
    }


    public function testValidateBase64DataUriTooLarge(): void
    {
        // Create a data URI that would decode to more than 100MB
        $largeBase64 = str_repeat('A', 134217728); // 100MB in base64
        $largeDataUri = 'data:application/octet-stream;base64,' . base64_encode($largeBase64);

        $this->assertFalse($this->validator->validateBase64DataUri($largeDataUri));
    }


    public function testValidateBasicFilePropertiesNonExistentFile(): void
    {
        $nonExistentFile = '/tmp/non-existent-file-' . uniqid();

        $this->assertFalse($this->validator->validateBasicFileProperties($nonExistentFile));
    }


    public function testValidateImageFileWithGetimagesize(): void
    {
        // Create a temporary PNG file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image');
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($tempFile, $imageData);

        try {
            $this->assertTrue($this->validator->validateImageFile($tempFile, 'png'));
        } finally {
            unlink($tempFile);
        }
    }


    public function testValidateImageFileInvalidFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_invalid');
        file_put_contents($tempFile, 'not an image');

        try {
            $this->assertFalse($this->validator->validateImageFile($tempFile, 'jpg'));
        } finally {
            unlink($tempFile);
        }
    }


    public function testValidatePdfFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_pdf');
        file_put_contents($tempFile, '%PDF-1.4');

        try {
            $this->assertTrue($this->validator->validatePdfFile($tempFile));
        } finally {
            unlink($tempFile);
        }
    }


    public function testValidatePdfFileInvalid(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_invalid_pdf');
        file_put_contents($tempFile, 'not a pdf');

        try {
            $this->assertFalse($this->validator->validatePdfFile($tempFile));
        } finally {
            unlink($tempFile);
        }
    }


    public function testIsPdfDataUri(): void
    {
        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        $this->assertTrue($this->validator->isPdfDataUri($pdfDataUri));

        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $this->assertFalse($this->validator->isPdfDataUri($imageDataUri));
    }


    public function testIsImageDataUri(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $this->assertTrue($this->validator->isImageDataUri($imageDataUri));

        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        $this->assertFalse($this->validator->isImageDataUri($pdfDataUri));
    }


    public function testGetFileTypeCategoryFromDataUri(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $this->assertSame(FileTypeEnum::IMAGE, $this->validator->getFileTypeCategoryFromDataUri($imageDataUri));

        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        $this->assertSame(FileTypeEnum::PDF, $this->validator->getFileTypeCategoryFromDataUri($pdfDataUri));

        $unknownDataUri = 'data:application/unknown;base64,SGVsbG8gV29ybGQ=';

        $this->assertNull($this->validator->getFileTypeCategoryFromDataUri($unknownDataUri));
    }


    public function testGetFileExtension(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $this->assertSame('jpg', $this->validator->getFileExtension($imageDataUri));

        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        $this->assertSame('pdf', $this->validator->getFileExtension($pdfDataUri));

        $unknownDataUri = 'data:application/unknown;base64,SGVsbG8gV29ybGQ=';

        $this->assertNull($this->validator->getFileExtension($unknownDataUri));
    }


    public function testGetFileTypeCategoryFromExtension(): void
    {
        $this->assertSame(FileTypeEnum::IMAGE, $this->validator->getFileTypeCategoryFromExtension('jpg'));
        $this->assertSame(FileTypeEnum::IMAGE, $this->validator->getFileTypeCategoryFromExtension('png'));
        $this->assertSame(FileTypeEnum::PDF, $this->validator->getFileTypeCategoryFromExtension('pdf'));
        $this->assertSame(FileTypeEnum::DOC, $this->validator->getFileTypeCategoryFromExtension('doc'));
        $this->assertSame(FileTypeEnum::CAD, $this->validator->getFileTypeCategoryFromExtension('dwg'));
        $this->assertSame(FileTypeEnum::ARCHIVE, $this->validator->getFileTypeCategoryFromExtension('zip'));

        $this->assertNull($this->validator->getFileTypeCategoryFromExtension('unknown'));
        $this->assertNull($this->validator->getFileTypeCategoryFromExtension(''));
    }


    public function testGetExtensionFromMimeType(): void
    {
        $this->assertSame('jpg', $this->validator->getExtensionFromMimeType('image/jpeg'));
        $this->assertSame('png', $this->validator->getExtensionFromMimeType('image/png'));
        $this->assertSame('pdf', $this->validator->getExtensionFromMimeType('application/pdf'));
        $this->assertSame('doc', $this->validator->getExtensionFromMimeType('application/msword'));

        $this->assertSame('', $this->validator->getExtensionFromMimeType('application/unknown'));
        $this->assertSame('', $this->validator->getExtensionFromMimeType(''));
    }


    public function testGetSupportedImageTypes(): void
    {
        $imageTypes = $this->validator->getSupportedImageTypes();

        $this->assertIsArray($imageTypes);
        $this->assertArrayHasKey('jpg', $imageTypes);
        $this->assertArrayHasKey('png', $imageTypes);
        $this->assertArrayHasKey('gif', $imageTypes);
        $this->assertArrayHasKey('webp', $imageTypes);
        $this->assertArrayHasKey('heic', $imageTypes);
        $this->assertArrayHasKey('heif', $imageTypes);
    }


    public function testGetSupportedPdfTypes(): void
    {
        $pdfTypes = $this->validator->getSupportedPdfTypes();

        $this->assertIsArray($pdfTypes);
        $this->assertArrayHasKey('pdf', $pdfTypes);
        $this->assertArrayHasKey('x-pdf', $pdfTypes);
    }


    public function testGetSupportedDocumentTypes(): void
    {
        $docTypes = $this->validator->getSupportedDocumentTypes();

        $this->assertIsArray($docTypes);
        $this->assertArrayHasKey('doc', $docTypes);
        $this->assertArrayHasKey('docx', $docTypes);
        $this->assertArrayHasKey('txt', $docTypes);
    }


    public function testGetSupportedCadTypes(): void
    {
        $cadTypes = $this->validator->getSupportedCadTypes();

        $this->assertIsArray($cadTypes);
        $this->assertArrayHasKey('dwg', $cadTypes);
        $this->assertArrayHasKey('dxf', $cadTypes);
    }


    public function testGetSupportedArchiveTypes(): void
    {
        $archiveTypes = $this->validator->getSupportedArchiveTypes();

        $this->assertIsArray($archiveTypes);
        $this->assertArrayHasKey('zip', $archiveTypes);
        $this->assertArrayHasKey('rar', $archiveTypes);
    }
}
