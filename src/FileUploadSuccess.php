<?php

declare(strict_types=1);

namespace FileUploadService;

/**
 * Represents one successfully processed and saved upload.
 *
 * Filename fields are not aliases:
 * - originalFilename is untrusted provenance (raw $_FILES['name'] when present)
 * - requestedFilename is the caller-supplied filenames[] value
 * - storedFilename is the package-produced logical/sanitized final filename
 * - storedPath is an opaque saver-returned storage identifier
 *
 * Do not parse storedPath to recover storedFilename, extension, MIME type, or size.
 */
final readonly class FileUploadSuccess
{
    /**
     * @param string|null $originalFilename UNTRUSTED provenance: raw $_FILES['name'] when present; null for data URIs. Never use as a path, stored filename, or Content-Disposition value.
     * @param string $requestedFilename Caller filenames[] value for this slot, before sanitization/collision/conversion
     * @param string $storedFilename Final logical filename from upload processing (sanitization, collision, conversion). Independent of storedPath.
     * @param string $storedPath Opaque saver-returned storage identifier; may have a completely different basename than storedFilename
     * @param string $extension Final processed-file extension derived from storedFilename (lowercase, no dot; '' if none). Never parsed from storedPath.
     * @param string|null $mimeType MIME of the bytes handed to the saver. Never inferred from storedPath.
     * @param int|null $sizeBytes Byte length of the file handed to the saver. Never inferred from storedPath.
     * @param bool $wasConverted True only when the stored artifact is a transformed version of the input
     * @param int $inputIndex 0-based index in the expanded input list
     */
    public function __construct(
        public ?string $originalFilename,
        public string $requestedFilename,
        public string $storedFilename,
        public string $storedPath,
        public string $extension,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public bool $wasConverted,
        public int $inputIndex,
    ) {}
}
