<?php

declare(strict_types=1);

namespace FileUploadService\Utils;

use Normalizer;

/**
 * Utility class for sanitizing filenames and paths
 * 
 * @package FileUploadService\Utils
 */
class FilenameSanitizer
{
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
        // Normalize Unicode characters to prevent confusion attacks
        if (class_exists('Normalizer') && Normalizer::isNormalized($filename, Normalizer::FORM_C)) {
            $normalized = Normalizer::normalize($filename, Normalizer::FORM_C);
            if ($normalized !== false) {
                $filename = $normalized;
            }
        }

        // Remove underscores if requested
        if ($removeUnderscores) {
            $filename = str_replace("_", "", $filename);
        }

        // Remove spaces if requested
        if ($removeSpaces) {
            $filename = str_replace(" ", "", $filename);
        }

        // Remove custom characters if provided
        if (!empty($removeCustomChars)) {
            $filename = str_replace($removeCustomChars, "", $filename);
        }

        // Remove dangerous characters that are problematic for filesystems
        $filename = str_replace(
            ['\\', '/', ':', '*', '?', '"', '<', '>', '|', '#', '%', '&', '+', '=', ';', '!', '@', '$', '^', '`', '~'],
            '',
            $filename
        );

        // Remove any remaining control characters and non-printable characters
        $filename = self::removeControlCharacters($filename);

        // Remove leading/trailing dots and spaces (Windows issue)
        $filename = trim($filename, '. ');

        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'unnamed';
        }

        // Limit filename length to prevent filesystem issues
        // Most filesystems support 255 characters, but we'll be conservative
        $maxLength = 200;
        if (strlen($filename) > $maxLength) {
            // Preserve file extension if present
            $extension = '';
            $dotPos = strrpos($filename, '.');
            if ($dotPos !== false && $dotPos > 0) {
                $extension = substr($filename, $dotPos);
                $baseName = substr($filename, 0, $dotPos);
            } else {
                $baseName = $filename;
            }

            // Truncate base name to fit within limit
            $maxBaseLength = $maxLength - strlen($extension);
            $filename = substr($baseName, 0, $maxBaseLength) . $extension;
        }

        // Final validation - ensure we still have a valid filename
        if (empty(trim($filename)) || $filename === '.') {
            $filename = 'unnamed';
        }

        return $filename;
    }


    /**
     * Clean path by removing dangerous characters while preserving directory separators
     * Used for sanitizing file paths that may contain directory separators
     *
     * @param string $path The path to clean
     * @return string Cleaned path safe for filesystem use
     */
    public static function cleanPath(string $path): string
    {
        // Remove null bytes and control characters (critical security fix)
        $path = self::removeControlCharacters($path);

        // Remove dangerous characters that are problematic for filesystems
        // Note: We don't remove '/' or '\' here as paths can contain directory separators
        $path = str_replace(
            ['*', '?', '"', '<', '>', '|', '#', '%', '&', '+', '=', ';', '!', '@', '$', '^', '`', '~'],
            '',
            $path
        );

        // Remove leading/trailing dots and spaces (Windows issue)
        $path = trim($path, '. ');

        // Note: We don't convert empty paths to 'unnamed' here because
        // the calling code (like FilesystemSaver::resolvePath) needs to handle
        // empty paths as errors for security reasons

        return $path;
    }


    /**
     * Remove null bytes and control characters from any string
     * Core security function to prevent null byte injection attacks
     *
     * @param string $input The input string to clean
     * @return string String with control characters removed
     */
    public static function removeControlCharacters(string $input): string
    {
        // Remove null bytes and control characters (critical security fix)
        $result = preg_replace('/[\x00-\x1f\x7f-\x9f]/', '', $input);
        return $result ?? $input; // Return original input if preg_replace fails
    }
}
