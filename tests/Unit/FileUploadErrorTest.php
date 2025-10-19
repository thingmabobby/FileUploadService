<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FileUploadError;
use PHPUnit\Framework\TestCase;

class FileUploadErrorTest extends TestCase
{
    public function testConstructor(): void
    {
        $error = new FileUploadError('test.jpg', 'File too large', 'UPLOAD_ERR_FORM_SIZE');

        $this->assertSame('test.jpg', $error->filename);
        $this->assertSame('File too large', $error->message);
        $this->assertSame('UPLOAD_ERR_FORM_SIZE', $error->code);
    }


    public function testConstructorWithoutCode(): void
    {
        $error = new FileUploadError('test.jpg', 'File too large');

        $this->assertSame('test.jpg', $error->filename);
        $this->assertSame('File too large', $error->message);
        $this->assertSame('', $error->code);
    }


    public function testGetDescriptionWithCode(): void
    {
        $error = new FileUploadError('test.jpg', 'File too large', 'UPLOAD_ERR_FORM_SIZE');

        $this->assertSame('test.jpg: File too large (Code: UPLOAD_ERR_FORM_SIZE)', $error->getDescription());
    }


    public function testGetDescriptionWithoutCode(): void
    {
        $error = new FileUploadError('test.jpg', 'File too large');

        $this->assertSame('test.jpg: File too large', $error->getDescription());
    }


    public function testGetDescriptionWithEmptyCode(): void
    {
        $error = new FileUploadError('test.jpg', 'File too large', '');

        $this->assertSame('test.jpg: File too large', $error->getDescription());
    }
}
