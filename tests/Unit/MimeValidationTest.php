<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\FileServiceValidator;
use FileUploadService\FileUploadSave;
use FileUploadService\FilesystemSaver;
use FileUploadService\FileUploadError;
use FileUploadService\DTO\FileUploadDTO;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MIME validation behavior when all files are allowed
 * 
 * @package FileUploadService\Tests\Unit
 */
class MimeValidationTest extends TestCase
{
    private FileServiceValidator $validator;
    private FileUploadSave $fileUploadSave;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mime_validation_test_' . uniqid();
        mkdir($this->tempDir, 0700, true);

        $this->validator = new FileServiceValidator();
        $fileSaver = new FilesystemSaver($this->tempDir);
        $this->fileUploadSave = new FileUploadSave($this->validator, $fileSaver);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->tempDir);
    }

    /**
     * Test that MIME validation is strict when specific file types are allowed
     */
    public function testStrictMimeValidationWhenSpecificTypesAllowed(): void
    {
        // Create a real PNG image file with .txt extension (should fail MIME validation)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $fakeImagePath = $this->tempDir . '/real_image_as_txt.txt';
        file_put_contents($fakeImagePath, $pngData);

        $fileDTO = FileUploadDTO::fromFilesArray([
            'name' => 'real_image_as_txt.txt',
            'type' => 'text/plain',
            'tmp_name' => $fakeImagePath,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($pngData)
        ], 'real_image_as_txt.txt');

        // Test with specific file types allowed (should use strict MIME validation)
        $result = $this->fileUploadSave->processFileUpload(
            $fileDTO,
            '',
            false,
            ['txt'] // Only .txt files allowed, but this is actually a PNG
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File type not allowed', $result['error']->getDescription());
    }

    /**
     * Test that MIME validation is relaxed when all files are allowed
     */
    public function testRelaxedMimeValidationWhenAllFilesAllowed(): void
    {
        // Create a fake image file with .txt extension (should pass when all files allowed)
        $fakeImagePath = $this->tempDir . '/fake_image_allowed.txt';
        file_put_contents($fakeImagePath, 'fake image content');

        $fileDTO = FileUploadDTO::fromFilesArray([
            'name' => 'fake_image_allowed.txt',
            'type' => 'text/plain',
            'tmp_name' => $fakeImagePath,
            'error' => UPLOAD_ERR_OK,
            'size' => 20
        ], 'fake_image_allowed.txt');

        // Test with all files allowed (should use relaxed MIME validation)
        $result = $this->fileUploadSave->processFileUpload(
            $fileDTO,
            '',
            true, // Allow overwrite
            ['all'] // All files allowed
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('filePath', $result);
    }

    /**
     * Test that MIME validation is strict when no file types are specified (default security)
     */
    public function testStrictMimeValidationWhenNoTypesSpecified(): void
    {
        // Create a real PNG file with .txt extension
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $fakeImagePath = $this->tempDir . '/fake_image_empty.txt';
        file_put_contents($fakeImagePath, $pngContent);

        $fileDTO = FileUploadDTO::fromFilesArray([
            'name' => 'fake_image_empty.txt',
            'type' => 'text/plain',
            'tmp_name' => $fakeImagePath,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($pngContent)
        ], 'fake_image_empty.txt');

        // Test with empty allowed file types (should use strict MIME validation for security)
        $result = $this->fileUploadSave->processFileUpload(
            $fileDTO,
            '',
            true, // Allow overwrite
            [] // Empty array means strict validation (default security)
        );

        $this->assertFalse($result['success']);
        $this->assertInstanceOf(FileUploadError::class, $result['error']);
    }

    /**
     * Test that unknown extensions are allowed when all files are allowed
     */
    public function testUnknownExtensionsAllowedWhenAllFilesAllowed(): void
    {
        // Create a file with unknown extension
        $unknownFile = $this->tempDir . '/unknown_file_unique.xyz';
        file_put_contents($unknownFile, 'some content');

        $fileDTO = FileUploadDTO::fromFilesArray([
            'name' => 'unknown_file_unique.xyz',
            'type' => 'application/octet-stream',
            'tmp_name' => $unknownFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 12
        ], 'unknown_file_unique.xyz');

        // Test with all files allowed
        $result = $this->fileUploadSave->processFileUpload(
            $fileDTO,
            '',
            true, // Allow overwrite
            ['all']
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('filePath', $result);
    }

    /**
     * Remove directory and all its contents recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->removeDirectory($file);
            }
        }
        rmdir($dir);
    }
}
