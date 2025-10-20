# FileUploadService

A comprehensive PHP service for handling file uploads from base64 encoded data URIs or traditional file uploads ($_FILES array). Supports images, PDFs, CAD drawings, videos, and other common file types with pluggable storage backends, advanced security features, and flexible validation options.

## Features

- **Multiple Input Types**: Handles both `$_FILES` arrays and base64 data URIs seamlessly
- **Comprehensive File Type Support**: Images, PDFs, CAD files, documents, archives, and videos
- **Custom MIME Type Support**: Allow specific MIME types and custom extensions beyond predefined categories
- **Advanced Security**: Path traversal protection, filename sanitization, MIME type validation, atomic file operations
- **HEIC/HEIF Conversion**: Automatically converts HEIC/HEIF files to JPEG with graceful degradation
- **Collision Resolution**: Multiple strategies for handling filename conflicts (increment, UUID, timestamp, custom)
- **Error Handling**: Comprehensive error tracking with rollback support
- **High Performance Mode**: Optimized for network storage and high-collision scenarios
- **Pluggable Storage**: Extensible storage backends via FileSaverInterface (filesystem, cloud, custom)
- **Type Safety**: Full enum-based type system for file types and strategies
- **Separate DTOs**: Specialized data transfer objects for different input types

## Requirements

- PHP 8.1 or higher
- `maestroerror/php-heic-to-jpg` for HEIC/HEIF conversion

## Installation

```bash
composer require thingmabobby/file-upload-service
```

## Basic Usage

```php
use FileUploadService\FileUploadService;

// Define allowed file types
$allowedFileTypes = ['image', 'pdf', 'video'];

// Define input data (can be $_FILES array or base64 data URIs)
$input = $_FILES['files']; // or ['data:image/jpeg;base64,/9j/4AAQ...', 'data:application/pdf;base64,JVBERi0x...']

// Define upload destination (directory, bucket/key prefix, etc.)
$uploadDestination = 'uploads';

// Define filenames for each input
$filenames = ['photo1.jpg', 'document1.pdf'];

// Create service with simple string-based configuration
$fileUploadService = new FileUploadService($allowedFileTypes);

try {
    // Upload files
    $result = $fileUploadService->save($input, $uploadDestination, $filenames);

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
} catch (RuntimeException $e) {
    // Handle critical errors (directory creation, filename mismatches, etc.)
    echo "Critical error: " . $e->getMessage() . "\n";
}
```

## Custom MIME Type Support

The service now supports custom MIME types and extensions beyond the predefined categories:

### Using Constructor with MIME Types

```php
// Allow specific MIME types using 'mime:' prefix
$service = new FileUploadService([
    'image',                    // All image types
    'mime:text/csv',           // Custom CSV MIME type
    'mime:application/x-custom', // Custom application MIME type
    '.xyz'                     // Custom extension
]);
```

### Using Setter Methods

```php
$service = new FileUploadService(['image']);

// Set allowed categories
$service->setAllowedCategories(['image', 'video']);

// Set custom extensions
$service->setAllowedExtensions(['xyz', 'custom']);

// Set custom MIME types
$service->setAllowedMimeTypes(['text/csv', 'application/x-custom']);

// Get current settings
$categories = $service->getAllowedCategories(); // Returns array<FileTypeEnum>
$extensions = $service->getAllowedExtensions(); // Returns array<string>
$mimeTypes = $service->getAllowedMimeTypes(); // Returns array<string>
```

### Mixed Configuration Example

```php
$service = new FileUploadService([
    'image',                    // Image category
    'video',                    // Video category  
    'mime:text/csv',           // Custom CSV MIME type
    'mime:application/x-php',   // Custom PHP MIME type
    '.xyz',                    // Custom extension
    '.custom'                  // Another custom extension
]);

// Additional configuration via setters
$service->setAllowedMimeTypes(['text/plain', 'application/json']);
$service->setAllowedExtensions(['txt', 'json']);
```

## Video File Support

The service now includes comprehensive video file support:

```php
// Allow video files
$service = new FileUploadService(['image', 'video']);

// Supported video formats:
// MP4, AVI, MOV, WMV, FLV, WebM, MKV, MPEG, MPG, 3GP, M4V, OGV
```

