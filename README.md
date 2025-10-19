# FileUploadService

A comprehensive PHP service for handling file uploads from base64 encoded data URIs or traditional file uploads ($_FILES array). Supports images, PDFs, CAD drawings, and other common file types with pluggable storage backends.

## Features

- **Multiple Input Types**: Handles both `$_FILES` arrays and base64 data URIs
- **File Type Validation**: Supports images, PDFs, CAD files, documents, and archives
- **HEIC/HEIF Conversion**: Automatically converts HEIC/HEIF files to JPEG with graceful degradation
- **Collision Resolution**: Multiple strategies for handling filename conflicts (increment, UUID, timestamp, custom)
- **Error Handling**: Comprehensive error tracking with rollback support
- **High Performance Mode**: Optimized for network storage and high-collision scenarios
- **Pluggable Storage**: Extensible storage backends via FileSaverInterface
- **Type Safety**: Full enum-based type system for file types and strategies

## Requirements

- PHP 8.1 or higher
- `maestroerror/heic-to-jpg` for HEIC/HEIF conversion

## Installation

```bash
composer require thingmabobby/file-upload-service
```

## Basic Usage

```php
use FileUploadService\FileUploadService;
use FileUploadService\FilesystemSaver;
use FileUploadService\Enum\FileTypeEnum;

// Create service with default filesystem storage
$fileUploadService = new FileUploadService([
    FileTypeEnum::IMAGE->value,
    FileTypeEnum::PDF->value
]);

// Upload files
$result = $fileUploadService->save($input, $uploadDir, $filenames);

// Check results
if ($result->hasSuccessfulUploads()) {
    echo "Uploaded " . $result->successfulCount . " files";
    foreach ($result->successfulFiles as $filePath) {
        echo "Saved: " . $filePath . "\n";
    }
} else {
    foreach ($result->getErrorMessages() as $errorMessage) {
        echo "Error: " . $errorMessage . "\n";
    }
}
```

## Configuration

### File Type Restrictions

```php
// Simple usage with raw strings (recommended)
$service = new FileUploadService([
    'image',
    'pdf',
    'cad'
]);

// Allow all file types
$service = new FileUploadService(['all']);

// Mix categories and specific extensions
$service = new FileUploadService([
    'image',
    'doc', 'docx', 'xls', 'custom_ext'
]);

// Advanced usage with enums (optional)
use FileUploadService\Enum\FileTypeEnum;

$service = new FileUploadService([
    FileTypeEnum::IMAGE,
    FileTypeEnum::PDF
]);

// Get all available file types
$allFileTypes = FileUploadService::getAvailableFileTypeCategories();
// Returns array of FileTypeEnum cases
```

### HEIC/HEIF Conversion

The service automatically attempts to convert HEIC/HEIF files to JPEG format. If the conversion library is not available, it gracefully degrades by saving the original HEIC/HEIF file as-is.

```php
// Enable HEIC to JPEG conversion (default)
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    convertHeicToJpg: true
);

// Disable HEIC conversion (save as-is)
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    convertHeicToJpg: false
);

// Check if HEIC conversion is available
if ($service->isHeicConversionEnabled()) {
    echo "HEIC conversion is enabled";
}
```

**HEIC Conversion Behavior:**
- **Default behavior**: HEIC/HEIF files are automatically converted to JPEG format
- **Conversion fails**: Falls back to saving the original HEIC/HEIF file
- **Always graceful**: Never fails uploads due to conversion issues
- **Library dependency**: Requires `maestroerror/heic-to-jpg` package

### Collision Resolution Strategies

```php
// Increment strategy (default): filename_1.jpg, filename_2.jpg
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    collisionStrategy: 'increment'
);

// UUID strategy: filename_a1b2c3d4.jpg
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    collisionStrategy: 'uuid'
);

// Timestamp strategy: filename_1234567890.jpg
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    collisionStrategy: 'timestamp'
);

// Advanced usage with enums (optional)
use FileUploadService\Enum\CollisionStrategyEnum;

$service = new FileUploadService(
    allowedFileTypes: ['image'],
    collisionStrategy: CollisionStrategyEnum::UUID
);

// Custom strategy
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    collisionStrategy: fn($base, $dir, $exts, $used) => $base . '_custom_' . bin2hex(random_bytes(4))
);
```

### High Performance Mode

```php
// Optimized for network storage or high-collision scenarios
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    highPerformanceMode: true
);
// Automatically uses 'uuid' strategy and filters extensions to reduce filesystem calls
```

### Error Handling and Rollback

```php
// Enable rollback on error (default: false)
// When enabled, removes all successfully uploaded files if any error occurs
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    rollbackOnError: true
);

// Default behavior (rollback disabled)
$service = new FileUploadService(['image']); // rollbackOnError defaults to false

// Check if rollback is enabled
if ($service->isRollbackOnErrorEnabled()) {
    echo "Rollback is enabled";
}
```

### Storage Backends

```php
use FileUploadService\FileUploadService;
use FileUploadService\FilesystemSaver;

// Default filesystem storage (uses system temp directory)
$service = new FileUploadService(['image']);

// Custom filesystem storage with specific directory
$filesystemSaver = new FilesystemSaver('/var/uploads', 0755, true);
$service = new FileUploadService(
    allowedFileTypes: ['image'],
    fileSaver: $filesystemSaver
);

// Cloud storage example (implement FileSaverInterface)
// $cloudSaver = new CloudStorageSaver('bucket-name', 'region', 'credentials');
// $service = new FileUploadService(['image'], $cloudSaver);
```

## Input Types

### $_FILES Structure Handling

The service automatically detects and handles different `$_FILES` array structures:

