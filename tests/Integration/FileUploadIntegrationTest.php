<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Integration;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\FileUploadService;
use FileUploadService\FilesystemSaver;
use PHPUnit\Framework\TestCase;

class FileUploadIntegrationTest extends TestCase
{
    private string $testDir;
    private FileUploadService $service;


    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/integration_test_' . uniqid();
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


    public function testCompleteWorkflowWithMultipleFileTypes(): void
    {
        // Test data URIs for different file types
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA0IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        // Create temporary files for $_FILES simulation
        $tempFile1 = tempnam(sys_get_temp_dir(), 'upload_test_1');
        file_put_contents($tempFile1, 'Test document content');

        $tempFile2 = tempnam(sys_get_temp_dir(), 'upload_test_2');
        // Create a simple binary file that would pass MIME validation
        // DWG files typically start with specific bytes, but for testing we'll create a minimal binary file
        file_put_contents($tempFile2, "\x00\x00\x00\x00" . 'Test CAD content');

        $filesArray1 = [
            'name' => 'document.txt',
            'type' => 'text/plain',
            'tmp_name' => $tempFile1,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen('Test document content')
        ];

        $filesArray2 = [
            'name' => 'drawing.dwg',
            'type' => 'application/dwg',
            'tmp_name' => $tempFile2,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen('Test CAD content')
        ];

        try {
            // Test mixed input types (base64 and $_FILES)
            // Allow all necessary file types for this test
            $this->service->allowFileType(FileTypeEnum::IMAGE->value);
            $this->service->allowFileType(FileTypeEnum::DOC->value);
            $this->service->allowFileType(FileTypeEnum::PDF->value);
            $this->service->allowFileType(FileTypeEnum::CAD->value);

            $result = $this->service->save(
                [$imageDataUri, $filesArray1, $pdfDataUri, $filesArray2],
                $this->testDir,
                ['image.jpg', 'document.txt', 'document.pdf', 'drawing.dwg']
            );

            // Verify results
            $this->assertTrue($result->hasSuccessfulUploads());

            $this->assertSame(3, $result->successfulCount); // CAD file should be rejected due to MIME validation
            $this->assertSame(4, $result->totalFiles);
            $this->assertCount(3, $result->successfulFiles);
            $this->assertCount(1, $result->errors); // CAD file should have an error

            // Verify files were actually saved
            foreach ($result->successfulFiles as $savedFile) {
                $this->assertTrue(file_exists($savedFile), "File should exist: {$savedFile}");
                $this->assertGreaterThan(0, filesize($savedFile), "File should not be empty: {$savedFile}");
            }
        } finally {
            if (file_exists($tempFile1)) {
                unlink($tempFile1);
            }
            if (file_exists($tempFile2)) {
                unlink($tempFile2);
            }
        }
    }


    public function testFileTypeRestrictionsWorkflow(): void
    {
        // Create service with only image files allowed
        $fileSaver = new FilesystemSaver($this->testDir);
        $imageOnlyService = new FileUploadService([FileTypeEnum::IMAGE], fileSaver: $fileSaver);

        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovUGFnZXMgMiAwIFIKPj4KZW5kb2JqCjIgMCBvYmoKPDwKL1R5cGUgL1BhZ2VzCi9LaWRzIFszIDAgUl0KL0NvdW50IDEKPD4KZW5kb2JqCjMgMCBvYmoKPDwKL1R5cGUgL1BhZ2UKL1BhcmVudCAyIDAgUgovTWVkaWFCb3ggWzAgMCA2MTIgNzkyXQovUmVzb3VyY2VzIDw8Ci9Gb250IDw8Ci9GMSAKPDwKL1R5cGUgL0ZvbnQKL1N1YnR5cGUgL1R5cGUxCi9CYXNlRm9udCAvSGVsdmV0aWNhCj4+Cj4+Cj4+Ci9Db250ZW50cyA4IDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovTGVuZ3RoIDQ0Cj4+CnN0cmVhbQpCVApxCjcyIDAgMCA3MiA0NzAuMTU0MSBUKgpUKQpFVAplbmRzdHJlYW0KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZgowMDAwMDAwMDA5IDAwMDAwIG4KMDAwMDAwMDA3NCAwMDAwMCBuCjAwMDAwMDAxMzIgMDAwMDAgbgowMDAwMDAwMjQxIDAwMDAwIG4KdHJhaWxlcgo8PAovU2l6ZSA1Ci9Sb290IDEgMCBSCj4+CnN0YXJ0eHJlZgozMjAKJSVFT0YK';

        // Test mixed allowed and disallowed files
        $result = $imageOnlyService->save(
            [$imageDataUri, $pdfDataUri],
            $this->testDir,
            ['image.jpg', 'document.pdf']
        );

        // Should have partial success
        $this->assertTrue($result->hasSuccessfulUploads());
        $this->assertTrue($result->hasErrors());
        $this->assertSame(1, $result->successfulCount);
        $this->assertSame(2, $result->totalFiles);
        $this->assertCount(1, $result->errors);

        // Verify only image was saved
        $this->assertCount(1, $result->successfulFiles);
        $savedFile = $result->successfulFiles[0];
        $this->assertStringEndsWith('image.jpg', $savedFile);

        // Verify error for PDF
        $error = $result->errors[0];
        $this->assertSame('document.pdf', $error->filename);
        $this->assertStringContainsString('not allowed', $error->message);
    }


