<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FileServiceValidator;
use FileUploadService\FileUploadService;
use FileUploadService\FileUploadSave;
use FileUploadService\DTO\FileUploadDTO;
use FileUploadService\FilesystemSaver;
use FileUploadService\Enum\FileTypeEnum;
use PHPUnit\Framework\TestCase;

/**
 * Test the new MIME type customization feature
 */
class MimeTypeCustomizationTest extends TestCase
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
     * Test backward compatibility with existing API
     */
    public function testBackwardCompatibility(): void
    {
        // Test that existing API still works
        $this->service->setAllowedFileTypes(['image', 'pdf', 'custom_ext']);

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertContains('image', $allowedTypes);
        $this->assertContains('pdf', $allowedTypes);
        $this->assertContains('custom_ext', $allowedTypes);
    }

    /**
     * Test new mime: prefix functionality
     */
    public function testMimePrefixFunctionality(): void
    {
        // Test MIME types with mime: prefix
        $this->service->setAllowedFileTypes(['image', 'mime:text/csv', 'mime:application/custom']);

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertContains('image', $allowedTypes);
        $this->assertContains('mime:text/csv', $allowedTypes);
        $this->assertContains('mime:application/custom', $allowedTypes);

        // Test individual arrays
        $categories = $this->service->getAllowedCategories();
        $this->assertContains(FileTypeEnum::IMAGE, $categories);
        $this->assertContains('text/csv', $this->service->getAllowedMimeTypes());
        $this->assertContains('application/custom', $this->service->getAllowedMimeTypes());
    }

    /**
     * Test granular setters
     */
    public function testGranularSetters(): void
    {
        // Test individual setters
        $this->service->setAllowedCategories(['image', 'pdf']);
        $this->service->setAllowedExtensions(['custom', 'xyz']);
        $this->service->setAllowedMimeTypes(['text/csv', 'application/json']);

        // Verify individual arrays
        $categories = $this->service->getAllowedCategories();
        $this->assertContains(FileTypeEnum::IMAGE, $categories);
        $this->assertContains(FileTypeEnum::PDF, $categories);
        $this->assertEquals(['custom', 'xyz'], $this->service->getAllowedExtensions());
        $this->assertEquals(['text/csv', 'application/json'], $this->service->getAllowedMimeTypes());

        // Verify combined array
        $combined = $this->service->getAllowedFileTypes();
        $this->assertContains(FileTypeEnum::IMAGE, $combined);
        $this->assertContains(FileTypeEnum::PDF, $combined);
        $this->assertContains('custom', $combined);
        $this->assertContains('xyz', $combined);
        $this->assertContains('mime:text/csv', $combined);
        $this->assertContains('mime:application/json', $combined);
    }

    /**
     * Test MIME type validation with custom MIME types
     */
    public function testMimeTypeValidation(): void
    {
        // Create a CSV file
        $csvContent = "name,age\nJohn,25\nJane,30";
        $csvPath = $this->tempDir . '/test_' . uniqid() . '.csv';
        file_put_contents($csvPath, $csvContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'test.csv',
                'type' => 'text/csv',
                'tmp_name' => $csvPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($csvContent)
            ], 'test.csv');

            // Test with MIME type allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['mime:text/csv'] // Allow CSV MIME type
            );

            $this->assertTrue($result['success'], 'CSV file should be allowed with mime:text/csv');
            $this->assertArrayHasKey('filePath', $result);
        } finally {
            unlink($csvPath);
        }
    }

    /**
     * Test MIME type validation rejection
     */
    public function testMimeTypeValidationRejection(): void
    {
        // Create a PHP file disguised as CSV
        $phpContent = "<?php system('rm -rf /'); ?>";
        $fakeCsvPath = $this->tempDir . '/fake_' . uniqid() . '.csv';
        file_put_contents($fakeCsvPath, $phpContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'fake.csv',
                'type' => 'text/csv',
                'tmp_name' => $fakeCsvPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($phpContent)
            ], 'fake.csv');

            // Test with CSV MIME type allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['mime:text/csv'] // Allow CSV MIME type
            );

            // This should FAIL because PHP content doesn't match CSV MIME type
            $this->assertFalse($result['success'], 'PHP content disguised as CSV should fail MIME validation');
            $this->assertInstanceOf(\FileUploadService\FileUploadError::class, $result['error']);
        } finally {
            unlink($fakeCsvPath);
        }
    }

    /**
     * Test validation logic: Match ANY allowed type = Allow
     */
    public function testValidationLogic(): void
    {
        // Create a text file
        $textContent = "This is plain text content";
        $textPath = $this->tempDir . '/test_' . uniqid() . '.txt';
        file_put_contents($textPath, $textContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => $textPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($textContent)
            ], 'test.txt');

            // Test 1: Match by category (should pass)
            $result1 = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['doc'] // Matches document category
            );
            $this->assertTrue($result1['success'], 'Should match by document category');

            // Test 2: Match by extension (should pass)
            $textContent2 = "This is a proper text file\nwith multiple lines\nand proper formatting.";
            $textPath2 = sys_get_temp_dir() . '/proper_test_2_' . uniqid() . '.txt';
            file_put_contents($textPath2, $textContent2);

            $fileDTO2 = FileUploadDTO::fromFilesArray([
                'name' => 'proper_test_2.txt',
                'type' => 'text/plain',
                'tmp_name' => $textPath2,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($textContent2)
            ], 'proper_test_2.txt');

            $result2 = $this->fileUploadSave->processFileUpload(
                $fileDTO2,
                '',
                true,
                ['txt'] // Matches txt extension
            );
            if (!$result2['success']) {
                $this->fail('Should match by txt extension. Error: ' . $result2['error']->getDescription());
            }
            $this->assertTrue($result2['success'], 'Should match by txt extension');

            // Test 3: Match by MIME type (should pass)
            $textContent3 = "This is a proper text file\nwith multiple lines\nand proper formatting.";
            $textPath3 = sys_get_temp_dir() . '/proper_test_3_' . uniqid() . '.txt';
            file_put_contents($textPath3, $textContent3);

            $fileDTO3 = FileUploadDTO::fromFilesArray([
                'name' => 'proper_test_3.txt',
                'type' => 'text/plain',
                'tmp_name' => $textPath3,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($textContent3)
            ], 'proper_test_3.txt');

            $result3 = $this->fileUploadSave->processFileUpload(
                $fileDTO3,
                '',
                true,
                ['mime:text/plain'] // Matches text/plain MIME type
            );
            if (!$result3['success']) {
                $this->fail('Should match by MIME type. Error: ' . $result3['error']->getDescription());
            }
            $this->assertTrue($result3['success'], 'Should match by MIME type');

            // Test 4: Match ANY of multiple types (should pass)
            $textContent4 = "This is a proper text file\nwith multiple lines\nand proper formatting.";
            $textPath4 = sys_get_temp_dir() . '/proper_test_4_' . uniqid() . '.txt';
            file_put_contents($textPath4, $textContent4);

            $fileDTO4 = FileUploadDTO::fromFilesArray([
                'name' => 'proper_test_4.txt',
                'type' => 'text/plain',
                'tmp_name' => $textPath4,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($textContent4)
            ], 'proper_test_4.txt');

            $result4 = $this->fileUploadSave->processFileUpload(
                $fileDTO4,
                '',
                true,
                ['image', 'mime:text/plain', 'xyz'] // Matches MIME type
            );
            $this->assertTrue($result4['success'], 'Should match ANY of the allowed types');

            // Test 5: No matches (should fail)
            $textContent5 = "This is a proper text file\nwith multiple lines\nand proper formatting.";
            $textPath5 = sys_get_temp_dir() . '/proper_test_5_' . uniqid() . '.txt';
            file_put_contents($textPath5, $textContent5);

            $fileDTO5 = FileUploadDTO::fromFilesArray([
                'name' => 'proper_test_5.txt',
                'type' => 'text/plain',
                'tmp_name' => $textPath5,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($textContent5)
            ], 'proper_test_5.txt');

            $result5 = $this->fileUploadSave->processFileUpload(
                $fileDTO5,
                '',
                true,
                ['image', 'mime:text/csv', 'xyz'] // No matches
            );
            $this->assertFalse($result5['success'], 'Should fail when no types match');
        } finally {
            unlink($textPath);
            if (isset($textPath2)) unlink($textPath2);
            if (isset($textPath3)) unlink($textPath3);
            if (isset($textPath4)) unlink($textPath4);
            if (isset($textPath5)) unlink($textPath5);
        }
    }

    /**
     * Test mixed configuration
     */
    public function testMixedConfiguration(): void
    {
        // Test mixed configuration
        $this->service->setAllowedFileTypes([
            'image',           // Category
            'custom_ext',      // Extension
            'mime:text/csv',   // MIME type
            'pdf'              // Category
        ]);

        $allowedTypes = $this->service->getAllowedFileTypes();
        $this->assertContains('image', $allowedTypes);
        $this->assertContains('custom_ext', $allowedTypes);
        $this->assertContains('mime:text/csv', $allowedTypes);
        $this->assertContains('pdf', $allowedTypes);

        // Verify individual arrays
        $categories = $this->service->getAllowedCategories();
        $this->assertContains(FileTypeEnum::IMAGE, $categories);
        $this->assertContains(FileTypeEnum::PDF, $categories);
        $this->assertContains('custom_ext', $this->service->getAllowedExtensions());
        $this->assertContains('text/csv', $this->service->getAllowedMimeTypes());
    }

    /**
     * Test edge cases
     */
    public function testEdgeCases(): void
    {
        // Test empty arrays
        $this->service->setAllowedCategories([]);
        $this->service->setAllowedExtensions([]);
        $this->service->setAllowedMimeTypes([]);

        $this->assertEquals([], $this->service->getAllowedCategories());
        $this->assertEquals([], $this->service->getAllowedExtensions());
        $this->assertEquals([], $this->service->getAllowedMimeTypes());

        // Test case sensitivity
        $this->service->setAllowedExtensions(['TXT', 'PDF']);
        $this->assertEquals(['txt', 'pdf'], $this->service->getAllowedExtensions());
    }
}
