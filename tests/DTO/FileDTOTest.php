<?php

declare(strict_types=1);

namespace FileUploadService\Tests\DTO;

use FileUploadService\DTO\FileDTO;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\FileServiceValidator;
use PHPUnit\Framework\TestCase;

class FileDTOTest extends TestCase
{
    private FileServiceValidator $validator;


    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FileServiceValidator();
    }


    public function testConstructor(): void
    {
        $fileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'original.jpg',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            fileTypeCategory: FileTypeEnum::IMAGE,
            tmpPath: '/tmp/test123',
            dataUri: null,
            size: 1024,
            uploadError: UPLOAD_ERR_OK
        );

        $this->assertSame('test.jpg', $fileDTO->filename);
        $this->assertSame('original.jpg', $fileDTO->originalName);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertSame(FileTypeEnum::IMAGE, $fileDTO->fileTypeCategory);
        $this->assertSame('/tmp/test123', $fileDTO->tmpPath);
        $this->assertNull($fileDTO->dataUri);
        $this->assertSame(1024, $fileDTO->size);
        $this->assertSame(UPLOAD_ERR_OK, $fileDTO->uploadError);
    }


    public function testFromFilesArray(): void
    {
        $filesArray = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test123',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $fileDTO = FileDTO::fromFilesArray($filesArray, 'target.jpg');

        $this->assertSame('target.jpg', $fileDTO->filename);
        $this->assertSame('test.jpg', $fileDTO->originalName);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertSame('unknown', $fileDTO->fileTypeCategory);
        $this->assertSame('/tmp/test123', $fileDTO->tmpPath);
        $this->assertNull($fileDTO->dataUri);
        $this->assertSame(1024, $fileDTO->size);
        $this->assertSame(UPLOAD_ERR_OK, $fileDTO->uploadError);
    }


    public function testFromFilesArrayWithFileTypeCategory(): void
    {
        $filesArray = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test123',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $fileDTO = FileDTO::fromFilesArray($filesArray, 'target.jpg', FileTypeEnum::IMAGE);

        $this->assertSame('target.jpg', $fileDTO->filename);
        $this->assertSame('test.jpg', $fileDTO->originalName);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertSame(FileTypeEnum::IMAGE, $fileDTO->fileTypeCategory);
        $this->assertSame('/tmp/test123', $fileDTO->tmpPath);
        $this->assertNull($fileDTO->dataUri);
        $this->assertSame(1024, $fileDTO->size);
        $this->assertSame(UPLOAD_ERR_OK, $fileDTO->uploadError);
    }


    public function testFromDataUri(): void
    {
        $dataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $fileDTO = FileDTO::fromDataUri($dataUri, 'test.jpg', $this->validator);

        $this->assertSame('test.jpg', $fileDTO->filename);
        $this->assertSame('test.jpg', $fileDTO->originalName);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertSame(FileTypeEnum::IMAGE, $fileDTO->fileTypeCategory);
        $this->assertNull($fileDTO->tmpPath);
        $this->assertSame($dataUri, $fileDTO->dataUri);
        $this->assertNull($fileDTO->size);
        $this->assertSame(0, $fileDTO->uploadError);
    }


    public function testFromDataUriWithFileTypeCategory(): void
    {
        $dataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        $fileDTO = FileDTO::fromDataUri($dataUri, 'test.pdf', $this->validator, FileTypeEnum::PDF);

        $this->assertSame('test.pdf', $fileDTO->filename);
        $this->assertSame('test.pdf', $fileDTO->originalName);
        $this->assertSame('pdf', $fileDTO->extension);
        $this->assertSame('application/pdf', $fileDTO->mimeType);
        $this->assertSame(FileTypeEnum::PDF, $fileDTO->fileTypeCategory);
        $this->assertNull($fileDTO->tmpPath);
        $this->assertSame($dataUri, $fileDTO->dataUri);
        $this->assertNull($fileDTO->size);
        $this->assertSame(0, $fileDTO->uploadError);
    }


    public function testIsFileUpload(): void
    {
        $fileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            tmpPath: '/tmp/test123'
        );

        $this->assertTrue($fileDTO->isFileUpload());

        $fileDTONoTmpPath = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            tmpPath: null
        );

        $this->assertFalse($fileDTONoTmpPath->isFileUpload());
    }


    public function testIsDataUri(): void
    {
        $dataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $fileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            dataUri: $dataUri
        );

        $this->assertTrue($fileDTO->isDataUri());

        $fileDTONoDataUri = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            dataUri: null
        );

        $this->assertFalse($fileDTONoDataUri->isDataUri());
    }


    public function testIsUploadSuccessful(): void
    {
        $fileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            uploadError: UPLOAD_ERR_OK
        );

        $this->assertTrue($fileDTO->isUploadSuccessful());

        $fileDTOWithError = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            uploadError: UPLOAD_ERR_INI_SIZE
        );

        $this->assertFalse($fileDTOWithError->isUploadSuccessful());
    }


    public function testGetFileTypeCategoryAsString(): void
    {
        $fileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            fileTypeCategory: FileTypeEnum::IMAGE
        );

        $this->assertSame('image', $fileDTO->getFileTypeCategoryAsString());

        $fileDTOWithString = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            fileTypeCategory: 'custom'
        );

        $this->assertSame('custom', $fileDTOWithString->getFileTypeCategoryAsString());

        $fileDTOWithNull = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            fileTypeCategory: null
        );

        $this->assertSame('unknown', $fileDTOWithNull->getFileTypeCategoryAsString());
    }


    public function testIsImage(): void
    {
        $imageFileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            fileTypeCategory: FileTypeEnum::IMAGE
        );

        $this->assertTrue($imageFileDTO->isImage());

        $pdfFileDTO = new FileDTO(
            filename: 'test.pdf',
            originalName: 'test.pdf',
            extension: 'pdf',
            fileTypeCategory: FileTypeEnum::PDF
        );

        $this->assertFalse($pdfFileDTO->isImage());
    }


    public function testNeedsHeicConversion(): void
    {
        $heicFileDTO = new FileDTO(
            filename: 'test.heic',
            originalName: 'test.heic',
            extension: 'heic',
            fileTypeCategory: FileTypeEnum::IMAGE
        );

        $this->assertTrue($heicFileDTO->needsHeicConversion());

        $heifFileDTO = new FileDTO(
            filename: 'test.heif',
            originalName: 'test.heif',
            extension: 'heif',
            fileTypeCategory: FileTypeEnum::IMAGE
        );

        $this->assertTrue($heifFileDTO->needsHeicConversion());

        $jpgFileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            fileTypeCategory: FileTypeEnum::IMAGE
        );

        $this->assertFalse($jpgFileDTO->needsHeicConversion());

        $heicNonImageDTO = new FileDTO(
            filename: 'test.heic',
            originalName: 'test.heic',
            extension: 'heic',
            fileTypeCategory: FileTypeEnum::PDF
        );

        $this->assertFalse($heicNonImageDTO->needsHeicConversion());
    }


    public function testGetFileTypeDescription(): void
    {
        $imageFileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            extension: 'jpg',
            fileTypeCategory: FileTypeEnum::IMAGE
        );

        $this->assertSame('Images', $imageFileDTO->getFileTypeDescription());

        $pdfFileDTO = new FileDTO(
            filename: 'test.pdf',
            originalName: 'test.pdf',
            extension: 'pdf',
            fileTypeCategory: FileTypeEnum::PDF
        );

        $this->assertSame('PDF Documents', $pdfFileDTO->getFileTypeDescription());

        $unknownFileDTO = new FileDTO(
            filename: 'test.xyz',
            originalName: 'test.xyz',
            extension: 'xyz',
            fileTypeCategory: 'unknown'
        );

        $this->assertSame('Unknown File Type', $unknownFileDTO->getFileTypeDescription());
    }


    public function testWithFilename(): void
    {
        $originalFileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'original.jpg',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            fileTypeCategory: FileTypeEnum::IMAGE,
            tmpPath: '/tmp/test123',
            dataUri: null,
            size: 1024,
            uploadError: UPLOAD_ERR_OK
        );

        $newFileDTO = $originalFileDTO->withFilename('new_test.jpg');

        $this->assertSame('new_test.jpg', $newFileDTO->filename);
        $this->assertSame('original.jpg', $newFileDTO->originalName);
        $this->assertSame('jpg', $newFileDTO->extension);
        $this->assertSame('image/jpeg', $newFileDTO->mimeType);
        $this->assertSame(FileTypeEnum::IMAGE, $newFileDTO->fileTypeCategory);
        $this->assertSame('/tmp/test123', $newFileDTO->tmpPath);
        $this->assertNull($newFileDTO->dataUri);
        $this->assertSame(1024, $newFileDTO->size);
        $this->assertSame(UPLOAD_ERR_OK, $newFileDTO->uploadError);
    }


    public function testWithFileTypeCategory(): void
    {
        $originalFileDTO = new FileDTO(
            filename: 'test.jpg',
            originalName: 'original.jpg',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            fileTypeCategory: FileTypeEnum::IMAGE,
            tmpPath: '/tmp/test123',
            dataUri: null,
            size: 1024,
            uploadError: UPLOAD_ERR_OK
        );

        $newFileDTO = $originalFileDTO->withFileTypeCategory(FileTypeEnum::PDF);

        $this->assertSame('test.jpg', $newFileDTO->filename);
        $this->assertSame('original.jpg', $newFileDTO->originalName);
        $this->assertSame('jpg', $newFileDTO->extension);
        $this->assertSame('image/jpeg', $newFileDTO->mimeType);
        $this->assertSame(FileTypeEnum::PDF, $newFileDTO->fileTypeCategory);
        $this->assertSame('/tmp/test123', $newFileDTO->tmpPath);
        $this->assertNull($newFileDTO->dataUri);
        $this->assertSame(1024, $newFileDTO->size);
        $this->assertSame(UPLOAD_ERR_OK, $newFileDTO->uploadError);
    }
}
