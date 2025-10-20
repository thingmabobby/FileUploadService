# FileUploadService Architecture & Flow Documentation

## Overview

The FileUploadService library provides a secure, type-safe way to handle file uploads from both traditional HTTP form uploads (`$_FILES`) and base64 data URIs. It supports comprehensive file type validation, HEIC conversion, collision resolution, and multiple storage backends.

## Architecture Components

### Core Classes

1. **`FileUploadService`** - Main service class that orchestrates the entire upload process
2. **`FileUploadSave`** - Handles the actual file processing and saving operations
3. **`FileServiceValidator`** - Validates file types, MIME types, and file integrity
4. **`FileCollisionResolver`** - Resolves filename conflicts using various strategies
5. **`FileSaverInterface`** - Interface for different storage implementations
6. **`FilesystemSaver`** - Local filesystem storage implementation
7. **`CloudStorageSaver`** - Example cloud storage implementation

### Data Transfer Objects (DTOs)

1. **`FileUploadDTO`** - Represents files uploaded via `$_FILES` (has `tmpPath`)
2. **`DataUriDTO`** - Represents files uploaded as base64 data URIs (has `dataUri`)

## Complete File Processing Flow

### 1. Service Initialization
```php
$service = new FileUploadService(
    allowedFileTypes: ['image', 'pdf'],
    convertHeicToJpg: true
);
```

**What happens:**
- `FileUploadService::__construct()` is called
- `FileServiceValidator` instance is created
- `FileCollisionResolver` is initialized with collision strategy
- Allowed file types are parsed and categorized
- `FileUploadSave` is NOT created yet (lazy-loaded in save() method)
- If `fileSaver` provided in constructor, `FileUploadSave` is created immediately (backward compatibility)

### 2. File Upload Request
```php
$result = $service->save(
    input: [$_FILES['file'], 'data:image/jpeg;base64,/9j/4AAQ...'],
    uploadDestination: '/var/www/documents',
    filenames: ['contract.pdf', 'signature.jpg']
);
```

**What happens:**
- `FileUploadService::save()` is called
- If `fileUploadSave` not initialized, creates `FilesystemSaver` from `uploadDestination`:
  - Absolute path: `basePath = dirname(uploadDestination)`
  - Relative path: `basePath = getcwd()`
- Creates `FileUploadSave` with the FileSaver
- Delegates to `FileUploadService::saveFromInput()`

### 3. Input Processing & Normalization
**Method:** `FileUploadService::saveFromInput()`

**What happens:**
- `FileSaverInterface::ensureUploadDestinationExists()` - Delegates to storage backend to ensure destination exists and is accessible
- Input arrays are processed and normalized:
  - Multi-file `$_FILES` arrays are expanded using `convertMultiFileToIndividualFiles()`
  - Single-file `$_FILES` arrays are converted using `convertSingleFileToIndividualFile()`
  - Data URIs remain as-is
- Filenames are optionally made unique using `FileCollisionResolver::generateUniqueFilenames()`

### 4. Individual File Processing Loop
**Method:** `FileUploadService::saveFromInput()` (foreach loop)

For each file input:

#### 4a. DTO Creation Based on Input Type
```php
if ($this->isFileUploadArray($input)) {
    $fileUploadDTO = FileUploadDTO::fromFilesArray($input, $filename);
    $result = $this->fileUploadSave->processFileUpload($fileUploadDTO, ...);
} else {
    $dataUriDTO = DataUriDTO::fromDataUri($input, $filename);
    $result = $this->fileUploadSave->processBase64Input($dataUriDTO, ...);
}
```

#### 4b. File Upload Processing
**Method:** `FileUploadSave::processFileUpload(FileUploadDTO $fileUploadDTO, ...)`

**What happens:**
1. **Upload Error Check** - Verify `$fileUploadDTO->isUploadSuccessful()`
2. **File Type Validation** - `FileServiceValidator::isFileTypeAllowed()` checks if file type is allowed
3. **File Integrity Validation** - `FileServiceValidator::validateUploadedFile()` checks file exists and is readable
4. **HEIC Conversion** - `FileUploadSave::handleHeicConversion()` converts HEIC/HEIF to JPG if needed
5. **Path Resolution** - `FileSaverInterface::resolveTargetPath()` determines final save location
6. **File Saving** - `FileSaverInterface::saveFile()` saves the file using the configured storage backend

#### 4c. Data URI Processing
**Method:** `FileUploadSave::processBase64Input(DataUriDTO $dataUriDTO, ...)`

**What happens:**
1. **Data URI Validation** - `FileServiceValidator::validateBase64DataUri()` validates format and base64 decode
2. **Temp File Creation** - `FileUploadSave::createTempFileFromDataUri()` creates temporary file from base64 data
3. **DTO Conversion** - Creates `FileUploadDTO` from the temp file
4. **Unified Processing** - Calls `FileUploadSave::processFileUpload()` with the converted DTO
5. **Cleanup** - Temp file is deleted in `finally` block

### 5. HEIC Conversion Process
**Method:** `FileUploadSave::handleHeicConversion()`

**What happens:**
- Detects HEIC/HEIF content using `isHeicContent()` with three-tier detection:
  1. **Primary**: Checks MIME type from DTO (from browser/client)
  2. **Fallback**: Uses finfo() to detect MIME type from file content
  3. **Last resort**: Reads binary header for HEIC brand markers (ftypheic, ftypheif, ftypmif1)
