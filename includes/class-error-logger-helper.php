<?php
// File: includes/class-error-logger-helper.php
/**
 * Error_Logger_Helper
 *
 * Utility methods for string formatting, field labels, and user agent parsing.
 *
 * @package ErrorLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Error_Logger_Helper {

    /**
     * Convert a submission field key into a human-friendly label.
     *
     * Examples:
     *   urgency_level   → Urgency Level
     *   firstName       → First Name
     *   DuplicatePageForm → Duplicate Page Form
     *
     * @param string $key The field key (e.g., urgency_level).
     * @return string Formatted label (e.g., "Urgency Level").
     */
    public static function format_field_label( $key ) {
        if ( empty( $key ) ) {
            return '';
        }

        // Replace underscores/hyphens with spaces
        $key = preg_replace( '/[_\-]+/', ' ', $key );

        // Add spaces before capital letters (e.g., DuplicatePageForm → Duplicate Page Form)
        $key = preg_replace( '/(?<!\ )[A-Z]/', ' $0', $key );

        // Normalize spacing
        $key = trim( preg_replace( '/\s+/', ' ', $key ) );

        // Capitalize each word
        return ucwords( $key );
    }

    /**
     * Clean up a User Agent string into Browser + OS.
     *
     * @param string $ua Full user agent string.
     * @return string Short version (e.g., "Chrome on Windows").
     */
    public static function clean_user_agent( $ua ) {
        if ( empty( $ua ) ) {
            return '';
        }

        $browser = 'Other';
        $os      = 'Other';

        // Browser detection
        if ( stripos( $ua, 'Chrome' ) !== false && stripos( $ua, 'Safari' ) !== false ) {
            $browser = 'Chrome';
        } elseif ( stripos( $ua, 'Safari' ) !== false ) {
            $browser = 'Safari';
        } elseif ( stripos( $ua, 'Firefox' ) !== false ) {
            $browser = 'Firefox';
        } elseif ( stripos( $ua, 'Edg' ) !== false || stripos( $ua, 'Edge') !== false ) {
            $browser = 'Edge';
        }

        // OS detection
        if ( stripos( $ua, 'Windows' ) !== false ) {
            $os = 'Windows';
        } elseif ( stripos( $ua, 'Mac OS X' ) !== false || stripos( $ua, 'Macintosh' ) !== false ) {
            $os = 'macOS';
        } elseif ( stripos( $ua, 'iPhone' ) !== false || stripos( $ua, 'iPad' ) !== false ) {
            $os = 'iOS';
        } elseif ( stripos( $ua, 'Android' ) !== false ) {
            $os = 'Android';
        } elseif ( stripos( $ua, 'Linux' ) !== false ) {
            $os = 'Linux';
        }

        return "{$browser} on {$os}";
    }
}
