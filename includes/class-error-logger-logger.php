<?php
// File: includes/class-error-logger-logger.php
/**
 * Error_Logger_Logger
 *
 * Scans failed action logs on shutdown and sends Slack alerts according to per-form settings.
 *
 * @package ErrorLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Error_Logger_Logger {

    /**
     * Process recent failed actions and notify Slack.
     * Runs on shutdown.
     *
     * Reads option 'error_logger_settings' for configuration:
     *  - webhook (string)
     *  - global_fields (csv)
     *  - forms => [ form_name => ['fields' => 'a,b,c'] ]
     */
    public static function process_failed_actions() {
        global $wpdb;

        $opts = get_option( 'error_logger_settings', array() );
        if ( ! is_array( $opts ) ) {
            $opts = array();
        }

        $webhook = isset( $opts['webhook'] ) ? trim( $opts['webhook'] ) : '';
        if ( empty( $webhook ) || strpos( $webhook, 'hooks.slack.com' ) === false ) {
            return;
        }

        // Default fallback fields
        $global_fields_raw = isset( $opts['global_fields'] ) ? $opts['global_fields'] : 'name,email';
        $global_allowed_fields = array_filter( array_map( 'trim', explode( ',', $global_fields_raw ) ) );

        $log_table    = $wpdb->prefix . 'e_submissions_actions_log';
        $subs_table   = $wpdb->prefix . 'e_submissions';
        $values_table = $wpdb->prefix . 'e_submissions_values';

        $window_minutes = 10;

        // Query recent failed action logs and join submission metadata
        $failed = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id, l.submission_id, l.log, l.created_at, l.created_at_gmt, s.referer, s.user_agent, s.form_name
                 FROM {$log_table} l
                 LEFT JOIN {$subs_table} s ON l.submission_id = s.id
                 WHERE l.status = %s
                   AND l.created_at >= (NOW() - INTERVAL %d MINUTE)
                 ORDER BY l.id ASC",
                'failed',
                $window_minutes
            )
        );

        if ( empty( $failed ) ) {
            return;
        }

        foreach ( $failed as $row ) {
            $log_id = (int) $row->id;
            if ( $log_id <= 0 ) {
                continue;
            }

            // Deduplicate per log ID
            $option_name = 'Error_Logger_notified_' . $log_id;
            $added = add_option( $option_name, time(), '', 'no' );
            if ( $added === false ) {
                continue;
            }

            // Parse error message
            $error_info = '';
            if ( ! empty( $row->log ) ) {
                $decoded = json_decode( $row->log, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                    $error_info = ! empty( $decoded['message'] )
                        ? (string) $decoded['message']
                        : trim( print_r( $decoded, true ) );
                } else {
                    $error_info = trim( $row->log );
                }
            }

            // Get page URL (referer fallback)
            $page_url = ! empty( $row->referer ) ? $row->referer : '';
            if ( empty( $page_url ) ) {
                $page_url = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT `value` FROM {$values_table}
                         WHERE submission_id = %d
                           AND `key` IN ('source_url','referer')
                         LIMIT 1",
                        $row->submission_id
                    )
                );
            }

            // 🔧 Determine which fields to fetch: per-form else global
            $form_name = ! empty( $row->form_name ) ? trim( $row->form_name ) : '';
            $allowed_fields = $global_allowed_fields;

            if ( $form_name && ! empty( $opts['forms'] ) && is_array( $opts['forms'] ) ) {
                // Normalize both sides for safer match (ignore case & spacing)
                $normalized_forms = array_change_key_case( array_combine(
                    array_map( fn( $k ) => preg_replace( '/\s+/', '', strtolower( $k ) ), array_keys( $opts['forms'] ) ),
                    $opts['forms']
                ));

                $lookup = preg_replace( '/\s+/', '', strtolower( $form_name ) );

                if ( isset( $normalized_forms[ $lookup ] ) ) {
                    $per = isset( $normalized_forms[ $lookup ]['fields'] )
                        ? $normalized_forms[ $lookup ]['fields']
                        : '';
                    if ( $per !== '' ) {
                        $allowed_fields = array_filter( array_map( 'trim', explode( ',', $per ) ) );
                    }
                }
            }

            // Fetch submission field values in the defined order
            $fields = array();
            if ( ! empty( $allowed_fields ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $allowed_fields ), '%s' ) );
                $query = $wpdb->prepare(
                    "SELECT `key`, `value` FROM {$values_table}
                     WHERE submission_id = %d AND `key` IN ($placeholders)",
                    array_merge( array( $row->submission_id ), $allowed_fields )
                );
                $rows = $wpdb->get_results( $query );

                $map = array();
                if ( $rows ) {
                    foreach ( $rows as $fv ) {
                        $map[ strtolower( $fv->key ) ] = $fv->value;
                    }
                    foreach ( $allowed_fields as $k ) {
                        $kl = strtolower( trim( $k ) );
                        if ( isset( $map[ $kl ] ) ) {
                            $fields[ $k ] = $map[ $kl ];
                        }
                    }
                }
            }

            // Format timestamp → PT
            $ts = ! empty( $row->created_at_gmt )
                ? $row->created_at_gmt
                : ( ! empty( $row->created_at ) ? $row->created_at : current_time( 'mysql', true ) );

            try {
                $dt = new DateTime( $ts, new DateTimeZone( 'UTC' ) );
                $dt->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) );
                $timestamp = $dt->format( 'Y-m-d h:i A' ) . ' PT';
            } catch ( Exception $e ) {
                $timestamp = gmdate( 'Y-m-d h:i A' ) . ' PT';
            }

            // Clean UA
            $ua_clean = Error_Logger_Helper::clean_user_agent( $row->user_agent ?? '' );

            // Build Slack message
            $site_title = get_bloginfo( 'name' );
            $text  = "🚨 *Form Submission Error*\n";
            $text .= "*Site:* {$site_title}\n";
            $text .= "*Form:* " . ( $form_name ?: 'Unknown' ) . "\n";
            $text .= "*Page URL:* " . ( $page_url ?: 'Unknown' ) . "\n";
            $text .= "*Error Message:*\n```" . ( $error_info ?: 'Unknown' ) . "```\n";

            if ( $ua_clean ) {
                $text .= "*User Agent:* {$ua_clean}\n";
            }

            $text .= "> *Timestamp:* _{$timestamp}_\n";

            if ( ! empty( $fields ) ) {
                $text .= "\n*Submission Data:*\n";
                foreach ( $fields as $name => $val ) {
                    $label = Error_Logger_Helper::format_field_label( $name );
                    $v = is_string( $val ) ? $val : print_r( $val, true );
                    if ( strlen( $v ) > 200 ) {
                        $v = substr( $v, 0, 197 ) . '...';
                    }
                    $text .= "• *{$label}:* {$v}\n";
                }
            }

            // Send payload to Slack
            $body = wp_json_encode( array( 'text' => $text ) );
            $response = wp_remote_post( $webhook, array(
                'body'    => $body,
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
                'timeout' => 8,
                'blocking'=> true,
            ) );

            $send_failed = false;
            if ( is_wp_error( $response ) ) {
                $send_failed = true;
                error_log( 'Error_Logger_Logger: Slack send WP_Error for log ' . $log_id . ': ' . $response->get_error_message() );
            } else {
                $code = (int) wp_remote_retrieve_response_code( $response );
                if ( $code < 200 || $code >= 300 ) {
                    $send_failed = true;
                    $body = wp_remote_retrieve_body( $response );
                    error_log( 'Error_Logger_Logger: Slack returned HTTP ' . $code . ' for log ' . $log_id . '. Body: ' . $body );
                }
            }

            if ( $send_failed ) {
                delete_option( $option_name ); // allow retry later
            }
        }
    }
}
