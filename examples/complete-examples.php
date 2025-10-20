<?php

require_once 'vendor/autoload.php';

use FileUploadService\FileUploadService;
use FileUploadService\FilesystemSaver;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\CollisionStrategyEnum;

echo "File Upload Service - Complete Usage Examples (Updated)\n";
echo "======================================================\n\n";

// Example 1: Basic configuration with video support
echo "1. Basic Configuration with Video Support:\n";
$service = new FileUploadService([
    'image',
    'video',  // New video support
    'pdf',
    'cad'
]);

echo "   - File types: image, video, pdf, cad\n";
echo "   - HEIC conversion: enabled (default)\n";
echo "   - Rollback on error: disabled (default)\n";
echo "   - Collision strategy: increment (default)\n\n";

// Example 2: Custom MIME types and extensions
echo "2. Custom MIME Types and Extensions:\n";
$service = new FileUploadService([
    'image',
    'video',
    'mime:text/csv',           // Custom CSV MIME type
    'mime:application/x-custom', // Custom application MIME type
    '.xyz',                    // Custom extension
    '.custom'                  // Another custom extension
]);

echo "   - Image and video categories\n";
echo "   - Custom MIME types: text/csv, application/x-custom\n";
echo "   - Custom extensions: xyz, custom\n\n";

// Example 3: Advanced configuration with all options
echo "3. Advanced Configuration:\n";
$filesystemSaver = new FilesystemSaver('/tmp/uploads', 0755, true);
$service = new FileUploadService(
    allowedFileTypes: ['image', 'video', 'pdf', 'doc', 'archive'],
    fileSaver: $filesystemSaver,
    createDirectory: true,
    directoryPermissions: 0755,
    rollbackOnError: true,
    collisionStrategy: 'uuid',
    highPerformanceMode: false,
    convertHeicToJpg: true
);

echo "   - Custom storage directory: /tmp/uploads\n";
echo "   - Rollback on error: enabled\n";
echo "   - Collision strategy: UUID\n";
echo "   - HEIC conversion: enabled\n";
echo "   - File types: image, video, pdf, doc, archive\n\n";

// Example 4: High-performance mode
echo "4. High-Performance Mode:\n";
$service = new FileUploadService(
    allowedFileTypes: ['image', 'video'],
    highPerformanceMode: true
);

echo "   - Automatically uses UUID collision strategy\n";
echo "   - Optimized for network storage\n";
echo "   - Reduces filesystem calls\n\n";

// Example 5: File type management with new methods
echo "5. File Type Management (Updated):\n";
$service = new FileUploadService(['image', 'video']);

// Add more file types using new methods
$service->setAllowedCategories(['image', 'video', 'pdf']);
echo "   - Added categories: pdf\n";

$service->setAllowedExtensions(['xyz', 'custom']);
echo "   - Added extensions: xyz, custom\n";

$service->setAllowedMimeTypes(['text/csv', 'application/x-custom']);
echo "   - Added MIME types: text/csv, application/x-custom\n";

// Remove specific types
$service->disallowFileType('pdf');
echo "   - Removed: pdf\n";

// Check if type is allowed
echo "   - Image allowed: " . ($service->isFileTypeCategoryAllowed('image') ? 'Yes' : 'No') . "\n";
echo "   - Video allowed: " . ($service->isFileTypeCategoryAllowed('video') ? 'Yes' : 'No') . "\n";
echo "   - PDF allowed: " . ($service->isFileTypeCategoryAllowed('pdf') ? 'Yes' : 'No') . "\n";
echo "   - Current restrictions: " . $service->getRestrictionDescription() . "\n\n";

// Example 6: Base64 data URI upload with custom MIME types
echo "6. Base64 Data URI Upload with Custom MIME Types:\n";
$imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';
$csvDataUri = 'data:text/csv;base64,LG5hbWUsZW1haWwsYWdlCkpvaG4sam9obkBleGFtcGxlLmNvbSwyNQpKYW5lLGphbmVAZXhhbXBsZS5jb20sMzA=';

$result = $service->save(
    [$imageDataUri, $csvDataUri],
    'uploads',
    ['image1.jpg', 'data.csv'],
    true // Overwrite existing files
);

if ($result->hasSuccessfulUploads()) {
    echo "   - Upload successful: " . $result->successfulCount . "/" . $result->totalFiles . " files\n";
    foreach ($result->successfulFiles as $filePath) {
        echo "   - Saved: " . $filePath . "\n";
    }
} else {
    echo "   - Upload failed\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "   - Error: $error\n";
    }
}
echo "\n";

// Example 7: $_FILES array upload with video support
echo "7. \$_FILES Array Upload with Video Support:\n";
// Simulate a video file upload
$videoFiles = [
    'name' => 'sample.mp4',
    'type' => 'video/mp4',
    'tmp_name' => tempnam(sys_get_temp_dir(), 'video_'),
    'error' => 0,
    'size' => 1024000
];

// Create a temporary file with some content
file_put_contents($videoFiles['tmp_name'], 'Fake video content for demo');

$result = $service->save(
    [$videoFiles],
    'uploads',
    ['sample.mp4'],
    true // Overwrite existing files
);

