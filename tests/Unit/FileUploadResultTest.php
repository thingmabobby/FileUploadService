<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FileUploadError;
use FileUploadService\FileUploadResult;
use PHPUnit\Framework\TestCase;

class FileUploadResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $successfulFiles = ['file1.jpg', 'file2.pdf'];
        $errors = [
            new FileUploadError('file3.txt', 'File type not allowed'),
            new FileUploadError('file4.exe', 'File too large')
        ];
        $totalFiles = 4;
        $successfulCount = 2;

        $result = new FileUploadResult($successfulFiles, $errors, $totalFiles, $successfulCount);

        $this->assertSame($successfulFiles, $result->successfulFiles);
        $this->assertSame($errors, $result->errors);
        $this->assertSame($totalFiles, $result->totalFiles);
        $this->assertSame($successfulCount, $result->successfulCount);
    }


    public function testHasErrorsWithErrors(): void
    {
        $errors = [new FileUploadError('file.txt', 'Some error')];
        $result = new FileUploadResult(['file1.jpg'], $errors, 2, 1);

        $this->assertTrue($result->hasErrors());
    }


    public function testHasErrorsWithoutErrors(): void
    {
        $result = new FileUploadResult(['file1.jpg'], [], 1, 1);

        $this->assertFalse($result->hasErrors());
    }


    public function testIsCompleteSuccessWithAllSuccessful(): void
    {
        $result = new FileUploadResult(['file1.jpg', 'file2.pdf'], [], 2, 2);

        $this->assertTrue($result->isCompleteSuccess());
    }


    public function testIsCompleteSuccessWithPartialSuccess(): void
    {
        $errors = [new FileUploadError('file3.txt', 'Some error')];
        $result = new FileUploadResult(['file1.jpg', 'file2.pdf'], $errors, 3, 2);

        $this->assertFalse($result->isCompleteSuccess());
    }


    public function testIsCompleteSuccessWithNoSuccess(): void
    {
        $errors = [
            new FileUploadError('file1.txt', 'Error 1'),
            new FileUploadError('file2.exe', 'Error 2')
        ];
        $result = new FileUploadResult([], $errors, 2, 0);

        $this->assertFalse($result->isCompleteSuccess());
    }


    public function testHasSuccessfulUploadsWithSuccess(): void
    {
        $result = new FileUploadResult(['file1.jpg'], [], 1, 1);

        $this->assertTrue($result->hasSuccessfulUploads());
    }


    public function testHasSuccessfulUploadsWithPartialSuccess(): void
    {
        $errors = [new FileUploadError('file2.txt', 'Some error')];
        $result = new FileUploadResult(['file1.jpg'], $errors, 2, 1);

        $this->assertTrue($result->hasSuccessfulUploads());
    }


    public function testHasSuccessfulUploadsWithNoSuccess(): void
    {
        $errors = [new FileUploadError('file1.txt', 'Some error')];
        $result = new FileUploadResult([], $errors, 1, 0);

        $this->assertFalse($result->hasSuccessfulUploads());
    }


    public function testGetErrorMessages(): void
    {
        $errors = [
            new FileUploadError('file1.txt', 'File type not allowed'),
            new FileUploadError('file2.exe', 'File too large'),
            new FileUploadError('file3.zip', 'Upload failed')
        ];
        $result = new FileUploadResult([], $errors, 3, 0);

        $errorMessages = $result->getErrorMessages();

        $this->assertIsArray($errorMessages);
        $this->assertCount(3, $errorMessages);
        $this->assertSame('File type not allowed', $errorMessages[0]);
        $this->assertSame('File too large', $errorMessages[1]);
        $this->assertSame('Upload failed', $errorMessages[2]);
    }


    public function testGetErrorMessagesWithNoErrors(): void
    {
        $result = new FileUploadResult(['file1.jpg'], [], 1, 1);

        $errorMessages = $result->getErrorMessages();

        $this->assertIsArray($errorMessages);
        $this->assertCount(0, $errorMessages);
    }


    public function testGetErrorForFileExisting(): void
    {
        $errors = [
            new FileUploadError('file1.txt', 'File type not allowed'),
            new FileUploadError('file2.exe', 'File too large')
        ];
        $result = new FileUploadResult([], $errors, 2, 0);

        $error = $result->getErrorForFile('file1.txt');

        $this->assertInstanceOf(FileUploadError::class, $error);
        $this->assertSame('file1.txt', $error->filename);
        $this->assertSame('File type not allowed', $error->message);
    }


    public function testGetErrorForFileNonExisting(): void
    {
        $errors = [new FileUploadError('file1.txt', 'File type not allowed')];
        $result = new FileUploadResult([], $errors, 1, 0);

        $error = $result->getErrorForFile('file2.txt');

        $this->assertNull($error);
    }


    public function testGetErrorForFileWithNoErrors(): void
    {
        $result = new FileUploadResult(['file1.jpg'], [], 1, 1);

        $error = $result->getErrorForFile('file1.jpg');

        $this->assertNull($error);
    }
}
