<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FileUploadSuccess;
use PHPUnit\Framework\TestCase;

class FileUploadSuccessTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        $upload = new FileUploadSuccess(
            originalFilename: 'IMG_1234.HEIC',
            requestedFilename: 'photo.heic',
            storedFilename: 'photo.jpg',
            storedPath: 'memory://obj-9f3c',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            sizeBytes: 2048,
            wasConverted: true,
            inputIndex: 2,
        );

        $this->assertSame('IMG_1234.HEIC', $upload->originalFilename);
        $this->assertSame('photo.heic', $upload->requestedFilename);
        $this->assertSame('photo.jpg', $upload->storedFilename);
        $this->assertSame('memory://obj-9f3c', $upload->storedPath);
        $this->assertSame('jpg', $upload->extension);
        $this->assertSame('image/jpeg', $upload->mimeType);
        $this->assertSame(2048, $upload->sizeBytes);
        $this->assertTrue($upload->wasConverted);
        $this->assertSame(2, $upload->inputIndex);
    }


    public function testOriginalFilenameCanBeNullForDataUri(): void
    {
        $upload = new FileUploadSuccess(
            originalFilename: null,
            requestedFilename: 'photo.jpg',
            storedFilename: 'photo.jpg',
            storedPath: '/uploads/photo.jpg',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            sizeBytes: 100,
            wasConverted: false,
            inputIndex: 0,
        );

        $this->assertNull($upload->originalFilename);
        $this->assertFalse($upload->wasConverted);
    }


    public function testStoredFilenameIsIndependentOfStoredPathBasename(): void
    {
        $upload = new FileUploadSuccess(
            originalFilename: 'client.jpg',
            requestedFilename: 'photo.jpg',
            storedFilename: 'photo.jpg',
            storedPath: 'bucket/sha256-deadbeef',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            sizeBytes: 50,
            wasConverted: false,
            inputIndex: 0,
        );

        $this->assertSame('photo.jpg', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
        $this->assertSame('bucket/sha256-deadbeef', $upload->storedPath);
        $this->assertNotSame(basename($upload->storedPath), $upload->storedFilename);
    }
}
