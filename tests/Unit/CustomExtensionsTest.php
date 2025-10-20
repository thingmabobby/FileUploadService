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
 * Test custom extensions and MIME types support
 */
class CustomExtensionsTest extends TestCase
{
    private FileServiceValidator $validator;
    private FileUploadService $service;
    private FileUploadSave $fileUploadSave;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->validator = new FileServiceValidator();
        $this->service = new FileUploadService();
        $fileSaver = new FilesystemSaver(sys_get_temp_dir());
        $this->fileUploadSave = new FileUploadSave($this->validator, $fileSaver);
        $this->tempDir = sys_get_temp_dir();
    }

    /**
     * Test that we can allow custom extensions
     */
    public function testCustomExtensionsAllowed(): void
    {
        // Test with custom extensions
        $this->service->setAllowedFileTypes(['image', 'custom_ext', 'another_ext']);

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertContains('image', $allowedTypes);
        $this->assertContains('custom_ext', $allowedTypes);
        $this->assertContains('another_ext', $allowedTypes);
    }

    /**
     * Test custom extension validation
     */
    public function testCustomExtensionValidation(): void
    {
        // Create a file with custom extension
        $customContent = "This is custom content";
        $customPath = $this->tempDir . '/custom_file_' . uniqid() . '.custom';
        file_put_contents($customPath, $customContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'custom_file.custom',
                'type' => 'application/octet-stream',
                'tmp_name' => $customPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($customContent)
            ], 'custom_file.custom');

            // Test with custom extension allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['custom'] // Allow custom extension
            );

            $this->assertTrue($result['success'], 'Custom extension should be allowed when explicitly specified');
            $this->assertArrayHasKey('filePath', $result);
        } finally {
            unlink($customPath);
        }
    }

    /**
     * Test what happens with unknown MIME types for custom extensions
     */
    public function testCustomExtensionMimeValidation(): void
    {
        // Create a file with custom extension and unknown MIME type
        $customContent = "This is custom content";
        $customPath = $this->tempDir . '/custom_mime_' . uniqid() . '.xyz';
        file_put_contents($customPath, $customContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'custom_mime.xyz',
                'type' => 'application/xyz-custom', // Unknown MIME type
                'tmp_name' => $customPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($customContent)
            ], 'custom_mime.xyz');

            // Test with custom extension allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['xyz'] // Allow custom extension
            );

            // This should pass because:
            // 1. Extension 'xyz' is allowed
            // 2. MIME type 'application/xyz-custom' is unknown, so validation passes
            $this->assertTrue($result['success'], 'Custom extension with unknown MIME should be allowed');
        } finally {
            unlink($customPath);
        }
    }

    /**
     * Test MIME type validation for custom extensions
     */
    public function testCustomExtensionWithKnownMimeType(): void
    {
        // Create a PHP file disguised as custom extension
        $phpContent = "<?php echo 'Hello'; ?>";
        $customPath = $this->tempDir . '/php_disguised_' . uniqid() . '.custom';
        file_put_contents($customPath, $phpContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'php_disguised.custom',
                'type' => 'text/x-php', // PHP MIME type
                'tmp_name' => $customPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($phpContent)
            ], 'php_disguised.custom');

            // Test with custom extension allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['custom'] // Allow custom extension
            );

            // This should PASS because:
            // 1. Extension 'custom' is allowed (explicitly whitelisted)
            // 2. Unknown extensions that are whitelisted bypass MIME validation
            // 3. The user has explicitly allowed this extension, so we trust their choice
            $this->assertTrue($result['success'], 'Custom extension should be allowed when explicitly whitelisted, regardless of MIME type');
            $this->assertArrayHasKey('filePath', $result);
        } finally {
            unlink($customPath);
        }
    }

    /**
     * Test what MIME types are detected for custom extensions
     */
    public function testCustomExtensionMimeDetection(): void
    {
        // Create different types of content with custom extension
        $testCases = [
            ['content' => 'Plain text content', 'expected_mime' => 'text/plain'],
            ['content' => '<?php echo "test"; ?>', 'expected_mime' => 'text/x-php'],
            ['content' => '{"json": "data"}', 'expected_mime' => 'application/json'],
        ];

        foreach ($testCases as $i => $testCase) {
            $customPath = $this->tempDir . '/test_custom_' . $i . '_' . uniqid() . '.custom';
            file_put_contents($customPath, $testCase['content']);

            try {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $detectedMime = finfo_file($finfo, $customPath);
                    $this->assertEquals(
                        $testCase['expected_mime'],
                        $detectedMime,
                        "Content '{$testCase['content']}' should be detected as '{$testCase['expected_mime']}'"
                    );
                    finfo_close($finfo);
                } else {
                    $this->fail('Failed to open finfo');
                }
            } finally {
                unlink($customPath);
            }
        }
    }
}
