<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FilesystemSaver;
use FileUploadService\FileUploadService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Security tests for FileUploadService components
 * Tests path traversal protection and filename security
 */
class SecurityTest extends TestCase
{
    private string $testDir;
    private FilesystemSaver $fileSaver;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/file_upload_security_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
        $this->fileSaver = new FilesystemSaver($this->testDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test path traversal protection in resolvePath method
     */
    public function testPathTraversalProtection(): void
    {
        // Test various path traversal attempts
        $maliciousPaths = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            './../sensitive/file.txt',
            '..\\..\\..\\..\\..\\etc\\passwd',
            'subdir/../../../etc/passwd',
            'subdir\\..\\..\\..\\windows\\system32',
            '/etc/passwd',
            'C:\\Windows\\System32\\config\\sam',
            'D:\\sensitive\\data.txt',
        ];

        foreach ($maliciousPaths as $maliciousPath) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/(path traversal|absolute paths|escapes base directory)/i');

            // Use reflection to access private resolvePath method
            $reflection = new \ReflectionClass($this->fileSaver);
            $method = $reflection->getMethod('resolvePath');
            $method->setAccessible(true);
            $method->invoke($this->fileSaver, $maliciousPath);
        }
    }

    /**
     * Test that valid relative paths work correctly
     */
    public function testValidRelativePaths(): void
    {
        $validPaths = [
            'test.txt',
            'subdir/file.txt',
            'subdir/subdir2/file.txt',
            'file with spaces.txt',
            'file-with-dashes.txt',
            'file_with_underscores.txt',
        ];

        foreach ($validPaths as $validPath) {
            // Use reflection to access private resolvePath method
            $reflection = new \ReflectionClass($this->fileSaver);
            $method = $reflection->getMethod('resolvePath');
            $method->setAccessible(true);

            $resolvedPath = $method->invoke($this->fileSaver, $validPath);

            // Verify the resolved path is within the base directory
            $normalizedTestDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->testDir);
            $normalizedResolvedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedPath);
            $normalizedValidPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $validPath);

            $this->assertStringStartsWith($normalizedTestDir, $normalizedResolvedPath);
            $this->assertStringEndsWith($normalizedValidPath, $normalizedResolvedPath);
        }
    }

    /**
     * Test empty path rejection
     */
    public function testEmptyPathRejection(): void
    {
        $emptyPaths = ['', '   ', "\t", "\n"];

        foreach ($emptyPaths as $emptyPath) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Target path cannot be empty');

            // Use reflection to access private resolvePath method
            $reflection = new \ReflectionClass($this->fileSaver);
            $method = $reflection->getMethod('resolvePath');
            $method->setAccessible(true);
            $method->invoke($this->fileSaver, $emptyPath);
        }
    }

    /**
     * Test filename cleaning security features
     */
    public function testFilenameSecurityFeatures(): void
    {
        // Test dangerous characters are removed
        $dangerousFilenames = [
            'file<script>alert("xss")</script>.txt',
            'file|with|pipes.txt',
            'file:with:colons.txt',
            'file*with*asterisks.txt',
            'file?with?questions.txt',
            'file"with"quotes.txt',
            'file<with>brackets.txt',
            'file>with>brackets.txt',
            'file#with#hashes.txt',
            'file%with%percent.txt',
            'file&with&ampersands.txt',
            'file+with+pluses.txt',
            'file=with=equals.txt',
            'file;with;semicolons.txt',
            'file!with!exclamations.txt',
            'file@with@ats.txt',
            'file$with$dollars.txt',
            'file^with^carets.txt',
            'file`with`backticks.txt',
            'file~with~tildes.txt',
        ];

        foreach ($dangerousFilenames as $dangerousFilename) {
            $cleaned = FileUploadService::cleanFilename($dangerousFilename);

            // Verify dangerous characters are removed
            $this->assertStringNotContainsString('<', $cleaned);
            $this->assertStringNotContainsString('>', $cleaned);
            $this->assertStringNotContainsString('|', $cleaned);
            $this->assertStringNotContainsString(':', $cleaned);
            $this->assertStringNotContainsString('*', $cleaned);
            $this->assertStringNotContainsString('?', $cleaned);
            $this->assertStringNotContainsString('"', $cleaned);
            $this->assertStringNotContainsString('#', $cleaned);
            $this->assertStringNotContainsString('%', $cleaned);
            $this->assertStringNotContainsString('&', $cleaned);
            $this->assertStringNotContainsString('+', $cleaned);
            $this->assertStringNotContainsString('=', $cleaned);
            $this->assertStringNotContainsString(';', $cleaned);
            $this->assertStringNotContainsString('!', $cleaned);
            $this->assertStringNotContainsString('@', $cleaned);
            $this->assertStringNotContainsString('$', $cleaned);
            $this->assertStringNotContainsString('^', $cleaned);
            $this->assertStringNotContainsString('`', $cleaned);
            $this->assertStringNotContainsString('~', $cleaned);

            // Verify filename is not empty
            $this->assertNotEmpty($cleaned);
            $this->assertNotEquals('unnamed', $cleaned);
        }
    }

    /**
     * Test filename length limits
     */
    public function testFilenameLengthLimits(): void
    {
        // Test extremely long filename
        $longFilename = str_repeat('a', 300) . '.txt';
        $cleaned = FileUploadService::cleanFilename($longFilename);

        $this->assertLessThanOrEqual(200, strlen($cleaned));
        $this->assertStringEndsWith('.txt', $cleaned);

        // Test filename without extension
        $longFilenameNoExt = str_repeat('a', 300);
        $cleanedNoExt = FileUploadService::cleanFilename($longFilenameNoExt);

        $this->assertLessThanOrEqual(200, strlen($cleanedNoExt));
    }

    /**
     * Test control character removal
     */
    public function testControlCharacterRemoval(): void
    {
        $filenameWithControlChars = "file\x00\x01\x02\x03\x04\x05.txt";
        $cleaned = FileUploadService::cleanFilename($filenameWithControlChars);

        $this->assertStringNotContainsString("\x00", $cleaned);
        $this->assertStringNotContainsString("\x01", $cleaned);
        $this->assertStringNotContainsString("\x02", $cleaned);
        $this->assertStringNotContainsString("\x03", $cleaned);
        $this->assertStringNotContainsString("\x04", $cleaned);
        $this->assertStringNotContainsString("\x05", $cleaned);
        $this->assertEquals('file.txt', $cleaned);
    }

    /**
     * Test Windows-specific filename issues
     */
    public function testWindowsFilenameIssues(): void
    {
        // Test leading/trailing dots and spaces
        $problematicFilenames = [
            '.hidden',
            'file.',
            ' file ',
            '.file.',
            ' file. ',
        ];

        foreach ($problematicFilenames as $filename) {
            $cleaned = FileUploadService::cleanFilename($filename);

            // Should not start or end with dots or spaces
            $this->assertFalse(str_starts_with($cleaned, '.'));
            $this->assertFalse(str_ends_with($cleaned, '.'));
            $this->assertFalse(str_starts_with($cleaned, ' '));
            $this->assertFalse(str_ends_with($cleaned, ' '));
        }
    }

    /**
     * Test empty filename handling
     */
    public function testEmptyFilenameHandling(): void
    {
        $emptyFilenames = [
            '',
            '   ',
            '\\/:*?"<>|#',
            '.',
            '..',
        ];

        foreach ($emptyFilenames as $emptyFilename) {
            $cleaned = FileUploadService::cleanFilename($emptyFilename);
            $this->assertEquals('unnamed', $cleaned);
        }
    }

    /**
     * Test Unicode normalization (if available)
     */
    public function testUnicodeNormalization(): void
    {
        if (!class_exists('Normalizer')) {
            $this->markTestSkipped('Normalizer class not available');
        }

        // Test that Unicode characters are handled properly
        $unicodeFilename = 'café.txt';
        $cleaned = FileUploadService::cleanFilename($unicodeFilename);

        $this->assertStringContainsString('café', $cleaned);
        $this->assertStringEndsWith('.txt', $cleaned);
    }

    /**
     * Test custom character removal
     */
    public function testCustomCharacterRemoval(): void
    {
        $filename = 'test-file_with@special#chars.txt';
        $cleaned = FileUploadService::cleanFilename($filename, removeCustomChars: ['@', '#']);

        $this->assertStringNotContainsString('@', $cleaned);
        $this->assertStringNotContainsString('#', $cleaned);
        $this->assertStringContainsString('test-file_with', $cleaned);
        $this->assertStringEndsWith('.txt', $cleaned);
    }
}
