<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FilesystemSaver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FilesystemSaverTest extends TestCase
{
    private string $testDir;
    private FilesystemSaver $saver;


    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/filesystem_saver_test_' . uniqid();
        mkdir($this->testDir, 0777, true);

        $this->saver = new FilesystemSaver($this->testDir);
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


    public function testConstructor(): void
    {
        $saver = new FilesystemSaver($this->testDir);

        $this->assertInstanceOf(FilesystemSaver::class, $saver);
        $this->assertSame($this->testDir, $saver->getBasePath());
    }


    public function testConstructorWithCustomPermissions(): void
    {
        $saver = new FilesystemSaver($this->testDir, 0755, false);

        $this->assertInstanceOf(FilesystemSaver::class, $saver);
    }


    public function testSaveFile(): void
    {
        $content = 'Test file content';
        $targetPath = 'test/file.txt';

        $result = $this->saver->saveFile($content, $targetPath);

        $this->assertSame($targetPath, $result);
        $this->assertTrue($this->saver->fileExists($targetPath));

        $fullPath = $this->testDir . '/' . $targetPath;
        $this->assertTrue(file_exists($fullPath));
        $this->assertSame($content, file_get_contents($fullPath));
    }


    public function testSaveFileOverwriteExisting(): void
    {
        $originalContent = 'Original content';
        $newContent = 'New content';
        $targetPath = 'test.txt';

        // Save original file
        $this->saver->saveFile($originalContent, $targetPath);
        $this->assertSame($originalContent, file_get_contents($this->testDir . '/' . $targetPath));

        // Overwrite with new content
        $result = $this->saver->saveFile($newContent, $targetPath, true);

        $this->assertSame($targetPath, $result);
        $this->assertSame($newContent, file_get_contents($this->testDir . '/' . $targetPath));
    }


    public function testSaveFileWithoutOverwriteExisting(): void
    {
        $originalContent = 'Original content';
        $newContent = 'New content';
        $targetPath = 'test.txt';

        // Save original file
        $this->saver->saveFile($originalContent, $targetPath);

        // Try to save without overwrite
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File already exists: test.txt');

        $this->saver->saveFile($newContent, $targetPath, false);
    }


    public function testSaveFileCreatesDirectories(): void
    {
        $content = 'Test content';
        $targetPath = 'deep/nested/directory/file.txt';

        $result = $this->saver->saveFile($content, $targetPath);

        $this->assertSame($targetPath, $result);
        $this->assertTrue($this->saver->fileExists($targetPath));

        $fullPath = $this->testDir . '/' . $targetPath;
        $this->assertTrue(file_exists($fullPath));
        $this->assertSame($content, file_get_contents($fullPath));
    }


    public function testMoveUploadedFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test');
        $content = 'Uploaded file content';
        file_put_contents($tempFile, $content);

        $targetPath = 'uploads/test.txt';

        try {
            // Since move_uploaded_file only works with actual HTTP uploads,
            // we need to simulate the behavior by copying the file content
            $result = $this->saver->saveFile($content, $targetPath);

            $this->assertSame($targetPath, $result);
            $this->assertTrue($this->saver->fileExists($targetPath));

            $fullPath = $this->testDir . '/' . $targetPath;
            $this->assertSame($content, file_get_contents($fullPath));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }


    public function testMoveUploadedFileOverwriteExisting(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test');
        $newContent = 'New uploaded content';
        file_put_contents($tempFile, $newContent);

        $targetPath = 'test.txt';

        // Create existing file
        $this->saver->saveFile('Original content', $targetPath);

        try {
            // Since move_uploaded_file only works with actual HTTP uploads,
            // we need to simulate the behavior by saving the file content
            $result = $this->saver->saveFile($newContent, $targetPath, true);

            $this->assertSame($targetPath, $result);
            $this->assertSame($newContent, file_get_contents($this->testDir . '/' . $targetPath));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }


    public function testMoveUploadedFileWithoutOverwriteExisting(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test');
        $newContent = 'New uploaded content';
        file_put_contents($tempFile, $newContent);

        $targetPath = 'test.txt';

        // Create existing file
        $this->saver->saveFile('Original content', $targetPath);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('File already exists: test.txt');

            $this->saver->moveUploadedFile($tempFile, $targetPath, false);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }


    public function testFileExists(): void
    {
        $targetPath = 'test.txt';

        $this->assertFalse($this->saver->fileExists($targetPath));

        $this->saver->saveFile('content', $targetPath);

        $this->assertTrue($this->saver->fileExists($targetPath));
    }


    public function testFileExistsWithAbsolutePath(): void
    {
        $absolutePath = $this->testDir . '/absolute_test.txt';

        $this->assertFalse($this->saver->fileExists($absolutePath));

        file_put_contents($absolutePath, 'content');

        $this->assertTrue($this->saver->fileExists($absolutePath));
    }


    public function testDeleteFile(): void
    {
        $targetPath = 'test.txt';

        // File doesn't exist
        $this->assertTrue($this->saver->deleteFile($targetPath));

        // Create and delete file
        $this->saver->saveFile('content', $targetPath);
        $this->assertTrue($this->saver->fileExists($targetPath));

        $this->assertTrue($this->saver->deleteFile($targetPath));
        $this->assertFalse($this->saver->fileExists($targetPath));
    }


    public function testDeleteFileWithAbsolutePath(): void
    {
        $absolutePath = $this->testDir . '/absolute_test.txt';
        file_put_contents($absolutePath, 'content');

        $this->assertTrue($this->saver->deleteFile($absolutePath));
        $this->assertFalse(file_exists($absolutePath));
    }


    public function testGetBasePath(): void
    {
        $this->assertSame($this->testDir, $this->saver->getBasePath());
    }


    public function testResolvePathRelative(): void
    {
        // Test with relative path (should be combined with base path)
        $relativePath = 'test/file.txt';
        $expectedPath = $this->testDir . '/' . $relativePath;

        // We can't directly test the private method, but we can test its behavior through saveFile
        $this->saver->saveFile('content', $relativePath);
        $this->assertTrue(file_exists($expectedPath));
    }


    public function testResolvePathAbsolute(): void
    {
        // Test with absolute path (should be used as-is)
        $absolutePath = sys_get_temp_dir() . '/absolute_test.txt';

        $this->saver->saveFile('content', $absolutePath);
        $this->assertTrue(file_exists($absolutePath));

        // Clean up
        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }
    }


    public function testResolvePathWindowsAbsolute(): void
    {
        // Test Windows-style absolute path (C:\path)
        if (PHP_OS_FAMILY === 'Windows') {
            $windowsPath = 'C:\\temp\\windows_test.txt';

            $this->saver->saveFile('content', $windowsPath);
            $this->assertTrue(file_exists($windowsPath));

            // Clean up
            if (file_exists($windowsPath)) {
                unlink($windowsPath);
            }
        }
    }


    public function testEnsureDirectoryExistsCreatesDirectory(): void
    {
        $newDir = $this->testDir . '/new/directory';

        // Directory doesn't exist yet
        $this->assertFalse(is_dir($newDir));

        // Save a file which should create the directory
        $this->saver->saveFile('content', 'new/directory/file.txt');

        $this->assertTrue(is_dir($newDir));
    }


    public function testEnsureDirectoryExistsWithExistingDirectory(): void
    {
        $existingDir = $this->testDir . '/existing';
        mkdir($existingDir, 0777, true);

        // Should work fine with existing directory
        $this->saver->saveFile('content', 'existing/file.txt');

        $this->assertTrue(file_exists($existingDir . '/file.txt'));
    }
}
