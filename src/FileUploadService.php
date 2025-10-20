<?php

declare(strict_types=1);

namespace FileUploadService;

use FileUploadService\FileUploadResult;
use FileUploadService\FileUploadError;
use FileUploadService\FileServiceValidator;
use FileUploadService\FileCollisionResolver;
use FileUploadService\FileUploadSave;
use FileUploadService\DTO\FileUploadDTO;
use FileUploadService\DTO\DataUriDTO;
use FileUploadService\Enum\FileTypeEnum;
use FileUploadService\Enum\CollisionStrategyEnum;
use FileUploadService\Utils\FilenameSanitizer;
use RuntimeException;

/**
 * Service class for handling file uploads from base64 encoded data URIs or traditional file uploads ($_FILES array)
 * Supports images, PDFs, CAD drawings, and other common file types
 * 
 * @package FileUploadService
 * @see README.md for detailed usage examples and documentation
 */
class FileUploadService
{
    /**
     * Array of successfully saved file paths (for rollback tracking)
     * 
     * @var array<string>
     */
    private array $savedFiles = [];

    /**
     * Array of successfully saved file paths (for result tracking)
     * 
     * @var array<string>
     */
    private array $savedFilePaths = [];

    /**
     * Array of upload errors (for result tracking)
     * 
     * @var array<FileUploadError>
     */
    private array $errors = [];

    /**
     * Allowed file type categories (image, pdf, etc.)
     * 
     * @var array<FileTypeEnum>
     */
    private array $allowedCategories = [];

    /**
     * Allowed file extensions (custom extensions)
     * 
     * @var array<string>
     */
    private array $allowedExtensions = [];

    /**
     * Allowed MIME types (custom MIME types)
     * 
     * @var array<string>
     */
    private array $allowedMimeTypes = [];

    /**
     * File validator instance
     */
    private FileServiceValidator $validator;

    /**
     * File collision resolver instance
     */
    private FileCollisionResolver $collisionResolver;

    /**
     * File upload save instance
     */
    private FileUploadSave $fileUploadSave;


    /**
     * Collision strategy for handling filename conflicts (can be string, callable, or enum)
     * 
     * @var string|callable|CollisionStrategyEnum
     */
    private mixed $collisionStrategy;


    /**
     * Constructor 
     *
     * @param array<string|FileTypeEnum> $allowedFileTypes Array of allowed file type strings ('image', 'pdf', etc.) or enum cases, or specific file extensions
     * @param FileSaverInterface|null $fileSaver File saver implementation (defaults to FilesystemSaver)
     * @param bool $createDirectory Whether to create directory if it doesn't exist (default: true)
     * @param int $directoryPermissions Directory permissions (default: 0775)
     * @param bool $rollbackOnError Whether to remove all successfully uploaded files if any error occurs (default: false)
     * @param string|callable|CollisionStrategyEnum $collisionStrategy Strategy for resolving filename collisions ('increment', 'uuid', 'timestamp', enum, or custom callable)
     * @param bool $highPerformanceMode Enable optimizations for network storage or high-collision scenarios (uses UUID strategy, filters extensions)
     * @param bool $convertHeicToJpg Whether to convert HEIC/HEIF files to JPEG (default: true)
     * @throws RuntimeException If invalid file types are provided
     */
    public function __construct(
        private array $allowedFileTypes = [FileTypeEnum::IMAGE, FileTypeEnum::PDF, FileTypeEnum::CAD],
        private readonly ?FileSaverInterface $fileSaver = null,
        private readonly bool $createDirectory = true,
        private readonly int $directoryPermissions = 0775,
        private readonly bool $rollbackOnError = false,
        string|callable|CollisionStrategyEnum $collisionStrategy = CollisionStrategyEnum::INCREMENT,
        private readonly bool $highPerformanceMode = false,
        private readonly bool $convertHeicToJpg = true
    ) {
        $this->collisionStrategy = $collisionStrategy;
        $this->validator = new FileServiceValidator();
        $this->setAllowedFileTypes($this->allowedFileTypes);

        // High-performance mode: override collision strategy to UUID for fewer filesystem calls
        $finalCollisionStrategy = $this->highPerformanceMode && !is_callable($this->collisionStrategy)
            ? CollisionStrategyEnum::UUID->value
            : $this->deriveCollisionStrategyValue($this->collisionStrategy);

        $this->collisionResolver = new FileCollisionResolver($this->validator, $finalCollisionStrategy);

        // Create default filesystem saver if none provided
        $fileSaver = $this->fileSaver ?? new FilesystemSaver(sys_get_temp_dir(), $this->directoryPermissions, $this->createDirectory);
        $this->fileUploadSave = new FileUploadSave($this->validator, $fileSaver, $this->convertHeicToJpg);
    }


