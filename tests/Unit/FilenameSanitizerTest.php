<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\Utils\FilenameSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FilenameSanitizer utility class
 * 
 * @package FileUploadService\Tests\Unit
 */
class FilenameSanitizerTest extends TestCase
{
    /**
     * Test basic filename cleaning
     */
    public function testCleanFilename(): void
    {
        $this->assertSame('test-file', FilenameSanitizer::cleanFilename('test-file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test/file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test:file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test*file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test?file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test"file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test<file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test>file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test|file'));
        $this->assertSame('testfile', FilenameSanitizer::cleanFilename('test#file'));
    }


    /**
     * Test null byte removal
     */
    public function testRemoveControlCharacters(): void
    {
        $filenameWithControlChars = "file\x00\x01\x02\x03\x04\x05.txt";
        $cleaned = FilenameSanitizer::removeControlCharacters($filenameWithControlChars);

        $this->assertStringNotContainsString("\x00", $cleaned);
        $this->assertStringNotContainsString("\x01", $cleaned);
        $this->assertStringNotContainsString("\x02", $cleaned);
        $this->assertStringNotContainsString("\x03", $cleaned);
        $this->assertStringNotContainsString("\x04", $cleaned);
        $this->assertStringNotContainsString("\x05", $cleaned);
        $this->assertSame('file.txt', $cleaned);
    }


    /**
     * Test path cleaning preserves directory separators
     */
    public function testCleanPath(): void
    {
        $pathWithSeparators = "path/to/file.txt";
        $cleaned = FilenameSanitizer::cleanPath($pathWithSeparators);

        $this->assertSame('path/to/file.txt', $cleaned);

        // Test dangerous characters are removed
        $dangerousPath = "path*to?file.txt";
        $cleanedDangerous = FilenameSanitizer::cleanPath($dangerousPath);

        $this->assertSame('pathtofile.txt', $cleanedDangerous);
    }


    /**
     * Test empty path handling
     */
    public function testCleanPathEmpty(): void
    {
        $emptyPath = '';
        $cleaned = FilenameSanitizer::cleanPath($emptyPath);

        // Should return empty string (not convert to 'unnamed')
        $this->assertSame('', $cleaned);
    }
}