## Exception Handling

The `FileUploadService` uses a **two-tier exception handling strategy**:

### **Critical Errors (Thrown Exceptions)**
These errors prevent the entire operation from proceeding and should be caught with try-catch:

- **Directory Issues**: Upload destination doesn't exist and can't be created
- **Permission Issues**: Upload destination is not writable  
- **Parameter Mismatches**: Filename count doesn't match input count
- **Configuration Errors**: Invalid file types provided

### **Individual File Errors (Captured in Result)**
These errors affect individual files but allow the operation to continue:

- **File Validation Failures**: Invalid file types, corrupted data
- **Upload Errors**: PHP upload errors (file too large, partial upload, etc.)
- **Processing Errors**: HEIC conversion failures, file saving issues

### **Best Practice**
Always wrap the `save()` method in a try-catch block to handle critical errors, then check the result for individual file errors:

```php
try {
    $result = $fileUploadService->save($input, $uploadDestination, $filenames);
    
    // Handle individual file results
    if ($result->hasSuccessfulUploads()) {
        // Process successful uploads
    }
    
    if ($result->hasErrors()) {
        // Handle individual file errors
    }
} catch (RuntimeException $e) {
    // Handle critical system errors
    error_log("File upload failed: " . $e->getMessage());
}
```

## Configuration

### File Type Restrictions

```php
// Simple usage with raw strings (recommended)
$service = new FileUploadService([
    'image',
    'pdf',
    'video',
    'cad'
]);

// Allow all file types
$service = new FileUploadService(['all']);

// Mix categories, custom extensions, and MIME types
$service = new FileUploadService([
    'image',
    'video',
    'mime:text/csv',
    'mime:application/x-custom',
    '.xyz',
    '.custom'
]);

// Advanced usage with enums (optional)
use FileUploadService\Enum\FileTypeEnum;

$service = new FileUploadService([
    FileTypeEnum::IMAGE,
    FileTypeEnum::VIDEO,
    FileTypeEnum::PDF
]);

// Get all available file types - returns array of FileTypeEnum cases
$allFileTypes = FileUploadService::getAvailableFileTypeCategories();
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
- **Default behavior**: HEIC/HEIF files are automatically detected via MIME type and converted to JPEG format
- **Detection method**: Uses MIME type from uploaded file (primary), finfo() detection (fallback), and binary header checks (last resort)
- **Extension-agnostic**: Files are detected by content, not file extension, preventing misidentification
- **Conversion fails**: Falls back to saving the original HEIC/HEIF file
- **Always graceful**: Never fails uploads due to conversion issues
- **Library dependency**: Uses `maestroerror/php-heic-to-jpg` package (required dependency)

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

// Default filesystem storage (uses system temp directory)
$service = new FileUploadService(['image']);

// Custom filesystem storage with specific directory
use FileUploadService\FilesystemSaver;
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
$result = $fileUploadService->save($input, $uploadDestination, $filenames);
```

## Supported File Types

The service supports a comprehensive range of file types through the `SupportedFileTypesEnum`. Here are all supported file types organized by category:

### Images
| Extension | MIME Type | Description |
|-----------|-----------|-------------|
| `jpg` | `image/jpeg` | JPEG images |
| `png` | `image/png` | PNG images |
| `gif` | `image/gif` | GIF images |
| `webp` | `image/webp` | WebP images |
| `avif` | `image/avif` | AVIF images |
| `jxl` | `image/jxl` | JPEG XL images |
| `bmp` | `image/bmp` | BMP images |
| `tiff` | `image/tiff` | TIFF images |
| `heic` | `image/heic` | HEIC images (converted to JPEG) |
| `heif` | `image/heif` | HEIF images (converted to JPEG) |

