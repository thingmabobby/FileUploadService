<?php

require_once 'vendor/autoload.php';

use FileUploadService\FileUploadService;

echo "FileUploadService - Basic Usage Example\n";
echo "======================================\n\n";

// 1. Simple setup with raw strings (recommended)
$service = new FileUploadService(['image', 'pdf']);

echo "✓ Service created with image and PDF support\n";

// 2. Upload a base64 image
$imageDataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

$result = $service->save(
    [$imageDataUri],
    'uploads',
    ['my-image.jpg'],
    true // Overwrite existing files
);

if ($result->hasSuccessfulUploads()) {
    echo "✓ Image uploaded successfully: " . $result->successfulFiles[0] . "\n";
    echo "  Files uploaded: " . $result->successfulCount . "/" . $result->totalFiles . "\n";
} else {
    echo "✗ Upload failed\n";
    foreach ($result->getErrorMessages() as $error) {
        echo "  Error: $error\n";
    }
}

echo "\nDone! Check the 'uploads' directory for your file.\n";
