<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FileServiceValidator;
use FileUploadService\FileUploadService;
use FileUploadService\FileUploadSave;
use FileUploadService\DTO\FileUploadDTO;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\FilesystemSaver;
use PHPUnit\Framework\TestCase;

/**
 * Test security edge cases and potential attack vectors
 */
class SecurityEdgeCasesTest extends TestCase
{
    private FileServiceValidator $validator;
    private FileUploadService $service;
    private FileUploadSave $fileUploadSave;

    protected function setUp(): void
    {
        $this->validator = new FileServiceValidator();
        $this->service = new FileUploadService();
        $fileSaver = new FilesystemSaver(sys_get_temp_dir());
        $this->fileUploadSave = new FileUploadSave($this->validator, $fileSaver);
    }

    /**
     * Test double extension attack vectors
     */
    public function testDoubleExtensionAttackVectors(): void
    {
        // Test cases that should be blocked
        $maliciousFiles = [
            'malware.php.txt',      // PHP disguised as text
            'script.js.html',       // JavaScript disguised as HTML
            'virus.exe.pdf',        // Executable disguised as PDF
            'backdoor.bat.txt',     // Batch file disguised as text
            'trojan.scr.jpg',       // Screensaver disguised as image
        ];

        foreach ($maliciousFiles as $filename) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // The extension should be the LAST part, not the malicious part
            $this->assertNotEquals('php', $extension, "Double extension {$filename} should not be detected as PHP");
            $this->assertNotEquals('js', $extension, "Double extension {$filename} should not be detected as JS");
            $this->assertNotEquals('exe', $extension, "Double extension {$filename} should not be detected as EXE");
            $this->assertNotEquals('bat', $extension, "Double extension {$filename} should not be detected as BAT");
            $this->assertNotEquals('scr', $extension, "Double extension {$filename} should not be detected as SCR");
        }
    }

    /**
     * Test MIME type vs extension mismatch
     */
    public function testMimeTypeExtensionMismatch(): void
    {
        // Create a real PNG file but with .txt extension
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $fakeImagePath = sys_get_temp_dir() . '/fake_image_' . uniqid() . '.txt';
        file_put_contents($fakeImagePath, $pngContent);

        try {
            // Test file type allowance (should fail due to MIME type mismatch)
            $isAllowed = $this->validator->isFileTypeAllowed('txt', $fakeImagePath, [FileTypeEnum::IMAGE]);
            $this->assertFalse($isAllowed, 'PNG content with .txt extension should fail file type allowance');

            // Test basic file validation (should pass)
            $isValid = $this->validator->validateUploadedFile($fakeImagePath, 'fake_image.txt');
            $this->assertTrue($isValid, 'PNG content with .txt extension should pass basic file validation');
        } finally {
            unlink($fakeImagePath);
        }
    }

    /**
     * Test executable files disguised as images
     */
    public function testExecutableDisguisedAsImage(): void
    {
        // Create a fake executable content (just some binary data)
        $executableContent = "\x4D\x5A\x90\x00"; // PE file signature
        $fakeExePath = sys_get_temp_dir() . '/fake_executable_' . uniqid() . '.jpg';
        file_put_contents($fakeExePath, $executableContent);

        try {
            // This should fail MIME validation
            $isValid = $this->validator->validateUploadedFile($fakeExePath, 'fake_executable.jpg');
            $this->assertFalse($isValid, 'Executable content with .jpg extension should fail validation');
        } finally {
            unlink($fakeExePath);
        }
    }

    /**
     * Test PHP files disguised as images
     */
    public function testPhpDisguisedAsImage(): void
    {
        // Create PHP content disguised as image
        $phpContent = "<?php system('rm -rf /'); ?>";
        $fakePhpPath = sys_get_temp_dir() . '/fake_php_' . uniqid() . '.png';
        file_put_contents($fakePhpPath, $phpContent);

        try {
            // This should fail MIME validation
            $isValid = $this->validator->validateUploadedFile($fakePhpPath, 'fake_php.png');
            $this->assertFalse($isValid, 'PHP content with .png extension should fail validation');
        } finally {
            unlink($fakePhpPath);
        }
    }

    /**
     * Test that pathinfo correctly handles double extensions
     */
    public function testPathinfoDoubleExtensions(): void
    {
        $testCases = [
            'malware.php.txt' => 'txt',
            'script.js.html' => 'html',
            'virus.exe.pdf' => 'pdf',
            'backdoor.bat.txt' => 'txt',
            'trojan.scr.jpg' => 'jpg',
            'normal.txt' => 'txt',
            'image.png' => 'png',
            'document.pdf' => 'pdf',
        ];

        foreach ($testCases as $filename => $expectedExtension) {
            $actualExtension = pathinfo($filename, PATHINFO_EXTENSION);
            $this->assertEquals($expectedExtension, $actualExtension, "pathinfo should return last extension for {$filename}");
        }
    }

    /**
     * Test file type detection based on actual content vs extension
     */
    public function testContentBasedValidation(): void
    {
        // Create a real JPEG file
        $jpegContent = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A8A');
        $realJpegPath = sys_get_temp_dir() . '/real_jpeg_' . uniqid() . '.jpg';
        file_put_contents($realJpegPath, $jpegContent);

        try {
            // This should pass validation
            $isValid = $this->validator->validateUploadedFile($realJpegPath, 'real_jpeg.jpg');
            $this->assertTrue($isValid, 'Real JPEG content with .jpg extension should pass validation');
        } finally {
            unlink($realJpegPath);
        }
    }

    /**
     * Test that unknown extensions are handled gracefully
     */
    public function testUnknownExtensions(): void
    {
        // Create a file with unknown extension
        $unknownContent = "This is just text content";
        $unknownPath = sys_get_temp_dir() . '/unknown_file_' . uniqid() . '.xyz';
        file_put_contents($unknownPath, $unknownContent);

        try {
            // With strict validation, unknown extensions should be allowed
            $isValid = $this->validator->validateUploadedFile($unknownPath, 'unknown_file.xyz');
            $this->assertTrue($isValid, 'Unknown extensions should be allowed with strict validation');
        } finally {
            unlink($unknownPath);
        }
    }
}
