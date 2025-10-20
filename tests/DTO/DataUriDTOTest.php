<?php

declare(strict_types=1);

namespace FileUploadService\Tests\DTO;

use FileUploadService\DTO\DataUriDTO;
use FileUploadService\Enum\FileTypeEnum;
use PHPUnit\Framework\TestCase;

class DataUriDTOTest extends TestCase
{
    public function testConstructor(): void
    {
        $dataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $fileDTO = new DataUriDTO(
            filename: 'test.jpg',
            dataUri: $dataUri,
            extension: 'jpg',
            mimeType: 'image/jpeg',
            fileTypeCategory: FileTypeEnum::IMAGE,
            size: 1024
        );

        $this->assertSame('test.jpg', $fileDTO->filename);
        $this->assertSame($dataUri, $fileDTO->dataUri);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertSame(FileTypeEnum::IMAGE, $fileDTO->fileTypeCategory);
        $this->assertSame(1024, $fileDTO->size);
    }


    public function testFromDataUri(): void
    {
        $dataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $fileDTO = DataUriDTO::fromDataUri($dataUri, 'test.jpg');

        $this->assertSame('test.jpg', $fileDTO->filename);
        $this->assertSame($dataUri, $fileDTO->dataUri);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertNull($fileDTO->fileTypeCategory);
        $this->assertNotNull($fileDTO->size);
    }


    public function testFromDataUriWithEmptyTargetFilename(): void
    {
        $dataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $fileDTO = DataUriDTO::fromDataUri($dataUri);

        $this->assertSame('data_uri_file.jpg', $fileDTO->filename);
        $this->assertSame($dataUri, $fileDTO->dataUri);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertSame('image/jpeg', $fileDTO->mimeType);
        $this->assertNull($fileDTO->fileTypeCategory);
        $this->assertNotNull($fileDTO->size);
    }


    public function testFromDataUriWithPdf(): void
    {
        $dataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        $fileDTO = DataUriDTO::fromDataUri($dataUri, 'test.pdf');

        $this->assertSame('test.pdf', $fileDTO->filename);
        $this->assertSame($dataUri, $fileDTO->dataUri);
        $this->assertSame('pdf', $fileDTO->extension);
        $this->assertSame('application/pdf', $fileDTO->mimeType);
        $this->assertNull($fileDTO->fileTypeCategory);
        $this->assertNotNull($fileDTO->size);
    }


    public function testFromDataUriWithInvalidDataUri(): void
    {
        $fileDTO = DataUriDTO::fromDataUri('invalid-data-uri', 'test.jpg');

        $this->assertSame('test.jpg', $fileDTO->filename);
        $this->assertSame('invalid-data-uri', $fileDTO->dataUri);
        $this->assertSame('jpg', $fileDTO->extension);
        $this->assertNull($fileDTO->mimeType);
        $this->assertNull($fileDTO->fileTypeCategory);
        $this->assertNull($fileDTO->size);
    }


    public function testGetFormattedSize(): void
    {
        $dataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $fileDTO = DataUriDTO::fromDataUri($dataUri, 'test.jpg');

        $this->assertIsString($fileDTO->getFormattedSize());
        $this->assertNotSame('Unknown size', $fileDTO->getFormattedSize());

        $fileDTONoSize = new DataUriDTO(
            filename: 'test.jpg',
            dataUri: 'data:image/jpeg;base64,',
            extension: 'jpg',
            size: null
        );

        $this->assertSame('Unknown size', $fileDTONoSize->getFormattedSize());
    }


    public function testGetFormattedSizeWithDifferentSizes(): void
    {
        // Test bytes
        $fileDTO = new DataUriDTO(
            filename: 'test.jpg',
            dataUri: 'data:image/jpeg;base64,',
            extension: 'jpg',
            size: 500
        );
        $this->assertSame('500 B', $fileDTO->getFormattedSize());

        // Test MB
        $fileDTO = new DataUriDTO(
            filename: 'test.jpg',
            dataUri: 'data:image/jpeg;base64,',
            extension: 'jpg',
            size: 1048576 // 1 MB
        );
        $this->assertSame('1 MB', $fileDTO->getFormattedSize());

        // Test GB
        $fileDTO = new DataUriDTO(
            filename: 'test.jpg',
            dataUri: 'data:image/jpeg;base64,',
            extension: 'jpg',
            size: 1073741824 // 1 GB
        );
        $this->assertSame('1 GB', $fileDTO->getFormattedSize());
    }
}
