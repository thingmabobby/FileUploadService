# FileUploadService Examples

This directory contains practical examples demonstrating how to use the FileUploadService library.

## Files

### `basic-usage.php`
A simple example showing the most common usage patterns:
- Basic service setup with raw strings
- Base64 image upload
- Error handling

**Run it:**
```bash
php examples/basic-usage.php
```

### `example.php`
A comprehensive example covering all major features:
- Configuration options
- File type management
- Base64 and $_FILES uploads
- Multiple file handling
- Collision resolution strategies
- HEIC conversion
- Error handling and rollback
- Enum usage
- Utility methods

**Run it:**
```bash
php examples/example.php
```

## Prerequisites

Make sure you have installed the FileUploadService dependencies:

```bash
composer install
```

## What You'll See

Both examples will:
1. Create upload directories
2. Demonstrate various configuration options
3. Upload sample files
4. Show error handling
5. Display results and file paths

## Key Features Demonstrated

- **Raw String Configuration**: No need to import enums for basic usage
- **Multiple Input Types**: Base64 data URIs and $_FILES arrays
- **File Type Management**: Adding/removing allowed file types
- **Collision Resolution**: Different strategies for handling duplicate filenames
- **HEIC Conversion**: Automatic HEIC/HEIF to JPEG conversion
- **Error Handling**: Comprehensive error reporting and rollback functionality
- **High Performance Mode**: Optimized settings for network storage

## Output

After running the examples, you'll find uploaded files in the `uploads/` directory. The examples use temporary files and directories that are cleaned up automatically.
