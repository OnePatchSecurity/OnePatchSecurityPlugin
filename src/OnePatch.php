<?php
/**
 * Class OnePatch
 *
 * A class for implementing security measures on a WordPress site.
 * We hook into the WordPress lifecycle to trigger specific security actions.
 *
 * Each method first checks to see if it should run based on the settings options. If not, return early.
 *
 * @package OnePatch
 *
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace OnePatch;

use WP_Error;
use WP_User;

/**
 * Main security class for OnePatch plugin.
 *
 * Handles various WordPress security enhancements including:
 * - Login attempt limiting and lockout functionality
 * - XML-RPC disabling
 * - WordPress version hiding
 * - REST API security hardening
 * - User enumeration prevention
 * - Login error message obfuscation
 *
 * All security features are configurable through plugin settings.
 * Each method checks the settings before execution to allow selective feature activation.
 *
 * @package OnePatch
 * @since   1.0.0
 */
class OnePatch {
	/**
	 * Security Settings from options.
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 */
	private array $settings;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->settings = get_option( 'security_settings', array() );
		add_action( 'init', array( $this, 'remove_wp_version_meta' ) );
		add_filter( 'init', array( $this, 'disable_xmlrpc' ), PHP_INT_MAX );
		add_filter( 'wp_login_errors', array( $this, 'custom_login_error_message' ) );

		add_action( 'init', array( $this, 'prevent_user_enum_via_query_param' ) );
		add_action( 'template_redirect', array( $this, 'prevent_user_enum_via_template' ) );

		add_filter( 'rest_authentication_errors', array( $this, 'boot_non_logged_users_from_rest' ) );
		add_filter( 'rest_endpoints', array( $this, 'block_specific_endpoints' ) );

