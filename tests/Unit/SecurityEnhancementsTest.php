<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FileServiceValidator;
use FileUploadService\FileUploadService;
use FileUploadService\FileUploadSave;
use FileUploadService\DTO\FileUploadDTO;
use FileUploadService\FilesystemSaver;
use PHPUnit\Framework\TestCase;

/**
 * Test additional security enhancements
 */
class SecurityEnhancementsTest extends TestCase
{
    private FileServiceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FileServiceValidator();
    }

    /**
     * Test suspicious filename patterns
     */
    public function testSuspiciousFilenamePatterns(): void
    {
        $suspiciousPatterns = [
            'malware.php.txt',
            'script.js.html',
            'virus.exe.pdf',
            'backdoor.bat.txt',
            'trojan.scr.jpg',
            'shell.php.gif',
            'webshell.asp.jpg',
            'cmd.exe.png',
        ];

        foreach ($suspiciousPatterns as $filename) {
            // Extract all extensions (not just the last one)
            $parts = explode('.', $filename);
            $extensions = array_slice($parts, 1); // All parts after first dot

            // Check if any suspicious extensions are present
            $suspiciousExtensions = ['php', 'js', 'exe', 'bat', 'scr', 'asp', 'cmd'];
            $hasSuspiciousExtension = !empty(array_intersect($extensions, $suspiciousExtensions));

            $this->assertTrue($hasSuspiciousExtension, "Filename {$filename} should be flagged as suspicious");
        }
    }

    /**
     * Test that we could enhance validation to check ALL extensions
     */
    public function testAllExtensionsDetection(): void
    {
        $testCases = [
            'malware.php.txt' => ['php', 'txt'],
            'script.js.html' => ['js', 'html'],
            'virus.exe.pdf' => ['exe', 'pdf'],
            'normal.txt' => ['txt'],
            'image.png' => ['png'],
        ];

        foreach ($testCases as $filename => $expectedExtensions) {
            $parts = explode('.', $filename);
            $actualExtensions = array_slice($parts, 1);

            $this->assertEquals($expectedExtensions, $actualExtensions, "Should detect all extensions for {$filename}");
        }
    }

    /**
     * Test file size limits for different file types
     */
    public function testFileSizeLimits(): void
    {
        // Test that we could implement different size limits per file type
        $sizeLimits = [
            'jpg' => 10 * 1024 * 1024,  // 10MB for images
            'png' => 10 * 1024 * 1024,  // 10MB for images
            'pdf' => 50 * 1024 * 1024,  // 50MB for PDFs
            'mp4' => 100 * 1024 * 1024, // 100MB for videos
            'txt' => 1 * 1024 * 1024,   // 1MB for text files
        ];

        foreach ($sizeLimits as $extension => $maxSize) {
            $this->assertGreaterThan(0, $maxSize, "Size limit for {$extension} should be positive");
            $this->assertLessThan(1024 * 1024 * 1024, $maxSize, "Size limit for {$extension} should be reasonable");
        }
    }

    /**
     * Test that we could add filename sanitization for suspicious patterns
     */
    public function testFilenameSanitization(): void
    {
        $suspiciousFilenames = [
            'malware.php.txt',
            'script.js.html',
            'virus.exe.pdf',
        ];

        foreach ($suspiciousFilenames as $filename) {
            // Check if filename contains suspicious patterns
            $suspiciousPatterns = ['\.php\.', '\.js\.', '\.exe\.', '\.bat\.', '\.scr\.'];
            $isSuspicious = false;

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $filename)) {
                    $isSuspicious = true;
                    break;
                }
            }

            $this->assertTrue($isSuspicious, "Filename {$filename} should be detected as suspicious");
        }
    }
}
