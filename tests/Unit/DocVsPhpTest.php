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
 * Test what happens when allowing docs and uploading PHP files
 */
class DocVsPhpTest extends TestCase
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
     * Test PHP file upload when only docs are allowed
     */
    public function testPhpFileWhenOnlyDocsAllowed(): void
    {
        // Create a PHP file
        $phpContent = "<?php echo 'Hello World'; ?>";
        $phpPath = $this->tempDir . '/malicious_' . uniqid() . '.php';
        file_put_contents($phpPath, $phpContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'malicious.php',
                'type' => 'application/x-php',
                'tmp_name' => $phpPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($phpContent)
            ], 'malicious.php');

            // Test with only document types allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['doc'] // Only allow documents
            );

            // This should be REJECTED because 'php' is not in allowed document types
            $this->assertFalse($result['success'], 'PHP file should be rejected when only docs are allowed');
            $this->assertInstanceOf(\FileUploadService\FileUploadError::class, $result['error']);
        } finally {
            unlink($phpPath);
        }
    }

    /**
     * Test what document types are actually allowed
     */
    public function testAllowedDocumentTypes(): void
    {
        $docTypes = $this->validator->getSupportedDocumentTypes();

        // Check what extensions are actually allowed for documents
        $this->assertIsArray($docTypes);

        // These should be allowed
        $expectedDocExtensions = ['doc', 'docx', 'txt', 'rtf', 'csv', 'xml', 'json'];
        foreach ($expectedDocExtensions as $ext) {
            $this->assertArrayHasKey($ext, $docTypes, "Document extension '{$ext}' should be allowed");
        }

        // PHP should NOT be in document types
        $this->assertArrayNotHasKey('php', $docTypes, 'PHP should NOT be in document types');
    }

    /**
     * Test PHP file disguised as document
     */
    public function testPhpDisguisedAsDocument(): void
    {
        // Create PHP content disguised as a text file
        $phpContent = "<?php system('rm -rf /'); ?>";
        $fakeDocPath = $this->tempDir . '/fake_doc_' . uniqid() . '.txt';
        file_put_contents($fakeDocPath, $phpContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'fake_doc.txt',
                'type' => 'text/plain',
                'tmp_name' => $fakeDocPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($phpContent)
            ], 'fake_doc.txt');

            // Test with document types allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['doc'] // Allow documents
            );

            // This should FAIL MIME validation because:
            // 1. File has .txt extension (allowed for docs)
            // 2. But content is PHP code (not text/plain)
            // 3. MIME validation should catch this mismatch
            $this->assertFalse($result['success'], 'PHP content disguised as .txt should fail MIME validation');
            $this->assertInstanceOf(\FileUploadService\FileUploadError::class, $result['error']);
        } finally {
            unlink($fakeDocPath);
        }
    }

    /**
     * Test legitimate document upload
     */
    public function testLegitimateDocumentUpload(): void
    {
        // Create legitimate text content
        $textContent = "This is a legitimate text document.";
        $docPath = $this->tempDir . '/legitimate_' . uniqid() . '.txt';
        file_put_contents($docPath, $textContent);

        try {
            $fileDTO = FileUploadDTO::fromFilesArray([
                'name' => 'legitimate.txt',
                'type' => 'text/plain',
                'tmp_name' => $docPath,
                'error' => UPLOAD_ERR_OK,
                'size' => strlen($textContent)
            ], 'legitimate.txt');

            // Test with document types allowed
            $result = $this->fileUploadSave->processFileUpload(
                $fileDTO,
                '',
                true,
                ['doc'] // Allow documents
            );

            // This should PASS because it's legitimate text content
            $this->assertTrue($result['success'], 'Legitimate text document should be allowed');
            $this->assertArrayHasKey('filePath', $result);
        } finally {
            unlink($docPath);
        }
    }

    /**
     * Test what happens with different PHP MIME types
     */
    public function testPhpMimeTypes(): void
    {
        $phpMimeTypes = [
            'application/x-php',
            'application/php',
            'text/php',
            'text/x-php',
            'application/x-httpd-php'
        ];

        foreach ($phpMimeTypes as $mimeType) {
            // Check if this MIME type would be detected as PHP
            $detectedExtension = $this->validator->getExtensionFromMimeType($mimeType);

            // Most PHP MIME types should not map to document extensions
            $this->assertNotEquals('txt', $detectedExtension, "MIME type {$mimeType} should not map to txt");
            $this->assertNotEquals('doc', $detectedExtension, "MIME type {$mimeType} should not map to doc");
        }
    }
}