### Videos
| Extension | MIME Type | Description |
|-----------|-----------|-------------|
| `mp4` | `video/mp4` | MP4 videos |
| `avi` | `video/x-msvideo` | AVI videos |
| `mov` | `video/quicktime` | QuickTime videos |
| `wmv` | `video/x-ms-wmv` | Windows Media videos |
| `flv` | `video/x-flv` | Flash videos |
| `webm` | `video/webm` | WebM videos |
| `mkv` | `video/x-matroska` | Matroska videos |
| `mpeg` | `video/mpeg` | MPEG videos |
| `mpg` | `video/mpeg` | MPEG videos |
| `3gp` | `video/3gpp` | 3GPP videos |
| `m4v` | `video/x-m4v` | iTunes videos |
| `ogv` | `video/ogg` | Ogg videos |

### PDF Documents
| Extension | MIME Type | Description |
|-----------|-----------|-------------|
| `pdf` | `application/pdf` | Standard PDF documents |
| `pdf` | `application/x-pdf` | Alternative PDF MIME type |
| `pdf` | `application/acrobat` | Adobe Acrobat PDF |
| `pdf` | `application/vnd.pdf` | Vendor-specific PDF |

### Documents
| Extension | MIME Type | Description |
|-----------|-----------|-------------|
| `doc` | `application/msword` | Microsoft Word documents |
| `docx` | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | Microsoft Word XML documents |
| `xls` | `application/vnd.ms-excel` | Microsoft Excel spreadsheets |
| `xlsx` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | Microsoft Excel XML spreadsheets |
| `ppt` | `application/vnd.ms-powerpoint` | Microsoft PowerPoint presentations |
| `pptx` | `application/vnd.openxmlformats-officedocument.presentationml.presentation` | Microsoft PowerPoint XML presentations |
| `txt` | `text/plain` | Plain text files |
| `rtf` | `application/rtf` | Rich Text Format documents |
| `csv` | `text/csv` | Comma-separated values |
| `xml` | `application/xml` | XML documents |
| `json` | `application/json` | JSON documents |
| `odt` | `application/vnd.oasis.opendocument.text` | OpenDocument text |
| `ods` | `application/vnd.oasis.opendocument.spreadsheet` | OpenDocument spreadsheet |
| `odp` | `application/vnd.oasis.opendocument.presentation` | OpenDocument presentation |

### CAD Files
| Extension | MIME Type | Description |
|-----------|-----------|-------------|
| `dwg` | `application/dwg` | AutoCAD drawings |
| `dxf` | `application/dxf` | AutoCAD DXF files |
| `step` | `application/step` | STEP 3D models |
| `iges` | `application/iges` | IGES 3D models |
| `stl` | `application/stl` | STL 3D models |
| `sldprt` | `application/sldprt` | SolidWorks part files |
| `sldasm` | `application/sldasm` | SolidWorks assembly files |

### Archives
| Extension | MIME Type | Description |
|-----------|-----------|-------------|
| `zip` | `application/zip` | ZIP archives |
| `rar` | `application/x-rar-compressed` | RAR archives |
| `7z` | `application/x-7z-compressed` | 7-Zip archives |
| `tar` | `application/x-tar` | TAR archives |
| `gz` | `application/gzip` | GZIP compressed files |

### File Type Categories

The service organizes file types into the following categories (accessible via `FileTypeEnum`):

- **`image`** - All image formats (JPEG, PNG, GIF, WebP, AVIF, JXL, BMP, TIFF, HEIC, HEIF)
- **`video`** - All video formats (MP4, AVI, MOV, WMV, FLV, WebM, MKV, MPEG, 3GP, M4V, OGV)
- **`pdf`** - PDF documents (all MIME variants)
- **`doc`** - All document formats (Office, OpenDocument, text files)
- **`cad`** - CAD and technical drawing files
- **`archive`** - Compressed archive formats
- **`all`** - Allow all file types (no restrictions)

**Note:** For programmatic access to this information, use the `SupportedFileTypesEnum` class which provides methods to get extensions, MIME types, and categories for each supported file type.

## Security Features

### Path Traversal Protection
- `FilesystemSaver::resolvePath()` prevents `../` attacks
- All paths are validated to stay within `basePath`
- Absolute paths are rejected for security

### Filename Sanitization
- `FilenameSanitizer::cleanFilename()` removes dangerous characters
- Null byte removal prevents security bypasses
- Unicode normalization prevents confusion attacks
- Length limits prevent filesystem issues

