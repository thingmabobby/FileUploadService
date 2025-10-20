<?php

declare(strict_types=1);

namespace FileUploadService\Tests\DTO;

use FileUploadService\DTO\FileUploadDTO;
use FileUploadService\Enum\FileTypeEnum;
use PHPUnit\Framework\TestCase;

class FileUploadDTOTest extends TestCase
{
    public function testConstructor(): void
    {
        $fileDTO = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'original.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            fileTypeCategory: FileTypeEnum::IMAGE,
            size: 1024,
            uploadError: UPLOAD_ERR_OK
        );

        $this->assertSame('test.jpg', $fileDTO->filename);
        $this->assertSame('original.jpg', $fileDTO->originalName);
        $this->assertSame('/tmp/test123', $fileDTO->tmpPath);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertSame(FileTypeEnum::IMAGE, $fileDTO->fileTypeCategory);
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

        $fileDTO = FileUploadDTO::fromFilesArray($filesArray, 'target.jpg');

        $this->assertSame('target.jpg', $fileDTO->filename);
        $this->assertSame('test.jpg', $fileDTO->originalName);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('/tmp/test123', $fileDTO->tmpPath);
        $this->assertSame(1024, $fileDTO->size);
        $this->assertSame(UPLOAD_ERR_OK, $fileDTO->uploadError);
    }


    public function testFromFilesArrayWithEmptyTargetFilename(): void
    {
        $filesArray = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test123',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $fileDTO = FileUploadDTO::fromFilesArray($filesArray);

        $this->assertSame('test.jpg', $fileDTO->filename);
        $this->assertSame('test.jpg', $fileDTO->originalName);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('/tmp/test123', $fileDTO->tmpPath);
        $this->assertSame(1024, $fileDTO->size);
        $this->assertSame(UPLOAD_ERR_OK, $fileDTO->uploadError);
    }


    public function testIsUploadSuccessful(): void
    {
        $fileDTO = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            uploadError: UPLOAD_ERR_OK
        );

        $this->assertTrue($fileDTO->isUploadSuccessful());

        $fileDTOWithError = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            uploadError: UPLOAD_ERR_INI_SIZE
        );

        $this->assertFalse($fileDTOWithError->isUploadSuccessful());
    }


    public function testGetFormattedSize(): void
    {
        $fileDTO = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            size: 1024
        );

        $this->assertSame('1 KB', $fileDTO->getFormattedSize());

        $fileDTONoSize = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            size: null
        );

        $this->assertSame('Unknown size', $fileDTONoSize->getFormattedSize());
    }


    public function testGetFormattedSizeWithDifferentSizes(): void
    {
        // Test bytes
        $fileDTO = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            size: 500
        );
        $this->assertSame('500 B', $fileDTO->getFormattedSize());

        // Test MB
        $fileDTO = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            size: 1048576 // 1 MB
        );
        $this->assertSame('1 MB', $fileDTO->getFormattedSize());

        // Test GB
        $fileDTO = new FileUploadDTO(
            filename: 'test.jpg',
            originalName: 'test.jpg',
            tmpPath: '/tmp/test123',
            extension: 'jpg',
            size: 1073741824 // 1 GB
        );
        $this->assertSame('1 GB', $fileDTO->getFormattedSize());
    }
}
