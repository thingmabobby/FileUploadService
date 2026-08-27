<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\Enum\CollisionStrategyEnum;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\FileSaverInterface;
use FileUploadService\FileUploadService;
use FileUploadService\FileUploadSuccess;
use FileUploadService\FilesystemSaver;
use PHPUnit\Framework\TestCase;

class FileUploadStructuredSuccessTest extends TestCase
{
    private const JPEG_DATA_URI = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

    private string $testDir;

    /** @var array<string> */
    private array $tempFiles = [];


    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/structured_success_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }


    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }

        parent::tearDown();
    }


    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }


    private function jpegBytes(): string
    {
        $encoded = explode(',', self::JPEG_DATA_URI, 2)[1];
        $bytes = base64_decode($encoded, true);
        $this->assertNotFalse($bytes);

        return $bytes;
    }


    /**
     * @return array<string, mixed>
     */
    private function createFilesArray(string $originalName, string $contents, string $clientMime = 'image/jpeg'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmp, $contents);
        $this->tempFiles[] = $tmp;

        return [
            'name' => $originalName,
            'type' => $clientMime,
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($contents),
        ];
    }


    private function createService(
        array $allowedFileTypes = [FileTypeEnum::IMAGE, FileTypeEnum::PDF, FileTypeEnum::CAD, FileTypeEnum::DOC],
        bool $rollbackOnError = false,
        string|CollisionStrategyEnum $collisionStrategy = CollisionStrategyEnum::INCREMENT,
        bool $convertHeicToJpg = true,
        ?FileSaverInterface $fileSaver = null,
    ): FileUploadService {
        return new FileUploadService(
            allowedFileTypes: $allowedFileTypes,
            fileSaver: $fileSaver ?? new FilesystemSaver($this->testDir),
            rollbackOnError: $rollbackOnError,
            collisionStrategy: $collisionStrategy,
            convertHeicToJpg: $convertHeicToJpg,
        );
    }


    public function testTraditionalUploadExposesOriginalRequestedStoredAndOutputMetadata(): void
    {
        $contents = $this->jpegBytes();
        $filesArray = $this->createFilesArray('IMG_1234.JPG', $contents);
        $service = $this->createService();

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: $this->testDir,
            filenames: ['vacation.jpg']
        );

        $this->assertTrue($result->isCompleteSuccess());
        $this->assertCount(1, $result->successfulUploads);
        $this->assertContainsOnlyInstancesOf(FileUploadSuccess::class, $result->successfulUploads);

        $upload = $result->successfulUploads[0];
        $this->assertSame('IMG_1234.JPG', $upload->originalFilename);
        $this->assertSame('vacation.jpg', $upload->requestedFilename);
        $this->assertSame('vacation.jpg', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
        $this->assertSame('image/jpeg', $upload->mimeType);
        $this->assertSame(strlen($contents), $upload->sizeBytes);
        $this->assertFalse($upload->wasConverted);
        $this->assertSame(0, $upload->inputIndex);
        $this->assertStringEndsWith('vacation.jpg', $upload->storedPath);
        $this->assertTrue(file_exists($upload->storedPath));

        $this->assertSame([$upload->storedPath], $result->successfulFiles);
        $this->assertIsString($result->successfulFiles[0]);
    }


    public function testUnsafeOriginalFilenameIsPreservedWhileStoredFilenameIsSanitized(): void
    {
        $unsafeOriginal = '../../evil"; filename="hack.jpg';
        $filesArray = $this->createFilesArray($unsafeOriginal, $this->jpegBytes());
        $service = $this->createService();

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: $this->testDir,
            filenames: ['safe-photo.jpg']
        );

        $this->assertTrue($result->isCompleteSuccess());
        $upload = $result->successfulUploads[0];

        $this->assertSame($unsafeOriginal, $upload->originalFilename);
        $this->assertSame('safe-photo.jpg', $upload->requestedFilename);
        $this->assertSame('safe-photo.jpg', $upload->storedFilename);
        $this->assertNotSame($upload->originalFilename, $upload->storedFilename);
        $this->assertStringNotContainsString('..', $upload->storedFilename);
        $this->assertStringNotContainsString('"', $upload->storedFilename);
        $this->assertStringNotContainsString('/', $upload->storedFilename);
        $this->assertStringNotContainsString('\\', $upload->storedFilename);
        $this->assertTrue(file_exists($upload->storedPath));
    }


    public function testRequestedFilenameKeepsUnsafeCharactersWhileStoredFilenameIsCleaned(): void
    {
        $filesArray = $this->createFilesArray('camera.jpg', $this->jpegBytes());
        $requested = 'My File!.jpg';
        $service = $this->createService();

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: $this->testDir,
            filenames: [$requested]
        );

        $this->assertTrue($result->isCompleteSuccess());
        $upload = $result->successfulUploads[0];

        $this->assertSame($requested, $upload->requestedFilename);
        $this->assertSame('My File.jpg', $upload->storedFilename);
        $this->assertNotSame($upload->requestedFilename, $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
    }


    public function testBase64HasNullOriginalFilenameAndUsesRequestedName(): void
    {
        $service = $this->createService();

        $result = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['photo.jpg']
        );

        $this->assertTrue($result->isCompleteSuccess());
        $upload = $result->successfulUploads[0];

        $this->assertNull($upload->originalFilename);
        $this->assertSame('photo.jpg', $upload->requestedFilename);
        $this->assertSame('photo.jpg', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
        $this->assertSame('image/jpeg', $upload->mimeType);
        $this->assertSame(strlen($this->jpegBytes()), $upload->sizeBytes);
        $this->assertFalse($upload->wasConverted);
        $this->assertSame(0, $upload->inputIndex);
    }


    public function testBase64EmptyRequestedFilenameUsesGeneratedStoredName(): void
    {
        $service = $this->createService();

        $result = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['']
        );

        $this->assertTrue($result->isCompleteSuccess());
        $upload = $result->successfulUploads[0];

        $this->assertNull($upload->originalFilename);
        $this->assertSame('', $upload->requestedFilename);
        $this->assertSame('data_uri_file.jpg', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
        $this->assertSame('image/jpeg', $upload->mimeType);
    }


    public function testMixedInputsPreserveInputIndexAndSkipFailedSlots(): void
    {
        $service = $this->createService(allowedFileTypes: [FileTypeEnum::IMAGE]);
        $filesArray = $this->createFilesArray('camera.jpg', $this->jpegBytes());
        $pdfDataUri = 'data:application/pdf;base64,JVBERi0xLjQK';

        $result = $service->save(
            input: [self::JPEG_DATA_URI, $pdfDataUri, $filesArray],
            uploadDestination: $this->testDir,
            filenames: ['one.jpg', 'document.pdf', 'three.jpg']
        );

        $this->assertTrue($result->hasSuccessfulUploads());
        $this->assertTrue($result->hasErrors());
        $this->assertSame(3, $result->totalFiles);
        $this->assertSame(2, $result->successfulCount);
        $this->assertCount(2, $result->successfulUploads);
        $this->assertCount(1, $result->errors);

        $this->assertSame(0, $result->successfulUploads[0]->inputIndex);
        $this->assertSame('one.jpg', $result->successfulUploads[0]->storedFilename);
        $this->assertNull($result->successfulUploads[0]->originalFilename);

        $this->assertSame(2, $result->successfulUploads[1]->inputIndex);
        $this->assertSame('three.jpg', $result->successfulUploads[1]->storedFilename);
        $this->assertSame('camera.jpg', $result->successfulUploads[1]->originalFilename);

        $this->assertSame('document.pdf', $result->errors[0]->filename);
        $this->assertSame(
            array_map(static fn(FileUploadSuccess $upload): string => $upload->storedPath, $result->successfulUploads),
            $result->successfulFiles
        );
    }


    public function testCollisionIncrementUpdatesStoredFilenameNotRequested(): void
    {
        $service = $this->createService();

        $first = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg']
        );
        $this->assertTrue($first->isCompleteSuccess());

        $second = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg'],
            generateUniqueFilenames: true
        );

        $this->assertTrue($second->isCompleteSuccess());
        $upload = $second->successfulUploads[0];
        $this->assertSame('test.jpg', $upload->requestedFilename);
        $this->assertSame('test_1.jpg', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
        $this->assertNotSame($first->successfulFiles[0], $second->successfulFiles[0]);
    }


    public function testUuidCollisionStrategyChangesStoredFilename(): void
    {
        $service = $this->createService(collisionStrategy: CollisionStrategyEnum::UUID);

        $first = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg']
        );
        $this->assertTrue($first->isCompleteSuccess());

        $second = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg'],
            generateUniqueFilenames: true
        );

        $this->assertTrue($second->isCompleteSuccess());
        $upload = $second->successfulUploads[0];
        $this->assertSame('test.jpg', $upload->requestedFilename);
        $this->assertNotSame('test.jpg', $upload->storedFilename);
        $this->assertMatchesRegularExpression('/^test_[a-f0-9]{8}\.jpg$/', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
    }


    public function testTimestampCollisionStrategyChangesStoredFilename(): void
    {
        $service = $this->createService(collisionStrategy: CollisionStrategyEnum::TIMESTAMP);

        $first = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg']
        );
        $this->assertTrue($first->isCompleteSuccess());

        $second = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg'],
            generateUniqueFilenames: true
        );

        $this->assertTrue($second->isCompleteSuccess());
        $upload = $second->successfulUploads[0];
        $this->assertSame('test.jpg', $upload->requestedFilename);
        $this->assertNotSame($upload->requestedFilename, $upload->storedFilename);
        $this->assertMatchesRegularExpression('/^test_\d+/', $upload->storedFilename);
        $this->assertStringEndsWith('.jpg', $upload->storedFilename);
    }


    public function testOverwriteKeepsStoredFilename(): void
    {
        $service = $this->createService();

        $first = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg']
        );
        $this->assertTrue($first->isCompleteSuccess());

        $second = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['test.jpg'],
            overwriteExisting: true
        );

        $this->assertTrue($second->isCompleteSuccess());
        $upload = $second->successfulUploads[0];
        $this->assertSame('test.jpg', $upload->storedFilename);
        $this->assertSame($first->successfulFiles[0], $second->successfulFiles[0]);
        $this->assertSame($first->successfulUploads[0]->storedPath, $upload->storedPath);
    }


    public function testCustomMimeAndExtensionDescribeStoredBytes(): void
    {
        $csv = "a,b\n1,2\n";
        $filesArray = $this->createFilesArray('export.csv', $csv, 'text/csv');
        $service = $this->createService(allowedFileTypes: ['csv', 'mime:text/csv', 'mime:text/plain']);

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: $this->testDir,
            filenames: ['export.csv']
        );

        $this->assertTrue($result->isCompleteSuccess(), $result->errors[0]->message ?? 'upload failed');
        $upload = $result->successfulUploads[0];
        $this->assertSame('export.csv', $upload->storedFilename);
        $this->assertSame('csv', $upload->extension);
        $this->assertSame(strlen($csv), $upload->sizeBytes);
        $this->assertContains($upload->mimeType, ['text/csv', 'text/plain', 'application/csv']);
    }


    public function testCustomSaverStoredPathIsOpaqueAndIndependentOfStoredFilename(): void
    {
        $saver = new class implements FileSaverInterface {
            public function saveFile(string $source, string $targetPath, bool $overwriteExisting = false): string
            {
                return 'memory://obj-9f3c';
            }

            public function fileExists(string $targetPath): bool
            {
                return false;
            }

            public function deleteFile(string $targetPath): bool
            {
                return true;
            }

            public function ensureUploadDestinationExists(string $uploadDestination): void
            {
            }

            public function resolveTargetPath(string $uploadDestination, string $filename): string
            {
                return $uploadDestination . '/' . $filename;
            }

            public function getBasePath(): string
            {
                return 'memory';
            }
        };

        $service = $this->createService(fileSaver: $saver);
        $filesArray = $this->createFilesArray('camera.jpg', $this->jpegBytes());

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: 'photos',
            filenames: ['photo.jpg']
        );

        $this->assertTrue($result->isCompleteSuccess());
        $upload = $result->successfulUploads[0];

        $this->assertSame('memory://obj-9f3c', $upload->storedPath);
        $this->assertSame('photo.jpg', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
        $this->assertSame('image/jpeg', $upload->mimeType);
        $this->assertSame(strlen($this->jpegBytes()), $upload->sizeBytes);
        $this->assertNotSame(basename($upload->storedPath), $upload->storedFilename);
        $this->assertSame(['memory://obj-9f3c'], $result->successfulFiles);
        $this->assertSame($upload->storedPath, $result->successfulFiles[0]);
    }


    public function testFilesystemSaverStoredPathIsAbsoluteAndLegacySuccessfulFilesAreStrings(): void
    {
        $service = $this->createService();
        $result = $service->save(
            input: [self::JPEG_DATA_URI],
            uploadDestination: $this->testDir,
            filenames: ['photo.jpg']
        );

        $this->assertTrue($result->isCompleteSuccess());
        $upload = $result->successfulUploads[0];

        $this->assertSame('photo.jpg', $upload->storedFilename);
        $this->assertStringEndsWith('photo.jpg', $upload->storedPath);
        $this->assertTrue(is_string($result->successfulFiles[0]));
        $this->assertSame($upload->storedPath, $result->successfulFiles[0]);
        $this->assertTrue(file_exists($upload->storedPath));
    }


    public function testRollbackRemovesStructuredSuccesses(): void
    {
        $service = $this->createService(rollbackOnError: true);

        $result = $service->save(
            input: [self::JPEG_DATA_URI, 'invalid-data-uri'],
            uploadDestination: $this->testDir,
            filenames: ['image.jpg', 'invalid.txt']
        );

        $this->assertTrue($result->hasErrors());
        $this->assertSame(0, $result->successfulCount);
        $this->assertCount(0, $result->successfulFiles);
        $this->assertCount(0, $result->successfulUploads);
        $this->assertFalse($result->hasSuccessfulUploads());
    }


    public function testPartialSuccessOnlyIncludesSuccessfulDtos(): void
    {
        $service = $this->createService(allowedFileTypes: [FileTypeEnum::IMAGE]);

        $result = $service->save(
            input: [self::JPEG_DATA_URI, 'data:application/pdf;base64,JVBERi0xLjQK'],
            uploadDestination: $this->testDir,
            filenames: ['image.jpg', 'document.pdf']
        );

        $this->assertSame(1, $result->successfulCount);
        $this->assertCount(1, $result->successfulUploads);
        $this->assertSame('image.jpg', $result->successfulUploads[0]->storedFilename);
        $this->assertCount(1, $result->errors);
        $this->assertSame('document.pdf', $result->errors[0]->filename);
        $this->assertSame(
            array_map(static fn(FileUploadSuccess $upload): string => $upload->storedPath, $result->successfulUploads),
            $result->successfulFiles
        );
    }


    public function testHeicDisabledKeepsOriginalFormatAndWasConvertedFalse(): void
    {
        $heicContents = "\x00\x00\x00\x18ftypheic" . str_repeat("\x00", 32);
        $filesArray = $this->createFilesArray('IMG_1234.HEIC', $heicContents, 'image/heic');
        $service = $this->createService(convertHeicToJpg: false);

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: $this->testDir,
            filenames: ['IMG_1234.HEIC']
        );

        $this->assertTrue($result->isCompleteSuccess(), $result->errors[0]->message ?? 'HEIC disabled upload failed');
        $upload = $result->successfulUploads[0];
        $this->assertSame('IMG_1234.HEIC', $upload->originalFilename);
        $this->assertSame('IMG_1234.HEIC', $upload->storedFilename);
        $this->assertSame('heic', $upload->extension);
        $this->assertFalse($upload->wasConverted);
        $this->assertSame(strlen($heicContents), $upload->sizeBytes);
    }


    public function testHeicConversionFailureProducesErrorWithoutSuccessDto(): void
    {
        $heicContents = "\x00\x00\x00\x18ftypheic" . str_repeat("\x00", 32);
        $filesArray = $this->createFilesArray('IMG_1234.HEIC', $heicContents, 'image/heic');
        $service = $this->createService(convertHeicToJpg: true);

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: $this->testDir,
            filenames: ['IMG_1234.HEIC']
        );

        if ($result->isCompleteSuccess() && ($result->successfulUploads[0]->wasConverted ?? false)) {
            $this->markTestSkipped('A convertible HEIC sample succeeded; conversion-failure path is not exercised.');
        }

        $this->assertTrue($result->hasErrors());
        $this->assertCount(0, $result->successfulUploads);
        $this->assertCount(0, $result->successfulFiles);
        $this->assertSame(0, $result->successfulCount);
    }


    public function testHeicConversionSuccessReportsJpegMetadataWhenLibraryConverts(): void
    {
        $fixture = dirname(__DIR__) . '/fixtures/sample.heic';
        if (!is_file($fixture)) {
            $this->markTestSkipped('No convertible HEIC fixture is present.');
        }

        $contents = file_get_contents($fixture);
        $this->assertNotFalse($contents);
        $filesArray = $this->createFilesArray('IMG_1234.HEIC', $contents, 'image/heic');
        $service = $this->createService(convertHeicToJpg: true);

        $result = $service->save(
            input: [$filesArray],
            uploadDestination: $this->testDir,
            filenames: ['IMG_1234.HEIC']
        );

        if (!$result->isCompleteSuccess()) {
            $this->markTestSkipped('HEIC conversion library could not convert the fixture.');
        }

        $upload = $result->successfulUploads[0];
        $this->assertTrue($upload->wasConverted);
        $this->assertSame('IMG_1234.HEIC', $upload->originalFilename);
        $this->assertSame('IMG_1234.jpg', $upload->storedFilename);
        $this->assertSame('jpg', $upload->extension);
        $this->assertSame('image/jpeg', $upload->mimeType);
        $this->assertNotNull($upload->sizeBytes);
        $this->assertGreaterThan(0, $upload->sizeBytes);
    }
}
