<?php
/**
 * Error_Logger_Admin
 *
 * Admin settings UI for Error Logger (global webhook + per-form fields).
 *
 * @package ErrorLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Error_Logger_Admin {

	const OPTION_NAME     = 'error_logger_settings';
	const SETTINGS_GROUP  = 'Error_Logger_settings_group';
	const PAGE_SLUG_MAIN  = 'error-logger';
	const PAGE_SLUG_FORMS = 'error-logger-forms';

	/**
	 * Register admin menus.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Error Logger', 'error-logger' ),
			__( 'Error Logger', 'error-logger' ),
			'manage_options',
			self::PAGE_SLUG_MAIN,
			array( __CLASS__, 'render_global_settings_page' ),
			'dashicons-bell',
			80
		);

		add_submenu_page(
			self::PAGE_SLUG_MAIN,
			__( 'Global Settings', 'error-logger' ),
			__( 'Global Settings', 'error-logger' ),
			'manage_options',
			self::PAGE_SLUG_MAIN,
			array( __CLASS__, 'render_global_settings_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG_MAIN,
			__( 'Form Config', 'error-logger' ),
			__( 'Form Config', 'error-logger' ),
			'manage_options',
			self::PAGE_SLUG_FORMS,
			array( __CLASS__, 'render_forms_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array( __CLASS__, 'sanitize_settings' )
		);

		add_settings_section(
			'Error_Logger_main_section',
			__( 'Slack Integration Settings', 'error-logger' ),
			'__return_false',
			self::PAGE_SLUG_MAIN
		);

		add_settings_field(
			'Error_Logger_slack_webhook',
			__( 'Slack Webhook URL', 'error-logger' ),
			array( __CLASS__, 'webhook_field_callback' ),
			self::PAGE_SLUG_MAIN,
			'Error_Logger_main_section'
		);

		add_settings_field(
			'Error_Logger_slack_fields',
			__( 'Fields to Send (global fallback)', 'error-logger' ),
			array( __CLASS__, 'fields_field_callback' ),
			self::PAGE_SLUG_MAIN,
			'Error_Logger_main_section'
		);
	}

	/**
	 * Sanitize settings input.
	 */
	public static function sanitize_settings( $input ) {
		$existing = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$out       = $existing;
		$has_error = false;

		// Webhook validation
		if ( isset( $input['webhook'] ) ) {
			$raw_webhook = trim( (string) $input['webhook'] );
			if ( empty( $raw_webhook ) ) {
				add_settings_error( self::OPTION_NAME, 'webhook_empty', __( 'Slack Webhook URL cannot be empty.', 'error-logger' ), 'error' );
				$has_error   = true;
				$raw_webhook = $existing['webhook'] ?? '';
			} elseif ( strpos( $raw_webhook, 'https://hooks.slack.com/services/' ) !== 0 ) {
				add_settings_error( self::OPTION_NAME, 'webhook_invalid', __( 'Invalid Slack Webhook URL. It must start with https://hooks.slack.com/services/.', 'error-logger' ), 'error' );
				$has_error   = true;
				$raw_webhook = $existing['webhook'] ?? '';
			} else {
				$raw_webhook = esc_url_raw( $raw_webhook );
			}
			$out['webhook'] = $raw_webhook;
		}

		// Global fields
		if ( array_key_exists( 'global_fields', $input ) ) {
			$gf    = trim( (string) $input['global_fields'] );
			$parts = array_filter( array_map( 'trim', explode( ',', $gf ) ) );
			$out['global_fields'] = implode( ',', $parts );
		} else {
			$out['global_fields'] = $existing['global_fields'] ?? 'name,email';
		}

		// Per-form fields
		$forms_in     = $input['forms'] ?? array();
		$stored_forms = $out['forms'] ?? array();

		foreach ( $forms_in as $form_name => $form_data ) {
			$fields_val = '';
			if ( isset( $form_data['fields'] ) ) {
				$fields_val = trim( (string) $form_data['fields'] );
				$parts      = array_filter( array_map( 'trim', explode( ',', $fields_val ) ) );
				$fields_val = implode( ',', $parts );
				if ( empty( $fields_val ) && isset( $stored_forms[ $form_name ]['fields'] ) ) {
					$fields_val = $stored_forms[ $form_name ]['fields'];
				}
			} elseif ( isset( $stored_forms[ $form_name ]['fields'] ) ) {
				$fields_val = $stored_forms[ $form_name ]['fields'];
			}
			$stored_forms[ $form_name ] = array( 'fields' => $fields_val );
		}

		$out['forms'] = $stored_forms;

		if ( ! $has_error ) {
			add_settings_error( self::OPTION_NAME, 'settings_saved', __( 'Settings saved.', 'error-logger' ), 'updated' );
		}

		return $out;
	}

	/**
	 * Webhook input field.
	 */
	public static function webhook_field_callback() {
		$opts = get_option( self::OPTION_NAME, array() );
		$val  = esc_url( $opts['webhook'] ?? '' );
		echo '<input type="url" name="' . self::OPTION_NAME . '[webhook]" value="' . esc_attr( $val ) . '" class="large-text code" style="width:100%;" placeholder="https://hooks.slack.com/services/...">';
		echo '<p class="description">' . __(
            'Go to <a href="https://api.slack.com/apps" target="_blank">https://api.slack.com/apps</a> and <strong>Create New App → From scratch</strong>. 
            Name the app and choose your workspace. In the left menu pick <strong>Incoming Webhooks</strong> → toggle <strong>Activate Incoming Webhooks</strong> ON. 
            Click <strong>Add New Webhook to Workspace</strong>, choose the channel, click <strong>Allow</strong>. Copy the generated webhook URL (it starts with https://hooks.slack.com/services/...). Paste it above.',
            'error-logger'
        ) . '</p>';
	}

	/**
	 * Global fields textarea.
	 */
	public static function fields_field_callback() {
		$opts = get_option( self::OPTION_NAME, array() );
		$val  = $opts['global_fields'] ?? 'name,email';
		if ( is_array( $val ) ) {
			$val = implode( ',', $val );
		}
		echo '<textarea name="' . self::OPTION_NAME . '[global_fields]" rows="3" class="large-text code" style="width:100%;">' . esc_textarea( $val ) . '</textarea>';
		echo '<p class="description">Global fallback field keys (comma-separated). Individual forms can override these.</p>';
	}

	/**
	 * Render Global Settings page.
	 */
	public static function render_global_settings_page() {
		$plugin_data = get_file_data( WP_PLUGIN_DIR . '/error-logger/error-logger.php', array( 'Version' => 'Version' ), 'plugin' );
		$version     = isset( $plugin_data['Version'] ) ? 'v' . esc_html( $plugin_data['Version'] ) : '';

		echo '<div class="wrap">';
		echo '<h1 style="display:flex; justify-content:space-between; align-items:center;">Error Logger – Global Settings ' . $version;
		echo '<a href="https://docs.google.com/document/d/16EqirMtP8nXQ5ClYZzCPHcXpA6cRSEk4z8B3gVdKzUU/edit?usp=sharing" target="_blank" class="button button-secondary" style="margin-left:auto;">Documentation</a></h1>';

		settings_errors( self::OPTION_NAME );

		echo '<form method="post" action="options.php">';
		settings_fields( self::SETTINGS_GROUP );
		do_settings_sections( self::PAGE_SLUG_MAIN );
		submit_button( 'Save Global Settings' );
		echo '</form></div>';

		// global notice fade
		echo '<script>
			document.addEventListener("DOMContentLoaded",function(){
				setTimeout(function(){
					document.querySelectorAll(".notice").forEach(el=>{
						el.style.transition="opacity .8s, max-height .6s";
						el.style.opacity="0";
						el.style.maxHeight="0";
						setTimeout(()=>el.remove(),1000);
					});
				},4000);
			});
		</script>';
	}

	/**
	 * Render Form Config page.
	 */
	public static function render_forms_page() {
		$opts          = get_option( self::OPTION_NAME, array() );
		$forms         = self::get_forms_list();
		$selected_form = isset( $_GET['form_name'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['form_name'] ) ) ) : '';

		$cleanup_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=error_logger_cleanup_forms' ),
			'error_logger_cleanup_forms_action'
		);

		echo '<div class="wrap">';
		echo '<h1 style="display:flex; justify-content:space-between; align-items:center;">Form Config';
		echo '<a href="' . esc_url( $cleanup_url ) . '" class="button button-secondary" style="margin-left:auto;" onclick="return confirm(\'Clean up stale or renamed forms?\');">🧹 Clean Up Stale Forms</a>';
		echo '</h1>';

		if ( isset( $_GET['cleanup_done'] ) ) {
			echo '<div class="notice notice-success" id="error-logger-cleanup-msg"><p>✅ Clean up complete — stale or renamed forms removed.</p></div>';
		}

		settings_errors( self::OPTION_NAME );

		if ( empty( $forms ) ) {
			echo '<p>No Elementor forms found. Submit a form once to populate this list.</p></div>';
			return;
		}

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG_FORMS ) . '">';
		echo '<label for="Error_Logger_form_select">Select Form:</label> ';
		echo '<select id="Error_Logger_form_select" name="form_name" style="min-width:350px;margin-left:8px;">';
		echo '<option value="">-- choose a form --</option>';
		foreach ( $forms as $f ) {
			$pretty = Error_Logger_Helper::format_field_label( $f );
			printf( '<option value="%s" %s>%s</option>', esc_attr( rawurlencode( $f ) ), selected( $selected_form, $f, false ), esc_html( $pretty ) );
		}
		echo '</select> ';
		submit_button( 'Load Form', 'secondary', '', false );
		echo '</form>';

		if ( $selected_form !== '' ) {
			$stored_fields = $opts['forms'][ $selected_form ]['fields'] ?? '';
			echo '<hr/><h3>' . esc_html( Error_Logger_Helper::format_field_label( $selected_form ) ) . '</h3>';
			echo '<form method="post" action="options.php">';
			settings_fields( self::SETTINGS_GROUP );
			echo '<p><label for="Error_Logger_form_fields">Fields to Send for this form (comma-separated):</label><br>';
			echo '<textarea id="Error_Logger_form_fields" name="' . self::OPTION_NAME . '[forms][' . esc_attr( $selected_form ) . '][fields]" rows="4" class="large-text code" style="width:100%;">' . esc_textarea( $stored_fields ) . '</textarea></p>';
			submit_button( 'Save Form Settings' );
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Handle stale forms cleanup.
	 */
	public static function handle_cleanup_forms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'error_logger_cleanup_forms_action' );

		$opts           = get_option( self::OPTION_NAME, array() );
		$existing_forms = $opts['forms'] ?? array();
		$current_forms  = self::get_forms_list();

		$normalized_current = array_map( 'strtolower', array_map( 'trim', $current_forms ) );

		foreach ( array_keys( $existing_forms ) as $saved_form ) {
			if ( ! in_array( strtolower( trim( $saved_form ) ), $normalized_current, true ) ) {
				unset( $existing_forms[ $saved_form ] );
			}
		}

		$opts['forms'] = $existing_forms;
		update_option( self::OPTION_NAME, $opts );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG_FORMS . '&cleanup_done=1' ) );
		exit;
	}

	/**
	 * Get all Elementor forms currently existing.
	 */
	protected static function get_forms_list() {
		global $wpdb;
		$table  = $wpdb->prefix . 'e_submissions';
		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return array();
		}
		$rows = $wpdb->get_results( "SELECT DISTINCT form_name FROM {$table} WHERE form_name IS NOT NULL AND form_name <> '' ORDER BY form_name ASC", ARRAY_A );
		$forms = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $r['form_name'] ) ) {
				$forms[] = trim( $r['form_name'] );
			}
		}
		return $forms;
	}
}

// Register cleanup action.
add_action( 'admin_post_error_logger_cleanup_forms', array( 'Error_Logger_Admin', 'handle_cleanup_forms' ) );