    /**
     * Set the allowed file types
     *
     * @param array<string|FileTypeEnum> $allowedFileTypes Array of allowed file type enums, specific file extensions, or MIME types (prefixed with 'mime:')
     * @throws RuntimeException If invalid file type enums are provided
     */
    public function setAllowedFileTypes(array $allowedFileTypes): void
    {
        // If empty, use constructor defaults for security
        if (empty($allowedFileTypes)) {
            $allowedFileTypes = [FileTypeEnum::IMAGE, FileTypeEnum::PDF, FileTypeEnum::CAD];
        }

        $validatedTypes = [];
        $this->allowedCategories = [];
        $this->allowedExtensions = [];
        $this->allowedMimeTypes = [];

        foreach ($allowedFileTypes as $type) {
            // Handle enum cases
            if ($type instanceof FileTypeEnum) {
                $validatedTypes[] = $type->value;
                $this->allowedCategories[] = $type;
                continue;
            }

            // Handle MIME types (prefixed with 'mime:')
            if (str_starts_with($type, 'mime:')) {
                $mimeType = substr($type, 5); // Remove 'mime:' prefix
                $validatedTypes[] = $type;
                $this->allowedMimeTypes[] = $mimeType;
                continue;
            }

            // Handle string values - try to derive enum if it's a known file type
            $derivedEnum = FileTypeEnum::tryFrom($type);
            if ($derivedEnum) {
                $validatedTypes[] = $derivedEnum->value;
                $this->allowedCategories[] = $derivedEnum;
            } else {
                // Keep as string for custom extensions
                $validatedTypes[] = $type;
                $this->allowedExtensions[] = $type;
            }
        }

        $this->allowedFileTypes = $validatedTypes;
    }


    /**
     * Get the current allowed file types
     *
     * @return array<FileTypeEnum|string> Array of allowed file type categories
     */
    public function getAllowedFileTypes(): array
    {
        return $this->allowedFileTypes;
    }


    /**
     * Set allowed file type categories
     *
     * @param array<string|FileTypeEnum> $categories Array of file type categories (image, pdf, etc.)
     */
    public function setAllowedCategories(array $categories): void
    {
        $this->allowedCategories = [];
        foreach ($categories as $category) {
            if ($category instanceof FileTypeEnum) {
                $this->allowedCategories[] = $category;
            } else {
                // Convert string to enum if possible
                $enum = FileTypeEnum::tryFrom($category);
                if ($enum) {
                    $this->allowedCategories[] = $enum;
                }
            }
        }
        $this->updateAllowedFileTypes();
    }


