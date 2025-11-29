<?php
/**
 * Plugin Name: Error Logger
 * Description: Logs form submission action failures (Elementor and future) and sends formatted Slack alerts with per-form selectable submission fields.
 * Version: 1.1.0
 * Author: Joshua Osho
 * Text Domain: error-logger
 *
 * @package ErrorLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'Error_Logger_PLUGIN_PATH' ) ) {
    define( 'Error_Logger_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'Error_Logger_PLUGIN_URL' ) ) {
    define( 'Error_Logger_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Load classes
require_once Error_Logger_PLUGIN_PATH . 'includes/class-error-logger-admin.php';
require_once Error_Logger_PLUGIN_PATH . 'includes/class-error-logger-helper.php';
require_once Error_Logger_PLUGIN_PATH . 'includes/class-error-logger-logger.php';

/**
 * Error Logger bootstrap
 */
final class Error_Logger {

    public function __construct() {
        // Admin
        add_action( 'admin_menu', array( 'Error_Logger_Admin', 'register_menu' ) );
        add_action( 'admin_init', array( 'Error_Logger_Admin', 'register_settings' ) );

        // Logger
        add_action( 'shutdown', array( 'Error_Logger_Logger', 'process_failed_actions' ) );
    }
}

new Error_Logger();
