<?php

require_once 'vendor/autoload.php';

use FileUploadService\FileUploadService;
use FileUploadService\Enum\FileTypeEnum;

echo "FileUploadService - Custom MIME Types & Video Support Example\n";
echo "==============================================================\n\n";

// 1. Basic video support
echo "1. Video File Support:\n";
$service = new FileUploadService(['image', 'video']);
echo "✓ Service created with image and video support\n";

// 2. Custom MIME types in constructor
echo "\n2. Custom MIME Types in Constructor:\n";
$service = new FileUploadService([
    'image',                    // Image category
    'video',                    // Video category
    'mime:text/csv',           // Custom CSV MIME type
    'mime:application/x-custom', // Custom application MIME type
    '.xyz'                     // Custom extension
]);
echo "✓ Service created with custom MIME types and extensions\n";

// 3. Using setter methods for custom configuration
echo "\n3. Using Setter Methods:\n";
$service = new FileUploadService(['image']);

// Add video support
$service->setAllowedCategories(['image', 'video']);
echo "✓ Added video category\n";

// Add custom extensions
$service->setAllowedExtensions(['xyz', 'custom', 'txt']);
echo "✓ Added custom extensions: xyz, custom, txt\n";

// Add custom MIME types
$service->setAllowedMimeTypes(['text/csv', 'application/x-custom', 'text/plain']);
echo "✓ Added custom MIME types: text/csv, application/x-custom, text/plain\n";

// 4. Display current configuration
echo "\n4. Current Configuration:\n";
$categories = $service->getAllowedCategories();
$extensions = $service->getAllowedExtensions();
$mimeTypes = $service->getAllowedMimeTypes();

$categoryValues = array_map(fn($cat) => is_object($cat) ? $cat->value : $cat, $categories);
echo "   Categories: " . implode(', ', $categoryValues) . "\n";
echo "   Extensions: " . implode(', ', $extensions) . "\n";
echo "   MIME Types: " . implode(', ', $mimeTypes) . "\n";

// 5. Test with mixed input types
echo "\n5. Testing Mixed Input Types:\n";

// Create test data URIs
$imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';
$csvDataUri = 'data:text/csv;base64,LG5hbWUsZW1haWwsYWdlCkpvaG4sam9obkBleGFtcGxlLmNvbSwyNQpKYW5lLGphbmVAZXhhbXBsZS5jb20sMzA=';

$result = $service->save(
    [$imageDataUri, $csvDataUri],
    'uploads',
    ['image.jpg', 'data.csv'],
    true // Overwrite existing files
);

if ($result->hasSuccessfulUploads()) {
    echo "✓ Mixed upload successful: " . $result->successfulCount . "/" . $result->totalFiles . " files\n";
    foreach ($result->successfulFiles as $filePath) {
        echo "  - Saved: " . $filePath . "\n";
    }
} else {
    echo "✗ Upload failed\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "  - Error: $error\n";
    }
}

// 6. Test custom extension validation
echo "\n6. Testing Custom Extension Validation:\n";
$customService = new FileUploadService(['.xyz', '.custom']);

// Test if custom extensions are allowed
echo "   .xyz allowed: " . ($customService->isFileTypeAllowedByExtension('xyz') ? 'Yes' : 'No') . "\n";
echo "   .custom allowed: " . ($customService->isFileTypeAllowedByExtension('custom') ? 'Yes' : 'No') . "\n";
echo "   .jpg allowed: " . ($customService->isFileTypeAllowedByExtension('jpg') ? 'Yes' : 'No') . "\n";

// 7. Test MIME type validation
echo "\n7. Testing MIME Type Validation:\n";
$mimeService = new FileUploadService(['mime:text/csv', 'mime:application/x-custom']);

echo "   text/csv MIME allowed: " . ($mimeService->isFileTypeAllowedByExtension('csv') ? 'Yes' : 'No') . "\n";
echo "   application/x-custom MIME allowed: " . ($mimeService->isFileTypeAllowedByExtension('custom') ? 'Yes' : 'No') . "\n";

// 8. Video file example (simulated)
echo "\n8. Video File Support Example:\n";
$videoService = new FileUploadService(['video']);

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

$result = $videoService->save(
    [$videoFiles],
    'uploads',
    ['sample.mp4'],
    true
);

if ($result->hasSuccessfulUploads()) {
    echo "✓ Video upload successful: " . $result->successfulFiles[0] . "\n";
} else {
    echo "✗ Video upload failed\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "  - Error: $error\n";
    }
}

// Clean up
if (file_exists($videoFiles['tmp_name'])) {
    unlink($videoFiles['tmp_name']);
}

// 9. Show all available file type categories
echo "\n9. Available File Type Categories:\n";
$availableTypes = FileUploadService::getAvailableFileTypeCategories();
foreach ($availableTypes as $type) {
    echo "   - " . $type->value . " (" . $type->getLabel() . ")\n";
}

// 10. Complex configuration example
echo "\n10. Complex Configuration Example:\n";
$complexService = new FileUploadService([
    'image',                    // All images
    'video',                    // All videos
    'mime:text/csv',           // CSV files
    'mime:application/json',   // JSON files
    'mime:text/plain',        // Plain text files
    '.xyz',                    // Custom extension
    '.custom'                  // Another custom extension
]);

// Additional configuration
$complexService->setAllowedMimeTypes(['application/xml', 'text/html']);
$complexService->setAllowedExtensions(['xml', 'html']);

echo "✓ Complex service configured with:\n";
echo "   - Image and video categories\n";
echo "   - Custom MIME types: CSV, JSON, plain text, XML, HTML\n";
echo "   - Custom extensions: xyz, custom, xml, html\n";

echo "\nDone! Check the 'uploads' directory for your files.\n";
echo "==================================================\n";