### MIME Type Validation
- `FileServiceValidator::isFileTypeAllowed()` validates actual file content
- Uses `finfo_open()` to detect real MIME types
- Prevents file type spoofing attacks
- Custom MIME types are validated against actual file content

### Atomic File Operations
- All file saves use temporary files + `rename()` for atomicity
- Prevents race conditions and partial file writes
- Secure temporary directory creation with restricted permissions

### Double Extension Protection
- Files like `malware.php.txt` are treated as `.txt` files (safe)
- Files like `malware.txt.php` are blocked by MIME validation
- MIME type detection prevents execution of disguised files

## API Reference

### FileUploadService

#### Constructor
```php
public function __construct(
    private readonly array $allowedFileTypes = [FileTypeEnum::IMAGE, FileTypeEnum::PDF, FileTypeEnum::CAD],
    private readonly ?FileSaverInterface $fileSaver = null,
    private readonly bool $createDirectory = true,
    private readonly int $directoryPermissions = 0775,
    private readonly bool $rollbackOnError = false,
    string|callable|CollisionStrategyEnum $collisionStrategy = CollisionStrategyEnum::INCREMENT,
    private readonly bool $highPerformanceMode = false,
    private readonly bool $convertHeicToJpg = true
)
```

#### Public Methods

**File Management:**
- `save(array $inputs, string $uploadDestination, array $filenames, bool $overwriteExisting = false, bool $generateUniqueFilenames = false): FileUploadResult`

**Configuration:**
- `setAllowedFileTypes(array $allowedFileTypes): void`
- `getAllowedFileTypes(): array<FileTypeEnum|string>`

- `setAllowedCategories(array $categories): void`
- `getAllowedCategories(): array<FileTypeEnum>`

- `setAllowedExtensions(array $extensions): void`
- `getAllowedExtensions(): array<string>`

- `setAllowedMimeTypes(array $mimeTypes): void`
- `getAllowedMimeTypes(): array<string>`

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
- `public readonly string $filename` - The filename that caused the error
- `public readonly string $message` - The error message
- `public readonly string $code` - The error code (optional, defaults to empty string)

**Methods:**
- `getDescription(): string`

## Architecture

The service is built with a clean separation of concerns:

- **FileUploadService**: Main orchestrator and public API
- **FileServiceValidator**: Handles file validation and type checking
- **FileCollisionResolver**: Manages filename collision resolution
- **FileUploadSave**: Handles actual file processing and saving operations
- **FileSaverInterface**: Pluggable storage backend interface
- **FilesystemSaver**: Default filesystem storage implementation
- **CloudStorageSaver**: Example cloud storage implementation
- **Enum Classes**: Type-safe constants and enumerations (FileTypeEnum, CollisionStrategyEnum, UploadErrorCodeEnum, SupportedFileTypesEnum)
- **DTO Classes**: Specialized data transfer objects (FileUploadDTO, DataUriDTO)
- **Utils**: Utility classes (FilenameSanitizer)

### Storage Backend Interface

The service uses the `FileSaverInterface` to abstract file storage operations, allowing for different storage backends:

- **FilesystemSaver**: Local filesystem storage (included)
- **Cloud Storage**: AWS S3, Google Cloud Storage, Azure Blob Storage (implementations can be added)
- **Custom Storage**: Any storage system can be implemented by implementing `FileSaverInterface`

The interface provides methods for:
- `saveFile(string $source, string $targetPath, bool $overwriteExisting = false): string`
- `resolveTargetPath(string $uploadDestination, string $filename): string`
- `ensureUploadDestinationExists(string $uploadDestination): void`
- `fileExists(string $targetPath): bool`
- `deleteFile(string $targetPath): bool`
- `getBasePath(): string`

## Testing

The package includes comprehensive test coverage with PHPUnit:

```bash
# Run all tests
composer test

# Run tests with HTML coverage report
composer test-coverage

# Run tests with text coverage report
composer test-coverage-text

# Run tests with verbose output
composer test-verbose

# Run tests and stop on first failure
composer test-stop-on-failure
```

## License

Unlicense - See LICENSE file for details.