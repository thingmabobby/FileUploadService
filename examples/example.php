<?php

require_once 'vendor/autoload.php';

use FileUploadService\FileUploadService;
use FileUploadService\FilesystemSaver;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\CollisionStrategyEnum;

echo "File Upload Service - Complete Usage Examples\n";
echo "=============================================\n\n";

// Example 1: Basic configuration with raw strings
echo "1. Basic Configuration - Raw Strings (Recommended):\n";
$service = new FileUploadService([
    'image',
    'pdf',
    'cad'
]);

echo "   - File types: image, pdf, cad\n";
echo "   - HEIC conversion: enabled (default)\n";
echo "   - Rollback on error: disabled (default)\n";
echo "   - Collision strategy: increment (default)\n\n";

// Example 2: Advanced configuration with all options
echo "2. Advanced Configuration:\n";
$filesystemSaver = new FilesystemSaver('/tmp/uploads', 0755, true);
$service = new FileUploadService(
    allowedFileTypes: ['image', 'pdf', 'doc', 'archive'],
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
echo "   - HEIC conversion: enabled\n\n";

// Example 3: High-performance mode
echo "3. High-Performance Mode:\n";
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    highPerformanceMode: true
);

echo "   - Automatically uses UUID collision strategy\n";
echo "   - Optimized for network storage\n";
echo "   - Reduces filesystem calls\n\n";

// Example 4: File type restrictions
echo "4. File Type Management:\n";
$service = new FileUploadService(['image', 'pdf']);

// Add more file types
$service->allowFileType(['doc', 'docx', 'txt']);
echo "   - Added: doc, docx, txt\n";

// Remove specific types
$service->disallowFileType('pdf');
echo "   - Removed: pdf\n";

// Check if type is allowed
echo "   - Image allowed: " . ($service->isFileTypeCategoryAllowed('image') ? 'Yes' : 'No') . "\n";
echo "   - PDF allowed: " . ($service->isFileTypeCategoryAllowed('pdf') ? 'Yes' : 'No') . "\n";
echo "   - Current restrictions: " . $service->getRestrictionDescription() . "\n\n";

// Example 5: Base64 data URI upload
echo "5. Base64 Data URI Upload:\n";
$imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

$result = $service->save(
    [$imageDataUri],
    'uploads',
    ['image1.jpg'],
    true // Overwrite existing files
);

if ($result->hasSuccessfulUploads()) {
    echo "   - Upload successful: " . $result->successfulFiles[0] . "\n";
    echo "   - Files uploaded: " . $result->successfulCount . "/" . $result->totalFiles . "\n";
} else {
    echo "   - Upload failed\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "   - Error: $error\n";
    }
}
echo "\n";

// Example 6: $_FILES array upload
echo "6. \$_FILES Array Upload:\n";
// Simulate a $_FILES array
$files = [
    'name' => 'document.pdf',
    'type' => 'application/pdf',
    'tmp_name' => '/tmp/uploaded_file',
    'error' => 0,
    'size' => 12345
];

// Create a temporary file for demonstration
$tempFile = tempnam(sys_get_temp_dir(), 'demo_');
file_put_contents($tempFile, 'Sample PDF content');
$files['tmp_name'] = $tempFile;

$result = $service->save(
    [$files],
    'uploads',
    ['document.pdf'],
    true // Overwrite existing files
);

if ($result->hasSuccessfulUploads()) {
    echo "   - Upload successful: " . $result->successfulFiles[0] . "\n";
} else {
    echo "   - Upload failed\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "   - Error: $error\n";
    }
}

// Clean up
if (file_exists($tempFile)) {
    unlink($tempFile);
}
echo "\n";

// Example 7: Multiple file upload
echo "7. Multiple File Upload:\n";
$multipleInputs = [
    $imageDataUri,  // Base64
    $files          // $_FILES array
];
$filenames = ['image2.jpg', 'document2.pdf'];

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

// Example 8: Collision resolution strategies
echo "8. Collision Resolution Strategies:\n";

$strategies = ['increment', 'uuid', 'timestamp'];

foreach ($strategies as $strategy) {
    $testService = new FileUploadService(
        allowedFileTypes: ['image'],
        collisionStrategy: $strategy
    );

    $strategyEnum = CollisionStrategyEnum::from($strategy);
    echo "   - {$strategy}: {$strategyEnum->getDescription()}\n";
}
echo "\n";

// Example 9: HEIC conversion
echo "9. HEIC Conversion:\n";
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

// Example 10: Error handling and rollback
echo "10. Error Handling and Rollback:\n";
$rollbackService = new FileUploadService(
    allowedFileTypes: ['image'],
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

// Example 11: Enum usage (optional)
echo "11. Enum Usage (Optional):\n";
echo "   - FileTypeEnum::IMAGE->getLabel(): " . FileTypeEnum::IMAGE->getLabel() . "\n";
echo "   - CollisionStrategyEnum::UUID->getDescription(): " . CollisionStrategyEnum::UUID->getDescription() . "\n";
$availableTypes = FileUploadService::getAvailableFileTypeCategories();
$typeNames = array_map(fn($type) => $type->value, $availableTypes);
echo "   - Available file types: " . implode(', ', $typeNames) . "\n\n";

// Example 12: Utility methods
echo "12. Utility Methods:\n";
echo "   - Clean filename: " . FileUploadService::cleanFilename('My File (1).jpg') . "\n";
echo "   - Clean filename (no underscores): " . FileUploadService::cleanFilename('My File (1).jpg', true) . "\n";

$extension = 'jpg';
$category = $service->getFileTypeCategoryFromExtension($extension);
$categoryString = is_object($category) ? $category->value : $category;
echo "   - File type category for '$extension': " . $categoryString . "\n";
echo "   - '$extension' allowed: " . ($service->isFileTypeAllowedByExtension($extension) ? 'Yes' : 'No') . "\n\n";

echo "Examples completed successfully!\n";
echo "================================\n";
