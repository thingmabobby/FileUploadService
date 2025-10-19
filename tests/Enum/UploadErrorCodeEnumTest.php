<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Enum;

use FileUploadService\Enum\UploadErrorCodeEnum;
use PHPUnit\Framework\TestCase;

class UploadErrorCodeEnumTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame(UPLOAD_ERR_OK, UploadErrorCodeEnum::OK->value);
        $this->assertSame(UPLOAD_ERR_INI_SIZE, UploadErrorCodeEnum::INI_SIZE->value);
        $this->assertSame(UPLOAD_ERR_FORM_SIZE, UploadErrorCodeEnum::FORM_SIZE->value);
        $this->assertSame(UPLOAD_ERR_PARTIAL, UploadErrorCodeEnum::PARTIAL->value);
        $this->assertSame(UPLOAD_ERR_NO_FILE, UploadErrorCodeEnum::NO_FILE->value);
        $this->assertSame(UPLOAD_ERR_NO_TMP_DIR, UploadErrorCodeEnum::NO_TMP_DIR->value);
        $this->assertSame(UPLOAD_ERR_CANT_WRITE, UploadErrorCodeEnum::CANT_WRITE->value);
        $this->assertSame(UPLOAD_ERR_EXTENSION, UploadErrorCodeEnum::EXTENSION->value);
    }


    public function testGetMessage(): void
    {
        $this->assertSame('No error', UploadErrorCodeEnum::OK->getMessage());
        $this->assertSame('File exceeds upload_max_filesize directive', UploadErrorCodeEnum::INI_SIZE->getMessage());
        $this->assertSame('File exceeds MAX_FILE_SIZE directive', UploadErrorCodeEnum::FORM_SIZE->getMessage());
        $this->assertSame('File was only partially uploaded', UploadErrorCodeEnum::PARTIAL->getMessage());
        $this->assertSame('No file was uploaded', UploadErrorCodeEnum::NO_FILE->getMessage());
        $this->assertSame('Missing temporary folder', UploadErrorCodeEnum::NO_TMP_DIR->getMessage());
        $this->assertSame('Failed to write file to disk', UploadErrorCodeEnum::CANT_WRITE->getMessage());
        $this->assertSame('File upload stopped by extension', UploadErrorCodeEnum::EXTENSION->getMessage());
    }


    public function testFromIntValidCodes(): void
    {
        $this->assertSame(UploadErrorCodeEnum::OK, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_OK));
        $this->assertSame(UploadErrorCodeEnum::INI_SIZE, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_INI_SIZE));
        $this->assertSame(UploadErrorCodeEnum::FORM_SIZE, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_FORM_SIZE));
        $this->assertSame(UploadErrorCodeEnum::PARTIAL, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_PARTIAL));
        $this->assertSame(UploadErrorCodeEnum::NO_FILE, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_NO_FILE));
        $this->assertSame(UploadErrorCodeEnum::NO_TMP_DIR, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_NO_TMP_DIR));
        $this->assertSame(UploadErrorCodeEnum::CANT_WRITE, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_CANT_WRITE));
        $this->assertSame(UploadErrorCodeEnum::EXTENSION, UploadErrorCodeEnum::fromInt(UPLOAD_ERR_EXTENSION));
    }


    public function testFromIntInvalidCode(): void
    {
        $this->assertNull(UploadErrorCodeEnum::fromInt(999));
        $this->assertNull(UploadErrorCodeEnum::fromInt(-1));
        $this->assertNull(UploadErrorCodeEnum::fromInt(5)); // UPLOAD_ERR_NO_TMP_DIR is 6, not 5
    }


    public function testIsSuccess(): void
    {
        $this->assertTrue(UploadErrorCodeEnum::OK->isSuccess());
        $this->assertFalse(UploadErrorCodeEnum::INI_SIZE->isSuccess());
        $this->assertFalse(UploadErrorCodeEnum::FORM_SIZE->isSuccess());
        $this->assertFalse(UploadErrorCodeEnum::PARTIAL->isSuccess());
        $this->assertFalse(UploadErrorCodeEnum::NO_FILE->isSuccess());
        $this->assertFalse(UploadErrorCodeEnum::NO_TMP_DIR->isSuccess());
        $this->assertFalse(UploadErrorCodeEnum::CANT_WRITE->isSuccess());
        $this->assertFalse(UploadErrorCodeEnum::EXTENSION->isSuccess());
    }


    public function testEnumCases(): void
    {
        $cases = UploadErrorCodeEnum::cases();

        $this->assertIsArray($cases);
        $this->assertCount(8, $cases);

        foreach ($cases as $case) {
            $this->assertInstanceOf(UploadErrorCodeEnum::class, $case);
        }
    }
}