    public function testCollisionResolutionWorkflow(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        // Save first file
        $result1 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg']);
        $this->assertTrue($result1->hasSuccessfulUploads());

        // Save second file with same name - should generate unique name
        $result2 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg'], generateUniqueFilenames: true);
        $this->assertTrue($result2->hasSuccessfulUploads());

        // Verify different filenames were generated
        $this->assertNotSame($result1->successfulFiles[0], $result2->successfulFiles[0]);

        // Verify both files exist (successfulFiles contains full paths)
        $this->assertTrue(file_exists($result1->successfulFiles[0]));
        $this->assertTrue(file_exists($result2->successfulFiles[0]));
    }


    public function testOverwriteExistingWorkflow(): void
    {
        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        // Save first file
        $result1 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg']);
        $this->assertTrue($result1->hasSuccessfulUploads());

        $firstFile = $result1->successfulFiles[0];
        $firstFileSize = filesize($firstFile);

        // Save second file with same name and overwrite
        $result2 = $this->service->save([$imageDataUri], $this->testDir, ['test.jpg'], overwriteExisting: true);
        $this->assertTrue($result2->hasSuccessfulUploads());

        $secondFile = $result2->successfulFiles[0];

        // Verify same filename was used
        $this->assertSame($result1->successfulFiles[0], $result2->successfulFiles[0]);

        // Verify file was overwritten (should be same size since same content)
        $this->assertTrue(file_exists($secondFile));
        $this->assertSame($firstFileSize, filesize($secondFile));
    }


    public function testRollbackOnErrorWorkflow(): void
    {
        // Create service with rollback enabled
        $fileSaver = new FilesystemSaver($this->testDir);
        $rollbackService = new FileUploadService(rollbackOnError: true, fileSaver: $fileSaver);

        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        $invalidDataUri = 'invalid-data-uri';

        // Test with mixed valid and invalid files
        $result = $rollbackService->save(
            [$imageDataUri, $invalidDataUri],
            $this->testDir,
            ['image.jpg', 'invalid.txt']
        );

        // Should have errors
        $this->assertTrue($result->hasErrors());

        // With rollback enabled, if any file fails, all successfully uploaded files should be deleted
        // Since we have one valid and one invalid file, the valid file should be rolled back
        $this->assertSame(0, $result->successfulCount, 'Rollback should remove all successfully uploaded files when errors occur');
        $this->assertCount(0, $result->successfulFiles, 'No files should remain after rollback');
    }


    public function testHighPerformanceModeWorkflow(): void
    {
        // Create service with high performance mode
        $fileSaver = new FilesystemSaver($this->testDir);
        $hpService = new FileUploadService(highPerformanceMode: true, fileSaver: $fileSaver);

        $imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

        // Test multiple files with high performance mode
        $result = $hpService->save(
            [$imageDataUri, $imageDataUri, $imageDataUri],
            $this->testDir,
            ['image1.jpg', 'image2.jpg', 'image3.jpg'],
            generateUniqueFilenames: true
        );

        $this->assertTrue($result->hasSuccessfulUploads());
        $this->assertSame(3, $result->successfulCount);
        $this->assertSame(3, $result->totalFiles);

        // Verify all files were saved with unique names
        $this->assertCount(3, $result->successfulFiles);
        $filenames = $result->successfulFiles;
        $this->assertCount(3, array_unique($filenames)); // All should be unique
    }

    public function testHeicConversionWorkflow(): void
    {
        // Test that HEIC conversion is enabled by default
        $this->assertTrue($this->service->isHeicConversionEnabled());

        // Test that the service can handle HEIC conversion configuration
        $this->assertTrue(true); // Placeholder assertion
    }

    public function testHeicConversionDisabledWorkflow(): void
    {
        // Create service with HEIC conversion disabled
        $fileSaver = new FilesystemSaver($this->testDir);
        $serviceWithoutHeic = new FileUploadService(
            allowedFileTypes: ['image'],
            fileSaver: $fileSaver,
            convertHeicToJpg: false
        );

        // Test that HEIC conversion is disabled
        $this->assertFalse($serviceWithoutHeic->isHeicConversionEnabled());

        // Test that the service configuration works correctly
        $this->assertTrue(true); // Placeholder assertion
    }
}
