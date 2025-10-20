<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Enum;

use FileUploadService\Enum\FileTypeEnum;
use PHPUnit\Framework\TestCase;

class FileTypeEnumTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('image', FileTypeEnum::IMAGE->value);
        $this->assertSame('pdf', FileTypeEnum::PDF->value);
        $this->assertSame('cad', FileTypeEnum::CAD->value);
        $this->assertSame('doc', FileTypeEnum::DOC->value);
        $this->assertSame('archive', FileTypeEnum::ARCHIVE->value);
        $this->assertSame('video', FileTypeEnum::VIDEO->value);
        $this->assertSame('all', FileTypeEnum::ALL->value);
    }


    public function testGetLabel(): void
    {
        $this->assertSame('Images', FileTypeEnum::IMAGE->getLabel());
        $this->assertSame('PDF Documents', FileTypeEnum::PDF->getLabel());
        $this->assertSame('CAD Files', FileTypeEnum::CAD->getLabel());
        $this->assertSame('Documents', FileTypeEnum::DOC->getLabel());
        $this->assertSame('Archives', FileTypeEnum::ARCHIVE->getLabel());
        $this->assertSame('Videos', FileTypeEnum::VIDEO->getLabel());
        $this->assertSame('All Files', FileTypeEnum::ALL->getLabel());
    }


    public function testGetAllValues(): void
    {
        $values = FileTypeEnum::getAllValues();

        $this->assertIsArray($values);
        $this->assertCount(7, $values);
        $this->assertContains('image', $values);
        $this->assertContains('pdf', $values);
        $this->assertContains('cad', $values);
        $this->assertContains('doc', $values);
        $this->assertContains('archive', $values);
        $this->assertContains('video', $values);
        $this->assertContains('all', $values);
    }


    public function testEnumCases(): void
    {
        $cases = FileTypeEnum::cases();

        $this->assertIsArray($cases);
        $this->assertCount(7, $cases);

        foreach ($cases as $case) {
            $this->assertInstanceOf(FileTypeEnum::class, $case);
        }
    }


    public function testTryFromValidValue(): void
    {
        $this->assertSame(FileTypeEnum::IMAGE, FileTypeEnum::tryFrom('image'));
        $this->assertSame(FileTypeEnum::PDF, FileTypeEnum::tryFrom('pdf'));
        $this->assertSame(FileTypeEnum::CAD, FileTypeEnum::tryFrom('cad'));
        $this->assertSame(FileTypeEnum::DOC, FileTypeEnum::tryFrom('doc'));
        $this->assertSame(FileTypeEnum::ARCHIVE, FileTypeEnum::tryFrom('archive'));
        $this->assertSame(FileTypeEnum::ALL, FileTypeEnum::tryFrom('all'));
    }


    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(FileTypeEnum::tryFrom('invalid'));
        $this->assertNull(FileTypeEnum::tryFrom(''));
        $this->assertNull(FileTypeEnum::tryFrom('IMAGE'));
        $this->assertNull(FileTypeEnum::tryFrom('pdf '));
    }


    public function testFromValidValue(): void
    {
        $this->assertSame(FileTypeEnum::IMAGE, FileTypeEnum::from('image'));
        $this->assertSame(FileTypeEnum::PDF, FileTypeEnum::from('pdf'));
    }


    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        FileTypeEnum::from('invalid');
    }
}