    /**
     * Set allowed file extensions
     *
     * @param array<string> $extensions Array of file extensions (without dots)
     */
    public function setAllowedExtensions(array $extensions): void
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        $this->updateAllowedFileTypes();
    }


    /**
     * Set allowed MIME types
     *
     * @param array<string> $mimeTypes Array of MIME types
     */
    public function setAllowedMimeTypes(array $mimeTypes): void
    {
        $this->allowedMimeTypes = $mimeTypes;
        $this->updateAllowedFileTypes();
    }


    /**
     * Get allowed categories
     *
     * @return array<FileTypeEnum> Array of allowed categories
     */
    public function getAllowedCategories(): array
    {
        return $this->allowedCategories;
    }


    /**
     * Get allowed extensions
     *
     * @return array<string> Array of allowed extensions
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }


    /**
     * Get allowed MIME types
     *
     * @return array<string> Array of allowed MIME types
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }


    /**
     * Update the combined allowedFileTypes array from individual arrays
     */
    private function updateAllowedFileTypes(): void
    {
        $combined = [];

        // Add categories
        foreach ($this->allowedCategories as $category) {
            $combined[] = $category;
        }

        // Add extensions
        foreach ($this->allowedExtensions as $extension) {
            $combined[] = $extension;
        }

        // Add MIME types with prefix
        foreach ($this->allowedMimeTypes as $mimeType) {
            $combined[] = 'mime:' . $mimeType;
        }

        $this->allowedFileTypes = $combined;
    }


    /**
     * Check if rollback on error is enabled
     *
     * @return bool True if rollback on error is enabled, false otherwise
     */
    public function isRollbackOnErrorEnabled(): bool
    {
        return $this->rollbackOnError;
    }


    /**
     * Check if HEIC/HEIF to JPEG conversion is enabled
     *
     * @return bool True if HEIC conversion is enabled, false otherwise
     */
    public function isHeicConversionEnabled(): bool
    {
        return $this->convertHeicToJpg;
    }


    /**
     * Check if a specific file type category is allowed
     *
     * @param string $fileType The file type category to check
     * @return bool True if the file type is allowed, false otherwise
     */
    public function isFileTypeCategoryAllowed(string $fileType): bool
    {
        // Check if the specific category constant is allowed
        if (in_array($fileType, $this->allowedFileTypes, true)) {
            return true;
        }

        // If we have specific extensions allowed, check if any extensions in this category are allowed
        $categoryExtensions = $this->getExtensionsForCategory($fileType);
        foreach ($categoryExtensions as $ext) {
            foreach ($this->allowedFileTypes as $allowedType) {
                $allowedTypeStr = $allowedType instanceof FileTypeEnum ? $allowedType->value : $allowedType;
                if (strtolower($allowedTypeStr) === strtolower($ext)) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Add file type categories or specific extensions to the allowed list
     *
     * @param string|array<string> $fileTypes The file type category constant(s) or specific file extension(s) to add
     * @throws RuntimeException If invalid file type constant is provided
     */
    public function allowFileType(string|array $fileTypes): void
    {
        // Normalize to array
        $fileTypes = is_array($fileTypes) ? $fileTypes : [$fileTypes];
        $validTypes = array_map(fn($enum) => $enum->value, self::getAvailableFileTypeCategories());

        foreach ($fileTypes as $fileType) {
            // Check if it's a valid constant
            if (in_array($fileType, $validTypes, true)) {
                if (!in_array($fileType, $this->allowedFileTypes, true)) {
                    $this->allowedFileTypes[] = $fileType;

                    // Convert string to enum
                    $enum = FileTypeEnum::from($fileType);
                    $this->allowedCategories[] = $enum;
                }
                continue;
            }

            // Handle MIME types (prefixed with 'mime:')
            if (str_starts_with($fileType, 'mime:')) {
                $mimeType = substr($fileType, 5); // Remove 'mime:' prefix
                if (!in_array($fileType, $this->allowedFileTypes, true)) {
                    $this->allowedFileTypes[] = $fileType;
                    $this->allowedMimeTypes[] = $mimeType;
                }
                continue;
            }

            // For custom extensions, add as-is
            if (!in_array($fileType, $this->allowedFileTypes, true)) {
                $this->allowedFileTypes[] = $fileType;
                $this->allowedExtensions[] = $fileType;
            }
        }
    }


    /**
     * Remove file type categories from the allowed list
     *
     * @param string|array<string> $fileTypes The file type category(ies) to remove
     */
    public function disallowFileType(string|array $fileTypes): void
    {
        // Normalize to array
        $fileTypes = is_array($fileTypes) ? $fileTypes : [$fileTypes];

        foreach ($fileTypes as $fileType) {
            // Remove the file type from allowedFileTypes array
            $key = array_search($fileType, $this->allowedFileTypes, true);
            if ($key !== false) {
                unset($this->allowedFileTypes[$key]);
            }
        }

        // Re-index the array to remove gaps
        $this->allowedFileTypes = array_values($this->allowedFileTypes);
    }


    /**
     * Check if all file types are allowed (no restrictions)
     *
     * @return bool True if no restrictions, false otherwise
     */
    public function isUnrestricted(): bool
    {
        return empty($this->allowedFileTypes) || in_array(FileTypeEnum::ALL->value, $this->allowedFileTypes, true);
    }


    /**
     * Save files automatically detecting input type (base64 data URIs or traditional file uploads)
     * Handles multiple files seamlessly, including mixed input types
     * 
     * This method automatically detects and handles different $_FILES structures internally
     * 
     * Single File Upload:
     * $input = [$_FILES['files']] where $_FILES['files']['name'] is a string
     * 
     * Multiple File Upload:
     * $input = [$_FILES['files']] where $_FILES['files']['name'] is an array
     * 
     * Mixed Input:
     * $input = [
     *     'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...',  // base64
     *     $_FILES['uploaded_file'],                                // $_FILES
     *     'data:application/pdf;base64,JVBERi0xLjQKMSAwIG9i...', // base64
     *     $_FILES['another_file']                                  // $_FILES
     * ];
     * 
     * All inputs are automatically converted to individual file arrays for consistent processing.
     *
     * @param array<string|array<string, mixed>> $input Array of data URIs, $_FILES arrays, or mixed input types
     * @param string $uploadDestination The destination to save files to (directory, bucket/key prefix, etc.)
     * @param array<string> $filenames Array of filenames corresponding to each input
     * @param bool $overwriteExisting Whether to overwrite existing files (default: false)
     * @return FileUploadResult Detailed result with successful uploads and errors
     * @throws RuntimeException If upload destination cannot be created or is not writable
     */
    public function save(
        array $input,
        string $uploadDestination,
        array $filenames,
        bool $overwriteExisting = false,
        bool $generateUniqueFilenames = false
    ): FileUploadResult {
        // Smart detection: If $input itself is a multi-file upload array (e.g., $_FILES['pictures']),
        // wrap it so it's treated as a single input rather than iterating over its keys
        if ($this->isMultiFileUploadArray($input)) {
            $input = [$input];
        }
        
        // Process all inputs using the unified method
        // This handles the complexity of different $_FILES structures internally
        /** @var array<string|array<string, mixed>> $input */
        return $this->saveFromInput($input, $uploadDestination, $filenames, $overwriteExisting, $generateUniqueFilenames);
    }


    /**
     * Check if input is a traditional file upload array ($_FILES)
     */
    private function isFileUploadArray(mixed $input): bool
    {
        if (!is_array($input)) {
            return false;
        }

        // Check if it has the structure of $_FILES array
        return isset($input['name']) && isset($input['tmp_name']) && isset($input['error']);
    }


    /**
     * Check if input is a multi-file upload array ($_FILES['files'])
     */
    private function isMultiFileUploadArray(mixed $input): bool
    {
        if (!is_array($input)) {
            return false;
        }

        // Check if it has the structure of multi-file $_FILES array
        // name, tmp_name, error, etc. should all be arrays
        return isset($input['name']) && isset($input['tmp_name']) && isset($input['error']) &&
            is_array($input['name']) && is_array($input['tmp_name']) && is_array($input['error']);
    }


    /**
     * Check if input is a single file upload array ($_FILES['file'])
     */
    private function isSingleFileUploadArray(mixed $input): bool
    {
        if (!is_array($input)) {
            return false;
        }

        // Check if it has the structure of a single $_FILES array
        return isset($input['name']) && isset($input['tmp_name']) && isset($input['error']) &&
            !is_array($input['name']) && !is_array($input['tmp_name']) && !is_array($input['error']);
    }


    /**
     * Convert multi-file upload array to individual file arrays
     * 
     * Takes a multi-file $_FILES structure and converts it to an array of individual file arrays.
     * This allows the service to process each file individually using the same logic.
     * 
     * Input: $_FILES['files'] with arrays for name, tmp_name, etc.
     * Output: Array of individual file arrays, each with the same structure as a single file upload
     * 
     * @param array<string, array<string|int, mixed>> $multiFileArray Multi-file array from $_FILES
     * @return array<int, array<string, mixed>> Array of individual file arrays
     */
    private function convertMultiFileToIndividualFiles(array $multiFileArray): array
    {
        // Example input structure ($_FILES['files']):
        // [
        //     'name' => ['image1.jpg', 'document.pdf', 'drawing.dwg'],
        //     'type' => ['image/jpeg', 'application/pdf', 'application/acad'],
        //     'tmp_name' => ['/tmp/abc123', '/tmp/def456', '/tmp/ghi789'],
        //     'error' => [0, 0, 0],
        //     'size' => [12345, 67890, 11111]
        // ]

        $individualFiles = [];
        $count = count($multiFileArray['name']);

        for ($i = 0; $i < $count; $i++) {
            // Convert each file to individual structure:
            // [
            //     'name' => 'image1.jpg',
            //     'type' => 'image/jpeg',
            //     'tmp_name' => '/tmp/abc123',
            //     'error' => 0,
            //     'size' => 12345
            // ]
            $individualFiles[] = [
                'name' => $multiFileArray['name'][$i],
                'type' => $multiFileArray['type'][$i] ?? '',
                'tmp_name' => $multiFileArray['tmp_name'][$i],
                'error' => $multiFileArray['error'][$i],
                'size' => $multiFileArray['size'][$i] ?? 0
            ];
        }

        return $individualFiles;
    }


    /**
     * Convert single file upload array to individual file array
     * 
     * Takes a single file $_FILES structure and converts it to the standard individual file format.
     * This ensures consistency between single and multiple file uploads - both end up as
     * individual file arrays that can be processed by the same logic.
     * 
     * Input: $_FILES['files'] with strings for name, tmp_name, etc.
     * Output: Individual file array with the same structure as files from multi-upload
     * 
     * @param array<string, mixed> $singleFileArray Single file array from $_FILES
     * @return array<string, mixed> Individual file array
     */
    private function convertSingleFileToIndividualFile(array $singleFileArray): array
    {
        // Example input structure ($_FILES['files']):
        // [
        //     'name' => 'image.jpg',
        //     'type' => 'image/jpeg',
        //     'tmp_name' => '/tmp/abc123',
        //     'error' => 0,
        //     'size' => 12345
        // ]

        // Convert to individual file structure (same format as multi-file conversion):
        // [
        //     'name' => 'image.jpg',
        //     'type' => 'image/jpeg',
        //     'tmp_name' => '/tmp/abc123',
        //     'error' => 0,
        //     'size' => 12345
        // ]
        return [
            'name' => $singleFileArray['name'],
            'type' => $singleFileArray['type'] ?? '',
            'tmp_name' => $singleFileArray['tmp_name'],
            'error' => $singleFileArray['error'],
            'size' => $singleFileArray['size'] ?? 0
        ];
    }


    /**
     * Save files from any input types (base64 data URIs and $_FILES arrays)
     * Processes each item individually based on automatic type detection
     *
     * @param array<string|array<string, mixed>> $inputs Array of input types (base64 strings, $_FILES arrays, etc.)
     * @param string $uploadDestination The destination to save files to (directory, bucket/key prefix, etc.)
     * @param array<string> $filenames Array of filenames corresponding to each input
     * @param bool $overwriteExisting Whether to overwrite existing files (default: false)
     * @return FileUploadResult Detailed result with successful uploads and errors
     * @throws RuntimeException If upload destination cannot be created or is not writable
     */
    private function saveFromInput(
        array $inputs,
        string $uploadDestination,
        array $filenames,
        bool $overwriteExisting = false,
        bool $generateUniqueFilenames = false
    ): FileUploadResult {
        // Ensure upload destination exists & is accessible
        if ($this->fileSaver) {
            $this->fileSaver->ensureUploadDestinationExists($uploadDestination);
        }

        // Process inputs and expand any multi-file upload arrays
        $processedInputs = [];
        $processedFilenames = [];
        $filenameIndex = 0;

        foreach ($inputs as $index => $input) {
            if ($this->isMultiFileUploadArray($input) && is_array($input)) {
                // This is a multi-file upload array, expand it
                // Type assertion: we know this is a multi-file array structure
                /** @var array<string, array<int|string, mixed>> $input */
                $individualFiles = $this->convertMultiFileToIndividualFiles($input);
                $fileCount = count($individualFiles);

                // Get the corresponding filenames for this multi-file input
                // We need to get filenames starting from the current filenameIndex
                $multiFileFilenames = array_slice($filenames, $filenameIndex, $fileCount);

                if (count($multiFileFilenames) !== $fileCount) {
                    throw new RuntimeException("Filename count mismatch for multi-file input at index {$index}. Expected {$fileCount} filenames, got " . count($multiFileFilenames) . ". Total filenames available: " . count($filenames) . ", starting from index: {$filenameIndex}");
                }

                // Add each individual file and its corresponding filename
                foreach ($individualFiles as $fileIndex => $individualFile) {
                    $processedInputs[] = $individualFile;
                    $processedFilenames[] = $multiFileFilenames[$fileIndex];
                }

                $filenameIndex += $fileCount;
            } elseif ($this->isSingleFileUploadArray($input) && is_array($input)) {
                // This is a single file upload array, convert it to individual file format
                $individualFile = $this->convertSingleFileToIndividualFile($input);
                $processedInputs[] = $individualFile;
                $processedFilenames[] = $filenames[$filenameIndex];
                $filenameIndex++;
            } else {
                // This is a single input (base64 or other format)
                $processedInputs[] = $input;
                $processedFilenames[] = $filenames[$filenameIndex];
                $filenameIndex++;
            }
        }

        // Validate that the total processed count matches
        if (count($processedInputs) !== count($processedFilenames)) {
            throw new RuntimeException("Processed input count (" . count($processedInputs) . ") must match processed filename count (" . count($processedFilenames) . ")");
        }

        // Use the processed inputs for the rest of the method
        $inputs = $processedInputs;
        $filenames = $processedFilenames;

        // Generate unique filenames if requested
        if ($generateUniqueFilenames) {
            $filenames = $this->collisionResolver->generateUniqueFilenames($uploadDestination, $filenames);
        }

        // Initialize tracking arrays
        $this->savedFilePaths = [];
        $this->errors = [];
        $this->savedFiles = [];

        $totalFiles = count($inputs);

        // Process each input item individually
        foreach ($inputs as $index => $input) {
            $filename = $filenames[$index];

            try {
                // Create FileUploadDTO based on input type
                if ($this->isFileUploadArray($input) && is_array($input)) {
                    // Create FileUploadDTO from $_FILES array
                    $fileUploadDTO = FileUploadDTO::fromFilesArray($input, $filename);

                    // Process and save from file upload
                    $result = $this->fileUploadSave->processFileUpload($fileUploadDTO, $uploadDestination, $overwriteExisting, $this->allowedFileTypes);
                    $this->handleSaveResult($result);
                } elseif (is_string($input)) {
                    // Create DataUriDTO from base64 data URI
                    $dataUriDTO = DataUriDTO::fromDataUri($input, $filename);

                    // Process and save from base64 input
                    $result = $this->fileUploadSave->processBase64Input($dataUriDTO, $uploadDestination, $overwriteExisting, $this->allowedFileTypes);
                    $this->handleSaveResult($result);
                }
            } catch (RuntimeException $e) {
                // If we can't process an item, add it to errors and continue
                $this->errors[] = new FileUploadError($filename, "Could not process input: " . $e->getMessage());
            }
        }

        // Return combined results
        return new FileUploadResult(
            successfulFiles: $this->savedFilePaths,
            errors: $this->errors,
            totalFiles: $totalFiles,
            successfulCount: count($this->savedFilePaths)
        );
    }


    /**
     * Handle the result from file save operations
     *
     * @param array{success: bool, filePath?: string, error?: FileUploadError} $result Result array from FileUploadSave
     */
    private function handleSaveResult(array $result): void
    {
        if ($result['success']) {
            // Construct full path for FilesystemSaver
            $filePath = $result['filePath'] ?? '';
            if ($filePath && $this->fileUploadSave->getFileSaver() instanceof FilesystemSaver) {
                $basePath = $this->fileUploadSave->getFileSaver()->getBasePath();
                $filePath = rtrim($basePath, '/') . '/' . ltrim($filePath, '/');
            }

            $this->savedFilePaths[] = $filePath;
            $this->savedFiles[] = $filePath; // Track for potential rollback
        } else {
            $error = $result['error'] ?? null;
            if ($error instanceof FileUploadError) {
                $this->errors[] = $error;
            }

            // Rollback if enabled
            if ($this->rollbackOnError) {
                $this->performRollback();
            }
        }
    }


    /**
     * Get the file type category from a file extension
     *
     * @param string $extension The file extension (without dot)
     * @return FileTypeEnum|string The file type enum or 'unknown' for unrecognized types
     */
    public function getFileTypeCategoryFromExtension(string $extension): FileTypeEnum|string
    {
        $extension = strtolower($extension);

        $enum = $this->validator->getFileTypeCategoryFromExtension($extension);
        return $enum ?? 'unknown';
    }


    /**
     * Check if file type is allowed based on extension and current restriction mode
     *
     * @param string $extension File extension (without dot)
     * @return bool True if the file type is allowed, false otherwise
     */
    public function isFileTypeAllowedByExtension(string $extension): bool
    {
        // If FILE_TYPE_ALL is allowed, accept any file type
        if (in_array(FileTypeEnum::ALL->value, $this->allowedFileTypes, true)) {
            return true;
        }

        $extension = strtolower($extension);

        // First check if the specific extension is explicitly allowed
        foreach ($this->allowedFileTypes as $allowedType) {
            $allowedTypeStr = $allowedType instanceof FileTypeEnum ? $allowedType->value : $allowedType;
            if (strtolower($allowedTypeStr) === $extension) {
                return true;
            }
        }

        // Then check if the file type category is allowed
        $fileTypeCategory = $this->getFileTypeCategoryFromExtension($extension);
        $categoryValue = $fileTypeCategory instanceof FileTypeEnum ? $fileTypeCategory->value : $fileTypeCategory;
        return $this->isFileTypeCategoryAllowed($categoryValue);
    }


    /**
     * Clean filename by removing special characters and making it safe for filesystem
     * Enhanced with security measures including length limits and Unicode normalization
     *
     * @param string $filename The filename to clean
     * @param bool $removeUnderscores Whether to remove underscores (default: false)
     * @param bool $removeSpaces Whether to remove spaces (default: false)
     * @param array<string> $removeCustomChars Array of custom characters to remove (default: [])
     * @return string Cleaned filename safe for filesystem use
     */
    public static function cleanFilename(
        string $filename,
        bool $removeUnderscores = false,
        bool $removeSpaces = false,
        array $removeCustomChars = []
    ): string {
        return FilenameSanitizer::cleanFilename($filename, $removeUnderscores, $removeSpaces, $removeCustomChars);
    }


    /**
     * Get available file type categories
     *
     * @return array<FileTypeEnum> Array of available file type enum cases
     */
    public static function getAvailableFileTypeCategories(): array
    {
        return FileTypeEnum::cases();
    }


    /**
     * Derive collision strategy value from mixed input
     *
     * @param string|callable|CollisionStrategyEnum $strategy The collision strategy input
     * @return string The string value for the collision strategy
     */
    private function deriveCollisionStrategyValue(string|callable|CollisionStrategyEnum $strategy): string
    {
        if ($strategy instanceof CollisionStrategyEnum) {
            return $strategy->value;
        }

        if (is_callable($strategy)) {
            return 'custom'; // Return a string identifier for callable strategies
        }

        // Try to derive enum from string
        $derivedEnum = CollisionStrategyEnum::tryFrom($strategy);

        return $derivedEnum ? $derivedEnum->value : $strategy;
    }


    /**
     * Get human-readable description of current restrictions
     *
     * @return string Human-readable description of current file type restrictions
     */
    public function getRestrictionDescription(): string
    {
        if ($this->isUnrestricted()) {
            return 'All file types allowed';
        }

        // Check if FILE_TYPE_ALL is explicitly set
        if (in_array(FileTypeEnum::ALL->value, $this->allowedFileTypes, true)) {
            return 'All file types allowed';
        }

        $allowedTypes = $this->allowedFileTypes;
        $count = count($allowedTypes);

        if ($count === 1) {
            $firstType = $allowedTypes[0] instanceof FileTypeEnum ? $allowedTypes[0]->value : $allowedTypes[0];
            return ucfirst($firstType) . ' files only';
        }

        if ($count === 2) {
            $firstType = $allowedTypes[0] instanceof FileTypeEnum ? $allowedTypes[0]->value : $allowedTypes[0];
            $secondType = $allowedTypes[1] instanceof FileTypeEnum ? $allowedTypes[1]->value : $allowedTypes[1];
            return ucfirst($firstType) . ' and ' . $secondType . ' files';
        }

        $lastType = array_pop($allowedTypes);
        $lastTypeStr = $lastType instanceof FileTypeEnum ? $lastType->value : $lastType;
        $types = implode(', ', array_map(function ($type) {
            return $type instanceof FileTypeEnum ? $type->value : $type;
        }, $allowedTypes)) . ', and ' . $lastTypeStr;
        return ucfirst($types) . ' files';
    }


    /**
     * Get all extensions for a specific file type category
     *
     * @param string $category The file type category constant
     * @return array<string> Array of extensions for the category
     */
    private function getExtensionsForCategory(string $category): array
    {
        return match ($category) {
            FileTypeEnum::IMAGE->value   => array_values($this->validator->getSupportedImageTypes()),
            FileTypeEnum::VIDEO->value   => array_values($this->validator->getSupportedVideoTypes()),
            FileTypeEnum::PDF->value     => array_values($this->validator->getSupportedPdfTypes()),
            FileTypeEnum::CAD->value     => array_values($this->validator->getSupportedCadTypes()),
            FileTypeEnum::DOC->value     => array_values($this->validator->getSupportedDocumentTypes()),
            FileTypeEnum::ARCHIVE->value => array_values($this->validator->getSupportedArchiveTypes()),
            default => []
        };
    }


    /**
     * Cleanup saved files when errors occur
     *
     * @param array<string> $filePaths Array of file paths to remove
     */
    private function cleanupSavedFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }


    /**
     * Perform rollback by removing saved files and clearing successful file arrays
     */
    private function performRollback(): void
    {
        $this->cleanupSavedFiles($this->savedFiles);
        $this->savedFiles = [];
        $this->savedFilePaths = [];
    }
}
