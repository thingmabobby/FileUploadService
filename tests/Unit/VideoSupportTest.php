<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\SupportedFileTypesEnum;
use FileUploadService\FileServiceValidator;
use FileUploadService\FileUploadService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for video file support
 * 
 * @package FileUploadService\Tests\Unit
 */
class VideoSupportTest extends TestCase
{
    private FileServiceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FileServiceValidator();
    }

    /**
     * Test that video file types are properly defined in SupportedFileTypesEnum
     */
    public function testVideoFileTypesDefined(): void
    {
        $videoTypes = SupportedFileTypesEnum::getTypesForCategory(FileTypeEnum::VIDEO);

        $this->assertNotEmpty($videoTypes);

        // Check that common video formats are included
        $extensions = array_map(fn($type) => $type->getStandardExtension(), $videoTypes);

        $this->assertContains('mp4', $extensions);
        $this->assertContains('avi', $extensions);
        $this->assertContains('mov', $extensions);
        $this->assertContains('webm', $extensions);
        $this->assertContains('mkv', $extensions);
    }

    /**
     * Test that video MIME types are correctly mapped
     */
    public function testVideoMimeTypes(): void
    {
        $mp4Type = SupportedFileTypesEnum::findByExtension('mp4');
        $this->assertNotNull($mp4Type);
        $this->assertSame('video/mp4', $mp4Type->getMimeType());

        $aviType = SupportedFileTypesEnum::findByExtension('avi');
        $this->assertNotNull($aviType);
        $this->assertSame('video/x-msvideo', $aviType->getMimeType());

        $webmType = SupportedFileTypesEnum::findByExtension('webm');
        $this->assertNotNull($webmType);
        $this->assertSame('video/webm', $webmType->getMimeType());
    }

    /**
     * Test that FileServiceValidator recognizes video file types
     */
    public function testValidatorRecognizesVideoTypes(): void
    {
        $videoTypes = $this->validator->getSupportedVideoTypes();

        $this->assertNotEmpty($videoTypes);
        $this->assertArrayHasKey('mp4', $videoTypes);
        $this->assertArrayHasKey('avi', $videoTypes);
        $this->assertArrayHasKey('mov', $videoTypes);
    }

    /**
     * Test that FileServiceValidator can categorize video extensions
     */
    public function testValidatorCategorizesVideoExtensions(): void
    {
        $this->assertSame(FileTypeEnum::VIDEO, $this->validator->getFileTypeCategoryFromExtension('mp4'));
        $this->assertSame(FileTypeEnum::VIDEO, $this->validator->getFileTypeCategoryFromExtension('avi'));
        $this->assertSame(FileTypeEnum::VIDEO, $this->validator->getFileTypeCategoryFromExtension('mov'));
        $this->assertSame(FileTypeEnum::VIDEO, $this->validator->getFileTypeCategoryFromExtension('webm'));
        $this->assertSame(FileTypeEnum::VIDEO, $this->validator->getFileTypeCategoryFromExtension('mkv'));
    }

    /**
     * Test that FileUploadService does NOT include video in default allowed types
     */
    public function testFileUploadServiceDoesNotIncludeVideoByDefault(): void
    {
        $service = new FileUploadService();
        $allowedTypes = $service->getAllowedFileTypes();

        $this->assertNotContains('video', $allowedTypes);
    }

    /**
     * Test that video files can be validated (basic test with fake data)
     */
    public function testVideoFileValidation(): void
    {
        // Create a temporary file with fake MP4 header
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        // Write fake MP4 header (ftyp box)
        fwrite($tempFile, "ftypmp41\x00\x00\x00\x00");

        // Test validation
        $isValid = $this->validator->validateVideoFile($tempPath, 'mp4');

        fclose($tempFile);

        $this->assertTrue($isValid);
    }

    /**
     * Test that unknown video extensions are handled gracefully
     */
    public function testUnknownVideoExtensionHandling(): void
    {
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        // Write some fake data
        fwrite($tempFile, "fake video data");

        // Test validation with unknown extension
        $isValid = $this->validator->validateVideoFile($tempPath, 'unknown');

        fclose($tempFile);

        // Should return true for unknown extensions (graceful handling)
        $this->assertTrue($isValid);
    }

    /**
     * Test video file type category is properly handled in FileUploadService
     */
    public function testVideoCategoryInFileUploadService(): void
    {
        $service = new FileUploadService();

        // Test that video category is NOT allowed by default
        $this->assertFalse($service->isFileTypeCategoryAllowed('video'));

        // Test that video extensions are NOT allowed by default
        $this->assertFalse($service->isFileTypeAllowedByExtension('mp4'));
        $this->assertFalse($service->isFileTypeAllowedByExtension('avi'));
        $this->assertFalse($service->isFileTypeAllowedByExtension('mov'));
    }
}
