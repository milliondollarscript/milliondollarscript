<?php

namespace MillionDollarScript\Classes\System;

/**
 * Secure file upload validation utility
 * 
 * Provides comprehensive security validation for file uploads including:
 * - MIME type validation
 * - File extension validation  
 * - Magic number verification
 * - File size limits
 * - Malicious content detection
 */
class FileValidator {

    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    
    const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif']
    ];
    
    const IMAGE_MAGIC_NUMBERS = [
        'jpeg' => ["\xFF\xD8\xFF"],
        'png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif'  => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"]
    ];

    /**
     * Validate uploaded file for security
     *
     * @param array $file $_FILES entry
     * @return array Validation result with 'valid' boolean and 'error' message
     */
    public static function validate_image_upload( $file ): array {
        $result = ['valid' => false, 'error' => ''];

        // Check if file was uploaded
        if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            $result['error'] = 'Invalid file upload.';
            return $result;
        }

        // Check file size
        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            $result['error'] = 'File size exceeds limit (5MB maximum).';
            return $result;
        }

        if ( $file['size'] === 0 ) {
            $result['error'] = 'Empty file not allowed.';
            return $result;
        }

        // Get file extension
        $filename = sanitize_file_name( $file['name'] );
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        
        if ( empty( $extension ) ) {
            $result['error'] = 'File must have an extension.';
            return $result;
        }

        // Check MIME type
        if ( ! function_exists( 'mime_content_type' ) ) {
            $result['error'] = 'MIME type detection not available.';
            return $result;
        }
        
        $mime_type = mime_content_type( $file['tmp_name'] );
        
        // Validate MIME type and extension match
        $valid_combo = false;
        foreach ( self::ALLOWED_IMAGE_TYPES as $allowed_mime => $allowed_extensions ) {
            if ( $mime_type === $allowed_mime && in_array( $extension, $allowed_extensions ) ) {
                $valid_combo = true;
                break;
            }
        }
        
        if ( ! $valid_combo ) {
            $result['error'] = 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.';
            return $result;
        }

        // Validate magic numbers
        if ( ! self::validate_magic_numbers( $file['tmp_name'], $extension ) ) {
            $result['error'] = 'File content does not match file type.';
            return $result;
        }

        // Additional security checks
        if ( ! self::scan_for_malicious_content( $file['tmp_name'] ) ) {
            $result['error'] = 'File contains potentially malicious content.';
            return $result;
        }

        $result['valid'] = true;
        return $result;
    }

    /**
     * Validate file magic numbers
     *
     * @param string $filepath Path to uploaded file
     * @param string $extension File extension
     * @return bool True if magic numbers are valid
     */
    private static function validate_magic_numbers( $filepath, $extension ): bool {
        if ( $extension === 'jpg' ) {
            $extension = 'jpeg';
        }

        $handle = fopen( $filepath, 'rb' );
        if ( ! $handle ) {
            return false;
        }

        $header = fread( $handle, 16 );
        fclose( $handle );

        $magic_numbers = self::IMAGE_MAGIC_NUMBERS[ $extension ] ?? [];
        
        foreach ( $magic_numbers as $magic ) {
            if ( substr( $header, 0, strlen( $magic ) ) === $magic ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan for malicious content in uploaded file
     *
     * @param string $filepath Path to uploaded file
     * @return bool True if file appears safe
     */
    private static function scan_for_malicious_content( $filepath ): bool {
        $handle = fopen( $filepath, 'rb' );
        if ( ! $handle ) {
            return false;
        }

        // Read first 1KB for malicious patterns
        $content = fread( $handle, 1024 );
        fclose( $handle );

        // Check for PHP tags and other suspicious patterns
        $malicious_patterns = [
            '/<\?php/i',
            '/<\?=/i', 
            '/<script/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/file_get_contents\s*\(/i',
        ];

        foreach ( $malicious_patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate secure filename
     *
     * @param string $original_filename Original filename
     * @param string $prefix Optional prefix
     * @return string Secure filename
     */
    public static function generate_secure_filename( $original_filename, $prefix = '' ): string {
        $extension = strtolower( pathinfo( $original_filename, PATHINFO_EXTENSION ) );
        $timestamp = time();
        $random = wp_generate_password( 8, false, false );
        
        return $prefix . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Get secure upload directory path
     *
     * @param string $subdir Optional subdirectory
     * @return string Secure upload path
     */
    public static function get_secure_upload_path( $subdir = '' ): string {
        $wp_upload_dir = wp_upload_dir();
        $base_path = $wp_upload_dir['basedir'] . '/mds-secure/';
        
        if ( $subdir ) {
            $base_path .= trailingslashit( $subdir );
        }
        
        // Create directory if it doesn't exist
        if ( ! file_exists( $base_path ) ) {
            wp_mkdir_p( $base_path );
            
            // Add .htaccess to prevent direct access
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents( $base_path . '.htaccess', $htaccess_content );
        }
        
        return $base_path;
    }
}