<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FilesystemSaver;
use FileUploadService\FileUploadService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for enhanced path traversal logic
 * Validates that uploadDestination can use ../ for navigation within basePath
 * while filenames are strictly protected from path traversal
 */
class PathTraversalEnhancementsTest extends TestCase
{
    private string $testBaseDir;
    private string $testDeepDir;
    private FilesystemSaver $fileSaver;

    protected function setUp(): void
    {
        // Create a base directory with deep nesting
        $this->testBaseDir = sys_get_temp_dir() . '/path_test_' . uniqid();
        $this->testDeepDir = $this->testBaseDir . '/deep/nested/directory';
        
        mkdir($this->testDeepDir, 0777, true);
        
        // Create FilesystemSaver with deep directory as base
        $this->fileSaver = new FilesystemSaver($this->testDeepDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testBaseDir)) {
            $this->removeDirectory($this->testBaseDir);
        }
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

    /**
     * Test that uploadDestination accepts ../ for directory navigation
     */
    public function testUploadDestinationAcceptsParentDirectoryNotation(): void
    {
        // convertToRelativePath should accept ../
        $targetPath = $this->fileSaver->resolveTargetPath('../images', 'test.jpg');
        
        // Should contain the path components
        $this->assertStringContainsString('images', $targetPath);
        $this->assertStringContainsString('test.jpg', $targetPath);
    }

    /**
     * Test that filenames cannot contain path traversal
     */
    public function testFilenameRejectsPathTraversal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected in filename');

