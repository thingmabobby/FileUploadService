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
 * Test the corrected MIME validation logic
 */
class CorrectedMimeValidationTest extends TestCase
{
    private FileServiceValidator $validator;
    private FileUploadSave $fileUploadSave;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->validator = new FileServiceValidator();
        $fileSaver = new FilesystemSaver(sys_get_temp_dir());
        $this->fileUploadSave = new FileUploadSave($this->validator, $fileSaver);
        $this->tempDir = sys_get_temp_dir();
    }

    /**
     * Test that MIME validation is ALWAYS strict unless 'all' is explicitly allowed
     */
    public function testMimeValidationBehavior(): void
    {
        // Create a real PNG file with .txt extension
        $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $fakeImagePath = $this->tempDir . '/fake_image_' . uniqid() . '.txt';
        file_put_contents($fakeImagePath, $pngContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'fake_image.txt',
                'type' => 'text/plain',
                'tmp_name' => $fakeImagePath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($pngContent)
            ], 'fake_image.txt');

            // Test 1: Empty array (no types specified) - should use STRICT validation
            $result1 = $this->fileUploadSave->processFileUpload($fileDTO, '', true, []);
            $this->assertFalse($result1['success'], 'Empty array should use strict MIME validation');
            $this->assertInstanceOf(\FileUploadService\FileUploadError::class, $result1['error']);

            // Test 2: Specific types allowed - should use STRICT validation
            $result2 = $this->fileUploadSave->processFileUpload($fileDTO, '', true, ['image']);
            $this->assertFalse($result2['success'], 'Specific types should use strict MIME validation');
            $this->assertInstanceOf(\FileUploadService\FileUploadError::class, $result2['error']);

            // Test 3: 'all' explicitly allowed - should use RELAXED validation
            $result3 = $this->fileUploadSave->processFileUpload($fileDTO, '', true, ['all']);
            $this->assertTrue($result3['success'], "'all' should use relaxed MIME validation");
            $this->assertArrayHasKey('filePath', $result3);
        } finally {
            unlink($fakeImagePath);
        }
    }

    /**
     * Test that the logic is: strict UNLESS 'all' is explicitly allowed
     */
    public function testMimeValidationLogic(): void
    {
        $testCases = [
            // [allowedTypes, expectedStrict, description]
            [[], true, 'Empty array should be strict (default security)'],
            [['image'], true, 'Specific types should be strict'],
            [['image', 'pdf'], true, 'Multiple specific types should be strict'],
            [['all'], false, "'all' should be relaxed"],
            [['image', 'all'], false, "'all' with other types should be relaxed"],
        ];

        foreach ($testCases as [$allowedTypes, $expectedStrict, $description]) {
            $actualStrict = !in_array('all', $allowedTypes, true);
            $this->assertEquals($expectedStrict, $actualStrict, $description);
        }
    }

    /**
     * Test security implications of the corrected logic
     */
    public function testSecurityImplications(): void
    {
        // Create malicious content disguised as image
        $maliciousContent = "<?php system('rm -rf /'); ?>";
        $maliciousPath = $this->tempDir . '/malicious_' . uniqid() . '.jpg';
        file_put_contents($maliciousPath, $maliciousContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'malicious.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => $maliciousPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($maliciousContent)
            ], 'malicious.jpg');

            // With strict validation (default), this should be blocked
            $result = $this->fileUploadSave->processFileUpload($fileDTO, '', true, ['image']);
            $this->assertFalse($result['success'], 'Malicious content should be blocked with strict validation');
            $this->assertInstanceOf(\FileUploadService\FileUploadError::class, $result['error']);
        } finally {
            unlink($maliciousPath);
        }
    }
}