- If conversion is enabled and HEIC content is detected, calls `FileUploadSave::convertHeicToJpg()`
- Uses `Maestroerror\HeicToJpg` library to convert HEIC to JPG
- Returns both converted file path and updated filename with `.jpg` extension
- Original HEIC temp file is cleaned up after successful conversion

### 6. File Saving Process
**Method:** `FileSaverInterface::saveFile()`

**What happens:**
- **Path Resolution** - `FileSaverInterface::resolveTargetPath()` converts upload directory + filename to storage-specific path
- **File Detection** - Detects if source is file content (string) or file path
- **Atomic Operations** - Uses temporary files and `rename()` for atomic saves
- **Directory Creation** - Ensures target directory exists with proper permissions
- **File Writing** - Writes file content or moves file to final location

### 7. Result Handling
**Method:** `FileUploadService::handleSaveResult()`

**What happens:**
- If successful: Adds file path to `$savedFilePaths` array
- If failed: Adds error to `$errors` array
- If rollback enabled: Calls `FileUploadService::performRollback()` to delete successfully saved files

### 8. Final Result
**Method:** `FileUploadService::saveFromInput()` (return)

**Returns:** `FileUploadResult` object containing:
- `successfulFiles` - Array of successfully saved file paths
- `errors` - Array of `FileUploadError` objects for failed uploads
- `totalFiles` - Total number of files processed
- `successfulCount` - Number of successfully processed files

## Security Features

### Path Traversal Protection
- `FilesystemSaver::resolvePath()` prevents `../` attacks in filenames
- Upload destinations can use legitimate directory navigation (e.g., `../images/`)
- All paths are validated to stay within `basePath`

### Filename Sanitization
- `FilenameSanitizer::cleanFilename()` removes dangerous characters
- Null byte removal prevents security bypasses
- Unicode normalization prevents confusion attacks

### MIME Type Validation
- `FileServiceValidator::isFileTypeAllowed()` validates actual file content
- Uses `finfo_open()` to detect real MIME types
- Prevents file type spoofing attacks

### Atomic File Operations
- All file saves use temporary files + `rename()` for atomicity
- Prevents race conditions and partial file writes

## File Type Support

### Supported Categories
- **Images**: JPEG, PNG, GIF, WebP, AVIF, JXL, BMP, TIFF, HEIC, HEIF
- **PDFs**: Standard PDF, X-PDF, Acrobat, VND-PDF
- **Documents**: Word, Excel, PowerPoint, Text, RTF, CSV, XML, JSON, OpenDocument
- **CAD**: DWG, DXF, STEP, IGES, STL, SolidWorks
- **Archives**: ZIP, RAR, 7Z, TAR, GZ
- **Videos**: MP4, AVI, MOV, WMV, FLV, WebM, MKV, MPEG, 3GP, M4V, OGV

### Custom Extensions & MIME Types
- Users can specify custom file extensions
- Users can specify custom MIME types with `mime:` prefix
- Unknown extensions are whitelisted when explicitly allowed

## Storage Backend Support

### FilesystemSaver
- Local filesystem storage
- Path traversal protection
- Atomic file operations
- Configurable directory permissions
- **Auto-configuration**: `FilesystemSaver::fromUploadDestination()` derives basePath automatically
  - Absolute uploadDestination: basePath = dirname(uploadDestination)
  - Relative uploadDestination: basePath = getcwd()

### CloudStorageSaver (Example)
- Demonstrates cloud storage integration
- Uses bucket/key path format
- Can be extended for AWS S3, Google Cloud Storage, etc.

### FileSaverInterface Methods
The interface provides methods for:
- `saveFile(string $source, string $targetPath, bool $overwriteExisting = false): string`
- `resolveTargetPath(string $uploadDestination, string $filename): string`
- `ensureUploadDestinationExists(string $uploadDestination): void`
- `fileExists(string $targetPath): bool`
- `deleteFile(string $targetPath): bool`
- `getBasePath(): string`

## Error Handling

### Upload Errors
- `FileUploadError` objects contain detailed error information
- Upload error codes from `$_FILES['error']` are preserved
- Validation errors include specific failure reasons

### Rollback Support
- Optional rollback on error removes successfully saved files
- Prevents partial uploads when any file fails

## Performance Features

### High Performance Mode
- Uses UUID collision strategy for fewer filesystem calls
- Optimized for network storage scenarios

### Collision Resolution
- Multiple strategies: increment, UUID, timestamp, custom callable
- Prevents filename conflicts automatically

## Usage Examples

### Basic File Upload
```php
$service = new FileUploadService([FileTypeEnum::IMAGE]);
$result = $service->save(
    input: [$_FILES['image']],
    uploadDir: 'uploads',
    filenames: ['profile.jpg']
);
```

### Mixed Input Types
```php
$service = new FileUploadService([FileTypeEnum::IMAGE, FileTypeEnum::PDF]);
$result = $service->save(
    input: [
        $_FILES['document'],                    // File upload
        'data:image/jpeg;base64,/9j/4AAQ...'   // Data URI
    ],
    uploadDir: 'documents',
    filenames: ['contract.pdf', 'signature.jpg']
);
```

### Custom File Types
```php
$service = new FileUploadService(['mime:text/csv', '.custom']);
$service->setAllowedExtensions(['xyz']);
$service->setAllowedMimeTypes(['application/custom']);
```

This architecture provides a robust, secure, and extensible foundation for file upload handling with clear separation of concerns and comprehensive error handling.
