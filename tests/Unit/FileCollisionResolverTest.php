<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\Enum\CollisionStrategyEnum;
use FileUploadService\FileCollisionResolver;
use FileUploadService\FileServiceValidator;
use PHPUnit\Framework\TestCase;

class FileCollisionResolverTest extends TestCase
{
    private FileCollisionResolver $resolver;
    private string $testDir;


    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/collision_test_' . uniqid();
        mkdir($this->testDir, 0777, true);

        $validator = new FileServiceValidator();
        $this->resolver = new FileCollisionResolver($validator, CollisionStrategyEnum::INCREMENT->value);
    }


    protected function tearDown(): void
    {
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


    public function testConstructor(): void
    {
        $validator = new FileServiceValidator();
        $resolver = new FileCollisionResolver($validator, CollisionStrategyEnum::INCREMENT->value);

        $this->assertInstanceOf(FileCollisionResolver::class, $resolver);
    }


    public function testGenerateUniqueFilenameNoConflict(): void
    {
        $filename = $this->resolver->generateUniqueFilename($this->testDir, 'testfile');

        $this->assertSame('testfile', $filename);
    }


    public function testGenerateUniqueFilenameWithConflict(): void
    {
        // Create a file that would conflict
        file_put_contents($this->testDir . '/testfile.jpg', 'test content');

        $filename = $this->resolver->generateUniqueFilename($this->testDir, 'testfile');

        $this->assertSame('testfile_1', $filename);
    }


    public function testGenerateUniqueFilenameWithMultipleConflicts(): void
    {
        // Create multiple conflicting files
        file_put_contents($this->testDir . '/testfile.jpg', 'test content');
        file_put_contents($this->testDir . '/testfile_1.png', 'test content');

        $filename = $this->resolver->generateUniqueFilename($this->testDir, 'testfile');

        $this->assertSame('testfile_2', $filename);
    }


    public function testGenerateUniqueFilenames(): void
    {
        $baseFilenames = ['file1', 'file2', 'file3'];

        $uniqueFilenames = $this->resolver->generateUniqueFilenames($this->testDir, $baseFilenames);

        $this->assertIsArray($uniqueFilenames);
        $this->assertCount(3, $uniqueFilenames);
        $this->assertSame(['file1', 'file2', 'file3'], $uniqueFilenames);
    }


    public function testGenerateUniqueFilenamesWithUsedFilenames(): void
    {
        $baseFilenames = ['file1', 'file1', 'file2']; // Duplicate file1
        $usedFilenames = ['existing_file'];

        $uniqueFilenames = $this->resolver->generateUniqueFilenames($this->testDir, $baseFilenames, $usedFilenames);

        $this->assertIsArray($uniqueFilenames);
        $this->assertCount(3, $uniqueFilenames);
        $this->assertSame(['file1', 'file1_1', 'file2'], $uniqueFilenames);
    }


    public function testResolveWithIncrement(): void
    {
        // Create conflicting files
        file_put_contents($this->testDir . '/testfile.jpg', 'test content');
        file_put_contents($this->testDir . '/testfile_1.png', 'test content');

        $filename = $this->resolver->resolveWithIncrement('testfile', $this->testDir, ['jpg', 'png', 'gif'], []);

        $this->assertSame('testfile_2', $filename);
    }


    public function testResolveWithIncrementSafetyLimit(): void
    {
        // Create many conflicting files to test safety limit
        for ($i = 1; $i <= 1001; $i++) {
            file_put_contents($this->testDir . "/testfile_{$i}.jpg", 'test content');
        }

        $filename = $this->resolver->resolveWithIncrement('testfile', $this->testDir, ['jpg'], []);

        // Should return a filename with random suffix due to safety limit
        $this->assertStringStartsWith('testfile_', $filename);
        $this->assertNotSame('testfile_1002', $filename);
    }


    public function testResolveWithUuid(): void
    {
        $validator = new FileServiceValidator();
        $uuidResolver = new FileCollisionResolver($validator, CollisionStrategyEnum::UUID->value);

        $filename = $uuidResolver->resolveWithUuid('testfile', $this->testDir, ['jpg', 'png'], []);

        $this->assertStringStartsWith('testfile_', $filename);
        $this->assertMatchesRegularExpression('/^testfile_[a-f0-9]{8}$/', $filename);
    }


    public function testResolveWithTimestamp(): void
    {
        $validator = new FileServiceValidator();
        $timestampResolver = new FileCollisionResolver($validator, CollisionStrategyEnum::TIMESTAMP->value);

        $filename = $timestampResolver->resolveWithTimestamp('testfile', $this->testDir, ['jpg', 'png'], []);

        $this->assertStringStartsWith('testfile_', $filename);
        $this->assertMatchesRegularExpression('/^testfile_\d+$/', $filename);
    }


    public function testResolveWithCustomStrategy(): void
    {
        $customStrategy = function ($baseFilename, $uploadDir, $possibleExtensions, $usedFilenames) {
            return $baseFilename . '_custom_' . time();
        };

        $validator = new FileServiceValidator();
        $customResolver = new FileCollisionResolver($validator, $customStrategy);

        $filename = $customResolver->resolveWithCustomStrategy(
            $customStrategy,
            'testfile',
            $this->testDir,
            ['jpg', 'png'],
            []
        );

        $this->assertStringStartsWith('testfile_custom_', $filename);
    }


    public function testIsFilenameUniqueNoConflict(): void
    {
        $isUnique = $this->resolver->isFilenameUnique('testfile', $this->testDir, ['jpg', 'png'], []);

        $this->assertTrue($isUnique);
    }


    public function testIsFilenameUniqueWithFileConflict(): void
    {
        file_put_contents($this->testDir . '/testfile.jpg', 'test content');

        $isUnique = $this->resolver->isFilenameUnique('testfile', $this->testDir, ['jpg', 'png'], []);

        $this->assertFalse($isUnique);
    }


    public function testIsFilenameUniqueWithUsedFilenameConflict(): void
    {
        $isUnique = $this->resolver->isFilenameUnique('testfile', $this->testDir, ['jpg', 'png'], ['testfile']);

        $this->assertFalse($isUnique);
    }


    public function testGenerateShortUuid(): void
    {
        $uuid = $this->resolver->generateShortUuid();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $uuid);
        $this->assertSame(8, strlen($uuid));
    }


    public function testGetAllPossibleExtensions(): void
    {
        $extensions = $this->resolver->getAllPossibleExtensions();

        $this->assertIsArray($extensions);
        $this->assertContains('jpg', $extensions);
        $this->assertContains('png', $extensions);
        $this->assertContains('pdf', $extensions);
        $this->assertContains('doc', $extensions);
        $this->assertContains('dwg', $extensions);
        $this->assertContains('zip', $extensions);
    }


    public function testFilterExtensionsByAllowedTypes(): void
    {
        $allExtensions = ['jpg', 'png', 'pdf', 'doc', 'dwg', 'zip'];
        $allowedTypes = ['image', 'pdf'];

        $filteredExtensions = $this->resolver->filterExtensionsByAllowedTypes($allExtensions, $allowedTypes);

        $this->assertIsArray($filteredExtensions);
        $this->assertContains('jpg', $filteredExtensions);
        $this->assertContains('png', $filteredExtensions);
        $this->assertContains('pdf', $filteredExtensions);
        $this->assertNotContains('doc', $filteredExtensions);
        $this->assertNotContains('dwg', $filteredExtensions);
        $this->assertNotContains('zip', $filteredExtensions);
    }


    public function testFilterExtensionsByAllowedTypesWithSpecificExtensions(): void
    {
        $allExtensions = ['jpg', 'png', 'pdf', 'doc', 'dwg', 'zip'];
        $allowedTypes = ['jpg', 'doc']; // Specific extensions

        $filteredExtensions = $this->resolver->filterExtensionsByAllowedTypes($allExtensions, $allowedTypes);

        $this->assertIsArray($filteredExtensions);
        $this->assertContains('jpg', $filteredExtensions);
        $this->assertContains('doc', $filteredExtensions);
        $this->assertNotContains('png', $filteredExtensions);
        $this->assertNotContains('pdf', $filteredExtensions);
        $this->assertNotContains('dwg', $filteredExtensions);
        $this->assertNotContains('zip', $filteredExtensions);
    }
}