		add_filter( 'authenticate', array( $this, 'handle_login_attempts_and_lockout' ), 30, 3 );
		add_action( 'login_form', array( $this, 'add_login_nonce' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'hide_login_box_if_locked_out' ) );
	}

	/**
	 * Remove WordPress version from the metadata.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function remove_wp_version_meta(): void {
		if ( empty( $this->settings['remove_wp_version_meta'] ) ) {
			return;
		}

		remove_action( 'wp_head', 'wp_generator' );
	}

	/**
	 * Disable XML-RPC in WordPress.
	 *
	 * Throwing a 403 on xmlrpc_methods is a bit aggressive, but it sends a message.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function disable_xmlrpc(): void {
		if ( empty( $this->settings['disable_xmlrpc'] ) ) {
			return;
		}

		add_filter( 'xmlrpc_enabled', '__return_false' );

		add_action(
			'xmlrpc_enabled',
			function() {
				status_header( 403 );
				wp_die( 'XML_RPC services are disabled on this application.', 'Forbidden', array( 'response' => 403 ) );
			}
		);
	}

	/**
	 * Customizes the login error message to prevent user information leaks.
	 *
	 * @param WP_Error $errors The existing WP_Error object containing login error messages.
	 *
	 * @return WP_Error The modified WP_Error object with custom error messages.
	 *
	 * @since 1.0.0
	 */
	public function custom_login_error_message( WP_Error $errors ): WP_Error {
		if ( empty( $this->settings['custom_login_error_message'] ) ) {
			return $errors;
		}

		foreach ( $errors->errors as $code => $messages ) {
			$errors->errors[ $code ] = array( __( 'Invalid login credentials. Please try again.', 'one-patch-security' ) );
		}

		return $errors;
	}

	/**
	 * Prevents user enumeration via the 'author' query parameter.
	 *
	 * If the 'author' query parameter is set in the URL, this function redirects
	 * the user to the homepage to prevent potential information disclosure
	 * regarding author accounts.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function prevent_user_enum_via_query_param(): void {
		if ( empty( $this->settings['prevent_user_enum_via_query_param'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['author'] ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Prevents user enumeration by redirecting author archive pages.
	 *
	 * If the current request is for an author archive page, this method
	 * redirects the user to the homepage to prevent potential information
	 * disclosure regarding author accounts.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function prevent_user_enum_via_template(): void {
		if ( empty( $this->settings['prevent_user_enum_via_template'] ) ) {
			return;
		}

		if ( is_author() ) {
			wp_safe_redirect( home_url(), 301 );
			exit;
		}
	}

	/**
	 * Disable access to the REST API to non-logged in users.
	 *
	 * Throw error message to non-logged in users who try to hit the REST API
	 *
	 * @param mixed $result The result of the previous callback or action that this filter applies to.
	 *
	 * @return mixed|WP_Error Returns a WP_Error if the user is not logged in, otherwise returns the original result.
	 *
	 * @since 1.0.0
	 */
	public function boot_non_logged_users_from_rest( mixed $result ): mixed {
		if ( empty( $this->settings['boot_non_logged_users_from_rest'] ) ) {
			return $result;
		}

		if ( ! is_user_logged_in() && ! is_admin() ) {
			return new WP_Error( 'rest_not_logged_in', 'No Cookies, no entry. Authenticate first.', array( 'status' => 401 ) );
		}
		return $result;
	}

	/**
	 * Remove user and plugin endpoints from REST API.
	 *
	 * REST endpoints are already blocked from non-logged in users. See boot_non_logged_users() above.
	 * This explicitly unsets the plugin and user endpoints altogether, which I don't use and have security implications.
	 *
	 * @param array $endpoints REST endpoints to be filtered.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function block_specific_endpoints( array $endpoints ): array {
		if ( empty( $this->settings['block_specific_endpoints'] ) ) {
			return $endpoints;
		}

		if ( isset( $endpoints['/wp/v2/users'] ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}
		if ( isset( $endpoints['/wp/v2/plugins'] ) ) {
			unset( $endpoints['/wp/v2/plugins'] );
		}
		return $endpoints;
	}

	/**
	 * Tracks failed login attempts.
	 * Sets one transient - login_attempts_<username> - for the number of failed attempts made.
	 * Sets an additional transient - lockout_<username> - with a 30m expiration.
	 * Lockout transient is used to block additional login attempts from that user.
	 *
	 * @param WP_User|WP_Error|null $user WP_User object or WP_Error.
	 * @param string|null           $username Username used in the failed login attempt.
	 * @param string|null           $password Password attempted.
	 *
	 * @return WP_User|WP_Error|null
	 *
	 * @since 1.0.0
	 */
	public function handle_login_attempts_and_lockout( WP_User|WP_Error|null $user, string|null $username, string|null $password ): WP_User|WP_Error|null {
		if ( empty( $this->settings['limit_login_attempts'] ) || empty( $username ) ) {
			return $user;
		}

		$max_attempts        = 3;
		$lockout_duration    = 30 * MINUTE_IN_SECONDS;
		$attempts_expiration = 60 * MINUTE_IN_SECONDS;
		$transient_prefix    = 'login_attempts_';
		$lockout_key         = 'lockout_' . $username;
		$lockout_cookie      = 'login_lockout_' . $username;

		// Type-safe lockout check.
		$lockout_expires = $this->get_safe_transient( $lockout_key );
		if ( $lockout_expires && time() < $lockout_expires ) {
			$this->set_secure_cookie( $lockout_cookie, '1', $lockout_expires );
			return new WP_Error(
				'too_many_attempts',
				sprintf(
					/* translators: %d: Number of minutes until the user can try logging in again */
					__( 'Too many failed login attempts. Please try again in %d minutes.', 'one-patch-security' ),
					ceil( ( $lockout_expires - time() ) / 60 )
				)
			);
		}

		// Successful login - reset attempts if not locked out.
		if ( ! is_wp_error( $user ) ) {
			delete_transient( $transient_prefix . $username );
			$this->clear_cookie( $lockout_cookie );
			return $user;
		}

		// Process failed attempt.
		$attempts = absint( get_transient( $transient_prefix . $username ) ) + 1;
		set_transient( $transient_prefix . $username, $attempts, $attempts_expiration );

		if ( $attempts >= $max_attempts ) {
			$new_lockout_expires = time() + $lockout_duration;
			set_transient( $lockout_key, $new_lockout_expires, $lockout_duration );
			$this->set_secure_cookie( $lockout_cookie, '1', $new_lockout_expires );

			return new WP_Error(
				'too_many_attempts',
				sprintf(
					/* translators: Too many failed login attempts. Please try again in %d minutes. */
					__( 'Too many failed login attempts. Please try again in %d minutes.', 'one-patch-security' ),
					ceil( $lockout_duration / 60 )
				)
			);
		}

		return $user;
	}

	/**
	 * Get safe transient.
	 *
	 * @param string $key transient key.
	 * @return int the transient value.
	 */
	private function get_safe_transient( string $key ): int {
		$value = get_transient( $key );
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Helper function to set a secure cookie
	 *
	 * @param string $name Cookie name.
	 * @param string $value Cookie value.
	 * @param int    $expires Cookie expiry.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function set_secure_cookie( string $name, string $value, int $expires ): void {
		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Helper function to clear the cookie.
	 *
	 * @param string $name Cookie name.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function clear_cookie( string $name ): void {
		$this->set_secure_cookie( $name, '', time() - YEAR_IN_SECONDS );
	}

	/**
	 * Add a nonce to the login form.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function add_login_nonce(): void {
		wp_nonce_field( 'one_patch_security_login', '_ops_nonce' );
	}

	/**
	 * Add JavaScript to hide logout box if user is logged out.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function hide_login_box_if_locked_out(): void {
		if ( empty( $this->settings['limit_login_attempts'] ) ) {
			return;
		}

		if ( ! isset( $_POST['log'], $_POST['_ops_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ops_nonce'] ) ), 'one_patch_security_login' ) ) {
			return;
		}

		$username        = sanitize_user( wp_unslash( $_POST['log'] ) );
		$lockout_key     = 'lockout_' . $username;
		$lockout_expires = get_transient( $lockout_key );

		if ( ! $lockout_expires ) {
			return;
		}

		$minutes_remaining = max( 1, ceil( ( $lockout_expires - time() ) / 60 ) );

		// Hide all default WordPress login UI.
		echo '<style>
        #loginform,
        .login .message,
        .login #login_error {
            display: none !important;
        }
    </style>';

		// Inject our single error message.
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script(
			'jquery',
			sprintf(
				'jQuery(function($) {
            $("#login").prepend(\'<div class="login_error">%s</div>\');
        });',
				esc_js(
					sprintf(
					// Translators: %d is the number of minutes the user must wait before trying to log in again.
						__( 'Too many failed login attempts. Please try again in %d minutes.', 'one-patch-security' ),
						$minutes_remaining
					)
				)
			)
		);
	}
}