**Single File Upload:**
```php
$_FILES['files'] = [
    'name' => 'image.jpg',           // String
    'type' => 'image/jpeg',          // String  
    'tmp_name' => '/tmp/abc123',     // String
    'error' => 0,                    // Integer
    'size' => 12345                  // Integer
];
```

**Multiple File Upload:**
```php
$_FILES['files'] = [
    'name' => ['image1.jpg', 'image2.png'],     // Array of strings
    'type' => ['image/jpeg', 'image/png'],      // Array of strings
    'tmp_name' => ['/tmp/abc123', '/tmp/def456'], // Array of strings
    'error' => [0, 0],                          // Array of integers
    'size' => [12345, 67890]                    // Array of integers
];
```

### Base64 Data URIs

```php
$dataUri = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...';
```

### Mixed Input Types

```php
$input = [
    'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...',  // base64
    $_FILES['uploaded_file'],                                // $_FILES
    'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9i...', // base64
    $_FILES['another_file']                                  // $_FILES
];
$filenames = ['image1.jpg', 'upload1.pdf', 'document.pdf', 'file2.doc'];
$result = $fileUploadService->save($input, $uploadDir, $filenames);
```

## Supported File Types

### Images
- JPEG, PNG, GIF, WebP, AVIF, JXL, BMP, TIFF, HEIC, HEIF

### Documents
- PDF (multiple MIME types supported)
- Microsoft Office: DOC, DOCX, XLS, XLSX, PPT, PPTX
- OpenDocument: ODT, ODS, ODP
- Text: TXT, RTF, CSV, XML, JSON

### CAD Files
- AutoCAD: DWG, DXF
- SolidWorks: SLDPRT, SLDASM
- Other: STEP, IGES, STL

### Archives
- ZIP, RAR, 7Z, TAR, GZ

## API Reference

### FileUploadService

#### Constructor
```php
public function __construct(
    array $allowedFileTypes = [FileTypeEnum::IMAGE, FileTypeEnum::PDF, FileTypeEnum::CAD],
    ?FileSaverInterface $fileSaver = null,
    bool $createDirectory = true,
    int $directoryPermissions = 0775,
    bool $rollbackOnError = false,
    string|callable|CollisionStrategyEnum $collisionStrategy = CollisionStrategyEnum::INCREMENT,
    bool $highPerformanceMode = false,
    bool $convertHeicToJpg = true
)
```

#### Public Methods

**File Management:**
- `save(array $inputs, string $uploadDir, array $filenames, bool $overwriteExisting = false, bool $generateUniqueFilenames = false): FileUploadResult`

**Configuration:**
- `setAllowedFileTypes(array $allowedFileTypes): void`
- `getAllowedFileTypes(): array`
- `allowFileType(string|array $fileTypes): void`
- `disallowFileType(string|array $fileTypes): void`

**Status Checks:**
- `isRollbackOnErrorEnabled(): bool`
- `isHeicConversionEnabled(): bool`
- `isFileTypeCategoryAllowed(string $fileType): bool`
- `isFileTypeAllowedByExtension(string $extension): bool`
- `isUnrestricted(): bool`

**Utilities:**
- `getFileTypeCategoryFromExtension(string $extension): FileTypeEnum|string`
- `getRestrictionDescription(): string`
- `cleanFilename(string $filename, bool $removeUnderscores = false): string`

#### Static Methods
- `getAvailableFileTypeCategories(): array`

### FileUploadResult

**Properties:**
- `public readonly array $successfulFiles` - Array of successfully uploaded file paths
- `public readonly array $errors` - Array of upload errors
- `public readonly int $totalFiles` - Total number of files attempted
- `public readonly int $successfulCount` - Number of successfully uploaded files

**Methods:**
- `hasErrors(): bool`
- `isCompleteSuccess(): bool`
- `hasSuccessfulUploads(): bool`
- `getErrorMessages(): array`
- `getErrorForFile(string $filename): ?FileUploadError`

### FileUploadError

**Properties:**
- `public readonly string $filename`
- `public readonly string $message`
- `public readonly string $code`

**Methods:**
- `getDescription(): string`

## Architecture

The service is built with a clean separation of concerns:

- **FileUploadService**: Main orchestrator and public API
- **FileServiceValidator**: Handles file validation and type checking
- **FileCollisionResolver**: Manages filename collision resolution
- **FileUploadSave**: Handles actual file saving operations
- **FileSaverInterface**: Pluggable storage backend interface
- **FilesystemSaver**: Default filesystem storage implementation
- **Enum Classes**: Type-safe constants and enumerations (FileTypeEnum, CollisionStrategyEnum, UploadErrorCodeEnum, SupportedFileTypesEnum)
- **DTO Classes**: Data transfer objects (FileDTO)

### Storage Backend Interface

The service uses the `FileSaverInterface` to abstract file storage operations, allowing for different storage backends:

- **FilesystemSaver**: Local filesystem storage (included)
- **Cloud Storage**: AWS S3, Google Cloud Storage, Azure Blob Storage (implementations can be added)
- **Custom Storage**: Any storage system can be implemented by implementing `FileSaverInterface`

The interface provides methods for:
- `saveFile(string $content, string $targetPath, bool $overwriteExisting = false): string`
- `moveUploadedFile(string $sourcePath, string $targetPath, bool $overwriteExisting = false): string`
- `fileExists(string $targetPath): bool`
- `deleteFile(string $targetPath): bool`
- `getBasePath(): string`

## Testing

The package includes comprehensive test coverage with PHPUnit:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run tests with verbose output
composer test-verbose

# Run tests and stop on first failure
composer test-stop-on-failure
```

## License

Unlicense - See LICENSE file for details.