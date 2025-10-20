<?php

declare(strict_types=1);

namespace FileUploadService\Tests\Unit;

use FileUploadService\FileUploadService;
use FileUploadService\FilesystemSaver;
use PHPUnit\Framework\TestCase;

/**
 * Tests for default behavior and configuration
 * Validates that the service works correctly with default settings
 */
class DefaultBehaviorTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/default_behavior_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
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

    /**
     * Test that FilesystemSaver is created from uploadDestination
     */
    public function testFilesystemSaverCreatedFromUploadDestination(): void
    {
        // Test with absolute path
        $absolutePath = $this->testDir . '/uploads';
        $fileSaver = FilesystemSaver::fromUploadDestination($absolutePath);
        
        $this->assertInstanceOf(FilesystemSaver::class, $fileSaver);
        $this->assertEquals($this->testDir, $fileSaver->getBasePath());
        
        // Test with relative path
        $fileSaver2 = FilesystemSaver::fromUploadDestination('uploads');
        $expectedPath = getcwd() ?: sys_get_temp_dir();
        
        $this->assertEquals($expectedPath, $fileSaver2->getBasePath());
    }

    /**
     * Test default file type restrictions
     */
    public function testDefaultFileTypeRestrictions(): void
    {
        // Create service with no parameters
        $service = new FileUploadService();

        // Should default to IMAGE, PDF, CAD
        $this->assertTrue($service->isFileTypeCategoryAllowed('image'));
        $this->assertTrue($service->isFileTypeCategoryAllowed('pdf'));
        $this->assertTrue($service->isFileTypeCategoryAllowed('cad'));
        
        // Should NOT allow other types by default
        $this->assertFalse($service->isFileTypeCategoryAllowed('video'));
        $this->assertFalse($service->isFileTypeCategoryAllowed('doc'));
        $this->assertFalse($service->isFileTypeCategoryAllowed('archive'));
    }

    /**
     * Test default directory permissions
     */
    public function testDefaultDirectoryPermissions(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);

        // Save a file which should create a subdirectory
        $fileSaver->saveFile('test content', 'subdir/test.txt');

        // Check that the directory was created
        $this->assertTrue(is_dir($this->testDir . '/subdir'));

        // Check permissions (if not on Windows)
        if (PHP_OS_FAMILY !== 'Windows') {
            $perms = fileperms($this->testDir . '/subdir');
            $actualPerms = $perms & 0777;
            
            // Default is 0775
            $this->assertEquals(0775, $actualPerms);
        } else {
            $this->markTestSkipped('Permission check not applicable on Windows');
        }
    }

    /**
     * Test default collision strategy is increment (via constructor inspection)
     */
    public function testDefaultCollisionStrategyIsIncrement(): void
    {
        $service = new FileUploadService(['all']);

        // Use reflection to check the collisionStrategy property
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('collisionStrategy');
        $property->setAccessible(true);
        $strategy = $property->getValue($service);

        // Default should be INCREMENT enum
        $this->assertEquals('increment', is_object($strategy) ? $strategy->value : $strategy);
    }

    /**
     * Test default HEIC conversion enabled
     */
    public function testDefaultHeicConversionEnabled(): void
    {
        $service = new FileUploadService(['image']);

        $this->assertTrue($service->isHeicConversionEnabled());
    }

    /**
     * Test default rollback disabled
     */
    public function testDefaultRollbackDisabled(): void
    {
        $service = new FileUploadService(['all']);

        $this->assertFalse($service->isRollbackOnErrorEnabled());
    }

    /**
     * Test default directory creation enabled
     */
    public function testDefaultDirectoryCreationEnabled(): void
    {
        $fileSaver = new FilesystemSaver($this->testDir);

        // This should automatically create the directory
        $result = $fileSaver->saveFile('test content', 'deep/nested/directory/file.txt');

        $this->assertTrue($fileSaver->fileExists($result));
        $this->assertTrue(is_dir($this->testDir . '/deep/nested/directory'));
    }

    /**
     * Test that empty allowed file types falls back to defaults
     */
    public function testEmptyAllowedFileTypesFallsBackToDefaults(): void
    {
        $service = new FileUploadService([]);

        // Should fall back to IMAGE, PDF, CAD
        $this->assertTrue($service->isFileTypeCategoryAllowed('image'));
        $this->assertTrue($service->isFileTypeCategoryAllowed('pdf'));
        $this->assertTrue($service->isFileTypeCategoryAllowed('cad'));
    }

    /**
     * Test unrestricted mode with 'all' keyword
     */
    public function testUnrestrictedModeWithAllKeyword(): void
    {
        $service = new FileUploadService(['all']);

        $this->assertTrue($service->isUnrestricted());
        
        // When unrestricted, these methods check if extensions are allowed
        $this->assertTrue($service->isFileTypeAllowedByExtension('jpg'));
        $this->assertTrue($service->isFileTypeAllowedByExtension('pdf'));
        $this->assertTrue($service->isFileTypeAllowedByExtension('mp4'));
        $this->assertTrue($service->isFileTypeAllowedByExtension('doc'));
        $this->assertTrue($service->isFileTypeAllowedByExtension('dwg'));
        $this->assertTrue($service->isFileTypeAllowedByExtension('zip'));
    }

    /**
     * Test high performance mode changes collision strategy to UUID
     */
    public function testHighPerformanceModeUsesUuidStrategy(): void
    {
        $service = new FileUploadService(
            allowedFileTypes: ['all'],
            highPerformanceMode: true
        );

        // Use reflection to access the collisionResolver
        $reflection = new \ReflectionClass($service);
        $resolverProperty = $reflection->getProperty('collisionResolver');
        $resolverProperty->setAccessible(true);
        $resolver = $resolverProperty->getValue($service);

        // Get the collision strategy from the resolver
        $resolverReflection = new \ReflectionClass($resolver);
        $strategyProperty = $resolverReflection->getProperty('collisionStrategy');
        $strategyProperty->setAccessible(true);
        $strategy = $strategyProperty->getValue($resolver);

        // In high performance mode, should be UUID
        $this->assertEquals('uuid', $strategy);
    }

    /**
     * Test that FilesystemSaver with custom directory permissions works
     */
    public function testCustomDirectoryPermissions(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Permission test not applicable on Windows');
        }

        $customPerms = 0755;
        $fileSaver = new FilesystemSaver($this->testDir, $customPerms, true);

        // Save a file which creates a subdirectory
        $fileSaver->saveFile('test', 'custom/test.txt');

        $perms = fileperms($this->testDir . '/custom');
        $actualPerms = $perms & 0777;

        $this->assertEquals($customPerms, $actualPerms);
    }
}