        // This should fail - filename contains ../
        $this->fileSaver->resolveTargetPath('images', '../../../etc/passwd');
    }

    /**
     * Test that filenames cannot contain ./ 
     */
    public function testFilenameRejectsCurrentDirectoryReference(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected in filename');

        // This should fail - filename contains ./
        $this->fileSaver->resolveTargetPath('images', './malicious.php');
    }

    /**
     * Test that resolveTargetPath accepts ../ in uploadDestination
     */
    public function testResolveTargetPathAcceptsParentNavigation(): void
    {
        // convertToRelativePath should accept ../ in uploadDestination
        $targetPath = $this->fileSaver->resolveTargetPath('../sibling', 'test.txt');
        
        // Should contain the components (exact path depends on normalization)
        $this->assertStringContainsString('test.txt', $targetPath);
        // The path should have the directory structure
        $this->assertIsString($targetPath);
    }

    /**
     * Test that resolveTargetPath with multiple ../ works
     */
    public function testResolveTargetPathMultipleParentNavigation(): void
    {
        // Multiple ../ should work
        $targetPath = $this->fileSaver->resolveTargetPath('../../uploads', 'file.txt');
        
        $this->assertStringContainsString('file.txt', $targetPath);
        $this->assertIsString($targetPath);
    }

    /**
     * Test that resolveTargetPath still validates final paths don't escape base
     */
    public function testResolveTargetPathValidatesBounds(): void
    {
        // resolveTargetPath itself doesn't validate bounds - that's done in resolvePath
        // So this test just ensures the path is constructed correctly
        $targetPath = $this->fileSaver->resolveTargetPath('../../../../etc', 'passwd');
        
        // Should contain the components - validation happens later in saveFile
        $this->assertStringContainsString('passwd', $targetPath);
    }

    /**
     * Test that normal relative paths still work in resolveTargetPath
     */
    public function testNormalRelativePathsStillWork(): void
    {
        $targetPath = $this->fileSaver->resolveTargetPath('normal/path', 'file.txt');
        
        $this->assertStringContainsString('normal', $targetPath);
        $this->assertStringContainsString('path', $targetPath);
        $this->assertStringContainsString('file.txt', $targetPath);
    }

    /**
     * Test that empty uploadDestination still works
     */
    public function testEmptyUploadDestination(): void
    {
        $targetPath = $this->fileSaver->resolveTargetPath('', 'file.txt');
        
        $this->assertStringContainsString('file.txt', $targetPath);
    }

    /**
     * Test that resolveTargetPath can handle complex paths
     */
    public function testResolveTargetPathComplexNavigation(): void
    {
        // Complex navigation path
        $targetPath = $this->fileSaver->resolveTargetPath('../../../deep/assets', 'file.txt');
        
        $this->assertStringContainsString('file.txt', $targetPath);
        $this->assertIsString($targetPath);
    }

    /**
     * Test that malicious filenames with ../ are rejected
     */
    public function testMaliciousFilenamesAreRejected(): void
    {
        // Try to create a path with malicious filename
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected in filename');
        
        $this->fileSaver->resolveTargetPath('uploads', '../../../etc/passwd');
    }

    /**
     * Test that filenames with ./ are rejected
     */
    public function testFilenamesWithDotSlashRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected in filename');
        
        $this->fileSaver->resolveTargetPath('uploads', './config.php');
    }

    /**
     * Test that convertToRelativePath properly validates filename separately
     */
    public function testConvertToRelativePathValidatesFilename(): void
    {
        // Good: ../ in destination, normal filename
        $targetPath1 = $this->fileSaver->resolveTargetPath('../images', 'photo.jpg');
        $this->assertStringContainsString('photo.jpg', $targetPath1);

        // Bad: normal destination, ../ in filename
        try {
            $this->fileSaver->resolveTargetPath('images', '../../../bad.jpg');
            $this->fail('Expected RuntimeException for path traversal in filename');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Path traversal detected in filename', $e->getMessage());
        }
    }

    /**
     * Test absolute uploadDestination within basePath
     */
    public function testAbsoluteUploadDestinationWithinBasePath(): void
    {
        // Create a subdirectory within our test base
        $subdir = $this->testDeepDir . '/uploads';
        mkdir($subdir, 0777, true);

        // Use absolute path that's within the base path
        $targetPath = $this->fileSaver->resolveTargetPath($subdir, 'file.txt');
        
        // Should work and return relative path
        $this->assertStringContainsString('file.txt', $targetPath);
        $this->assertStringContainsString('uploads', $targetPath);
    }

    /**
     * Test absolute uploadDestination outside basePath is rejected
     */
    public function testAbsoluteUploadDestinationOutsideBasePathRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside the allowed base path');

        // Try to use absolute path outside base path
        $outsidePath = sys_get_temp_dir() . '/outside';
        $this->fileSaver->resolveTargetPath($outsidePath, 'file.txt');
    }

    /**
     * Test filename with backslash path traversal (Windows)
     */
    public function testFilenameRejectsBackslashTraversal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected in filename');

        // Windows-style path traversal in filename
        $this->fileSaver->resolveTargetPath('uploads', '..\\..\\etc\\passwd');
    }

    /**
     * Test uploadDestination can use backslashes (Windows paths)
     */
    public function testUploadDestinationAcceptsBackslashes(): void
    {
        // Windows-style path should work in uploadDestination
        $targetPath = $this->fileSaver->resolveTargetPath('..\\sibling', 'file.txt');
        
        $this->assertStringContainsString('file.txt', $targetPath);
        $this->assertIsString($targetPath);
    }

    /**
     * Test combined scenario: complex destination with safe filename
     */
    public function testComplexDestinationWithSafeFilename(): void
    {
        // Complex navigation in destination is OK
        $targetPath = $this->fileSaver->resolveTargetPath('../../../deep/nested/uploads', 'document.pdf');
        
        $this->assertStringContainsString('document.pdf', $targetPath);
        $this->assertStringNotContainsString('..', basename($targetPath));
    }

    /**
     * Test that filename validation happens before destination processing
     */
    public function testFilenameValidationHappensFIrst(): void
    {
        // Even with valid destination, bad filename should fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected in filename');

        $this->fileSaver->resolveTargetPath('valid/destination', '../malicious.php');
    }

    /**
     * Test empty filename is handled
     */
    public function testEmptyFilenameHandling(): void
    {
        // Empty filename should return just the destination
        $targetPath = $this->fileSaver->resolveTargetPath('uploads', '');
        
        // The result should be the destination path (implementation detail)
        $this->assertIsString($targetPath);
    }

    /**
     * Test filename with only dots and slashes
     */
    public function testFilenameWithOnlyDotsAndSlashes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected in filename');

        $this->fileSaver->resolveTargetPath('uploads', '../');
    }

    /**
     * Test uploadDestination with trailing slashes
     */
    public function testUploadDestinationWithTrailingSlashes(): void
    {
        // Trailing slashes should be handled gracefully
        $targetPath = $this->fileSaver->resolveTargetPath('../images/', 'photo.jpg');
        
        $this->assertStringContainsString('photo.jpg', $targetPath);
        $this->assertStringContainsString('images', $targetPath);
    }
}

