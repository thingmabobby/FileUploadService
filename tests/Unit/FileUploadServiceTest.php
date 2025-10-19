<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\DTO\FileDTO;
use FileUploadService\Enum\CollisionStrategyEnum;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\FileUploadService;
use FileUploadService\FilesystemSaver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileUploadServiceTest extends TestCase
{
    private string $testDir;
    private FileUploadService $service;


    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/file_upload_service_test_' . uniqid();
        mkdir($this->testDir, 0777, true);

        $fileSaver = new FilesystemSaver($this->testDir);
        $this->service = new FileUploadService(fileSaver: $fileSaver);
    }


    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }

        parent::tearDown();
    }


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


    public function testConstructorWithDefaultValues(): void
    {
        $service = new FileUploadService();

        $this->assertInstanceOf(FileUploadService::class, $service);
        $this->assertTrue($service->isRollbackOnErrorEnabled() === false);
        $this->assertTrue($service->isHeicConversionEnabled() === true);
    }


    public function testConstructorWithCustomValues(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir, 0755, false);
        $service = new FileUploadService(
            allowedFileTypes: [FileTypeEnum::IMAGE],
            fileSaver: $fileSaver,
            createDirectory: false,
            directoryPermissions: 0755,
            rollbackOnError: true,
            collisionStrategy: CollisionStrategyEnum::UUID,
            highPerformanceMode: true,
            convertHeicToJpg: false
        );

        $this->assertInstanceOf(FileUploadService::class, $service);
        $this->assertTrue($service->isRollbackOnErrorEnabled());
        $this->assertFalse($service->isHeicConversionEnabled());
    }


    public function testSetAllowedFileTypes(): void
    {
        $this->service->setAllowedFileTypes([FileTypeEnum::PDF, 'custom']);

        $allowedTypes = $this->service->getAllowedFileTypes();

        $this->assertContains('pdf', $allowedTypes);
        $this->assertContains('custom', $allowedTypes);
    }


    public function testSetAllowedFileTypesWithEmptyArray(): void
    {
        $this->service->setAllowedFileTypes([]);

        $allowedTypes = $this->service->getAllowedFileTypes();

        // Should fall back to defaults
        $this->assertContains('image', $allowedTypes);
        $this->assertContains('pdf', $allowedTypes);
        $this->assertContains('cad', $allowedTypes);
    }


    public function testGetAllowedFileTypes(): void
    {
        $allowedTypes = $this->service->getAllowedFileTypes();

        $this->assertIsArray($allowedTypes);
        $this->assertContains('image', $allowedTypes);
        $this->assertContains('pdf', $allowedTypes);
        $this->assertContains('cad', $allowedTypes);
    }


    public function testIsRollbackOnErrorEnabled(): void
    {
        $this->assertFalse($this->service->isRollbackOnErrorEnabled());

        $serviceWithRollback = new FileUploadService(rollbackOnError: true);
        $this->assertTrue($serviceWithRollback->isRollbackOnErrorEnabled());
    }


    public function testIsHeicConversionEnabled(): void
    {
        $this->assertTrue($this->service->isHeicConversionEnabled());

        $serviceWithoutHeic = new FileUploadService(convertHeicToJpg: false);
        $this->assertFalse($serviceWithoutHeic->isHeicConversionEnabled());
    }


    public function testIsFileTypeCategoryAllowed(): void
    {
        $this->assertTrue($this->service->isFileTypeCategoryAllowed('image'));
        $this->assertTrue($this->service->isFileTypeCategoryAllowed('pdf'));
        $this->assertTrue($this->service->isFileTypeCategoryAllowed('cad'));
        $this->assertFalse($this->service->isFileTypeCategoryAllowed('unknown'));
    }


    public function testAllowFileType(): void
    {
        $this->service->allowFileType('custom');

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertContains('custom', $allowedTypes);
    }


    public function testAllowFileTypeArray(): void
    {
        $this->service->allowFileType(['custom1', 'custom2']);

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertContains('custom1', $allowedTypes);
        $this->assertContains('custom2', $allowedTypes);
    }


    public function testDisallowFileType(): void
    {
        $this->service->disallowFileType('image');

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertNotContains('image', $allowedTypes);
    }


    public function testDisallowFileTypeArray(): void
    {
        $this->service->disallowFileType(['image', 'pdf']);

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertNotContains('image', $allowedTypes);
        $this->assertNotContains('pdf', $allowedTypes);
    }


    public function testIsUnrestricted(): void
    {
        $this->assertFalse($this->service->isUnrestricted());

        $fileSaver = new FilesystemSaver($this->testDir);
        $serviceWithAll = new FileUploadService([FileTypeEnum::ALL], fileSaver: $fileSaver);
        $this->assertTrue($serviceWithAll->isUnrestricted());
    }


    public function testIsFileTypeAllowedByExtension(): void
    {
        $this->assertTrue($this->service->isFileTypeAllowedByExtension('jpg'));
        $this->assertTrue($this->service->isFileTypeAllowedByExtension('pdf'));
        $this->assertTrue($this->service->isFileTypeAllowedByExtension('dwg'));
        $this->assertFalse($this->service->isFileTypeAllowedByExtension('exe'));
    }


    public function testIsFileTypeAllowedByExtensionWithAllAllowed(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);
        $service = new FileUploadService([FileTypeEnum::ALL], fileSaver: $fileSaver);

        $this->assertTrue($service->isFileTypeAllowedByExtension('exe'));
        $this->assertTrue($service->isFileTypeAllowedByExtension('unknown'));
    }


    public function testGetFileTypeCategoryFromExtension(): void
    {
        $this->assertSame(FileTypeEnum::IMAGE, $this->service->getFileTypeCategoryFromExtension('jpg'));
        $this->assertSame(FileTypeEnum::PDF, $this->service->getFileTypeCategoryFromExtension('pdf'));
        $this->assertSame(FileTypeEnum::CAD, $this->service->getFileTypeCategoryFromExtension('dwg'));
        $this->assertSame('unknown', $this->service->getFileTypeCategoryFromExtension('unknown'));
    }


    public function testCleanFilename(): void
    {
        $this->assertSame('test-file', FileUploadService::cleanFilename('test-file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test/file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test:file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test*file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test?file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test"file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test<file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test>file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test|file'));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test#file'));
    }


    public function testCleanFilenameRemoveUnderscores(): void
    {
        $this->assertSame('testfile', FileUploadService::cleanFilename('test_file', removeUnderscores: true));
        $this->assertSame('test_file', FileUploadService::cleanFilename('test_file', removeUnderscores: false));
    }


    public function testCleanFilenameRemoveSpaces(): void
    {
        $this->assertSame('testfile', FileUploadService::cleanFilename('test file', removeSpaces: true));
        $this->assertSame('test file', FileUploadService::cleanFilename('test file', removeSpaces: false));
    }


    public function testCleanFilenameRemoveCustomChars(): void
    {
        $this->assertSame('testfile', FileUploadService::cleanFilename('test-file', removeCustomChars: ['-']));
        $this->assertSame('testfile', FileUploadService::cleanFilename('test@file', removeCustomChars: ['@']));
    }


    public function testCleanFilenameEmptyResult(): void
    {
        $this->assertSame('unnamed', FileUploadService::cleanFilename(''));
        $this->assertSame('unnamed', FileUploadService::cleanFilename('   '));
        $this->assertSame('unnamed', FileUploadService::cleanFilename('\\/:*?"<>|#'));
    }


    public function testGetAvailableFileTypeCategories(): void
    {
        $categories = FileUploadService::getAvailableFileTypeCategories();

        $this->assertIsArray($categories);
        $this->assertContains(FileTypeEnum::IMAGE, $categories);
        $this->assertContains(FileTypeEnum::PDF, $categories);
        $this->assertContains(FileTypeEnum::CAD, $categories);
        $this->assertContains(FileTypeEnum::DOC, $categories);
        $this->assertContains(FileTypeEnum::ARCHIVE, $categories);
        $this->assertContains(FileTypeEnum::ALL, $categories);
    }


    public function testGetRestrictionDescription(): void
    {
        $description = $this->service->getRestrictionDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('files', $description);
    }


    public function testGetRestrictionDescriptionUnrestricted(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);
        $service = new FileUploadService([FileTypeEnum::ALL], fileSaver: $fileSaver);

        $description = $service->getRestrictionDescription();

        $this->assertSame('All file types allowed', $description);
    }


    public function testGetRestrictionDescriptionSingleType(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);
        $service = new FileUploadService([FileTypeEnum::IMAGE], fileSaver: $fileSaver);

        $description = $service->getRestrictionDescription();

        $this->assertSame('Image files only', $description);
    }


    public function testGetRestrictionDescriptionTwoTypes(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);
        $service = new FileUploadService([FileTypeEnum::IMAGE, FileTypeEnum::PDF], fileSaver: $fileSaver);

        $description = $service->getRestrictionDescription();

        $this->assertSame('Image and pdf files', $description);
    }


    public function testGetRestrictionDescriptionMultipleTypes(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);
        $service = new FileUploadService([FileTypeEnum::IMAGE, FileTypeEnum::PDF, FileTypeEnum::CAD], fileSaver: $fileSaver);

        $description = $service->getRestrictionDescription();

        $this->assertStringContainsString('Image', $description);
        $this->assertStringContainsString('pdf', $description);
        $this->assertStringContainsString('cad', $description);
        $this->assertStringContainsString('files', $description);
    }


    public function testSaveWithBase64DataUri(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $result = $this->service->save(
            [$imageDataUri],
            $this->testDir,
            ['test.jpg']
        );

        $this->assertTrue($result->hasSuccessfulUploads());
        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->successfulCount);
        $this->assertSame(1, $result->totalFiles);

        $savedFile = $result->successfulFiles[0];
        $this->assertStringEndsWith('test.jpg', $savedFile);
        $this->assertTrue(file_exists($savedFile));
    }


    public function testSaveWithFileUpload(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test');
        file_put_contents($tempFile, 'Test file content');

        $filesArray = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen('Test file content')
        ];

        try {
            // Allow document types for this test
            $this->service->allowFileType(FileTypeEnum::DOC->value);

            $result = $this->service->save(
                [$filesArray],
                $this->testDir,
                ['test.txt']
            );

            $this->assertTrue($result->hasSuccessfulUploads());
            $this->assertFalse($result->hasErrors());
            $this->assertSame(1, $result->successfulCount);
            $this->assertSame(1, $result->totalFiles);

            $savedFile = $result->successfulFiles[0];
            $this->assertStringEndsWith('test.txt', $savedFile);
            $this->assertTrue(file_exists($savedFile));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }


    public function testSaveWithMultipleFiles(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test');
        file_put_contents($tempFile, 'Test file content');

        $filesArray = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen('Test file content')
        ];

        try {
            // Allow document types for this test
            $this->service->allowFileType(FileTypeEnum::DOC->value);

            $result = $this->service->save(
                [$imageDataUri, $filesArray],
                $this->testDir,
                ['image.jpg', 'document.txt']
            );

            $this->assertTrue($result->hasSuccessfulUploads());
            $this->assertSame(2, $result->successfulCount);
            $this->assertSame(2, $result->totalFiles);

            $this->assertCount(2, $result->successfulFiles);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }


    public function testSaveWithOverwriteExisting(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        // Save first file
        $result1 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg']);
        $this->assertTrue($result1->hasSuccessfulUploads());

        // Save with same filename and overwrite
        $result2 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg'], overwriteExisting: true);
        $this->assertTrue($result2->hasSuccessfulUploads());
    }


    public function testSaveWithGenerateUniqueFilenames(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        // Save first file
        $result1 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg']);
        $this->assertTrue($result1->hasSuccessfulUploads());

        // Save with same filename and generate unique names
        $result2 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg'], generateUniqueFilenames: true);
        $this->assertTrue($result2->hasSuccessfulUploads());

        // Should have different filenames
        $this->assertNotSame($result1->successfulFiles[0], $result2->successfulFiles[0]);
    }


    public function testSaveWithFileTypeRestrictions(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);
        $service = new FileUploadService([FileTypeEnum::IMAGE], fileSaver: $fileSaver);

        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        // Image should be allowed
        $result = $service->save([$imageDataUri], $this->testDir, ['test.jpg']);
        $this->assertTrue($result->hasSuccessfulUploads());

        // PDF should be rejected
        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        $result = $service->save([$pdfDataUri], $this->testDir, ['test.pdf']);
        $this->assertFalse($result->hasSuccessfulUploads());
        $this->assertTrue($result->hasErrors());
    }


    public function testSaveWithNonExistentDirectory(): void
    {
        $nonExistentDir = $this->testDir . '/non_existent';

        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $result = $this->service->save([$imageDataUri], $nonExistentDir, ['test.jpg']);

        $this->assertTrue($result->hasSuccessfulUploads());
        $this->assertTrue(is_dir($nonExistentDir));
    }


    public function testSaveWithDirectoryCreationDisabled(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir, 0775, false);
        $service = new FileUploadService(createDirectory: false, fileSaver: $fileSaver);
        $nonExistentDir = $this->testDir . '/non_existent';

        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Upload directory does not exist:');

        $service->save([$imageDataUri], $nonExistentDir, ['test.jpg']);
    }
}