if ($result->hasSuccessfulUploads()) {
    echo "   - Video upload successful: " . $result->successfulFiles[0] . "\n";
} else {
    echo "   - Video upload failed\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "   - Error: $error\n";
    }
}

// Clean up
if (file_exists($videoFiles['tmp_name'])) {
    unlink($videoFiles['tmp_name']);
}
echo "\n";

// Example 8: Multiple file upload with mixed types
echo "8. Multiple File Upload with Mixed Types:\n";
$multipleInputs = [
    $imageDataUri,  // Base64 image
    $csvDataUri,    // Base64 CSV
    $videoFiles     // $_FILES video
];
$filenames = ['image2.jpg', 'data2.csv', 'video2.mp4'];

$result = $service->save($multipleInputs, 'uploads', $filenames, true);

echo "   - Total files: " . $result->totalFiles . "\n";
echo "   - Successful: " . $result->successfulCount . "\n";
echo "   - Errors: " . count($result->errors) . "\n";

if ($result->hasErrors()) {
    foreach ($result->errors as $error) {
        echo "   - Error for {$error->filename}: {$error->message}\n";
    }
}
echo "\n";

// Example 9: Collision resolution strategies
echo "9. Collision Resolution Strategies:\n";

$strategies = ['increment', 'uuid', 'timestamp'];

foreach ($strategies as $strategy) {
    $testService = new FileUploadService(
        allowedFileTypes: ['image', 'video'],
        collisionStrategy: $strategy
    );

    $strategyEnum = CollisionStrategyEnum::from($strategy);
    echo "   - {$strategy}: {$strategyEnum->getDescription()}\n";
}
echo "\n";

// Example 10: HEIC conversion
echo "10. HEIC Conversion:\n";
$serviceWithHeic = new FileUploadService(
    allowedFileTypes: ['image'],
    convertHeicToJpg: true
);

echo "   - HEIC conversion enabled: " . ($serviceWithHeic->isHeicConversionEnabled() ? 'Yes' : 'No') . "\n";

$serviceWithoutHeic = new FileUploadService(
    allowedFileTypes: ['image'],
    convertHeicToJpg: false
);

echo "   - HEIC conversion disabled: " . ($serviceWithoutHeic->isHeicConversionEnabled() ? 'Yes' : 'No') . "\n\n";

// Example 11: Error handling and rollback
echo "11. Error Handling and Rollback:\n";
$rollbackService = new FileUploadService(
    allowedFileTypes: ['image', 'video'],
    rollbackOnError: true
);

echo "   - Rollback enabled: " . ($rollbackService->isRollbackOnErrorEnabled() ? 'Yes' : 'No') . "\n";

// Test with mixed valid/invalid files
$mixedInputs = [
    $imageDataUri,  // Valid
    'invalid-data'  // Invalid
];
$mixedFilenames = ['valid.jpg', 'invalid.txt'];

$result = $rollbackService->save($mixedInputs, 'uploads', $mixedFilenames, true);

echo "   - With rollback: " . $result->successfulCount . " files saved\n";
echo "   - Errors: " . count($result->errors) . "\n\n";

// Example 12: Enum usage (updated with video)
echo "12. Enum Usage (Updated with Video):\n";
echo "   - FileTypeEnum::IMAGE->getLabel(): " . FileTypeEnum::IMAGE->getLabel() . "\n";
echo "   - FileTypeEnum::VIDEO->getLabel(): " . FileTypeEnum::VIDEO->getLabel() . "\n";
echo "   - CollisionStrategyEnum::UUID->getDescription(): " . CollisionStrategyEnum::UUID->getDescription() . "\n";
$availableTypes = FileUploadService::getAvailableFileTypeCategories();
$typeNames = array_map(fn($type) => $type->value, $availableTypes);
echo "   - Available file types: " . implode(', ', $typeNames) . "\n\n";

// Example 13: Utility methods
echo "13. Utility Methods:\n";
echo "   - Clean filename: " . FileUploadService::cleanFilename('My File (1).jpg') . "\n";
echo "   - Clean filename (no underscores): " . FileUploadService::cleanFilename('My File (1).jpg', true) . "\n";

$extension = 'mp4';
$category = $service->getFileTypeCategoryFromExtension($extension);
$categoryString = is_object($category) ? $category->value : $category;
echo "   - File type category for '$extension': " . $categoryString . "\n";
echo "   - '$extension' allowed: " . ($service->isFileTypeAllowedByExtension($extension) ? 'Yes' : 'No') . "\n\n";

// Example 14: Security features demonstration
echo "14. Security Features:\n";
echo "   - Path traversal protection: Enabled\n";
echo "   - Filename sanitization: Enabled\n";
echo "   - MIME type validation: Enabled\n";
echo "   - Atomic file operations: Enabled\n";
echo "   - Double extension protection: Enabled\n\n";

// Example 15: Storage backend flexibility
echo "15. Storage Backend Flexibility:\n";
echo "   - FilesystemSaver: Local filesystem storage\n";
echo "   - CloudStorageSaver: Example cloud storage implementation\n";
echo "   - Custom backends: Implement FileSaverInterface\n";
echo "   - Upload destination validation: Delegated to backend\n\n";

echo "Examples completed successfully!\n";
echo "===============================\n";
