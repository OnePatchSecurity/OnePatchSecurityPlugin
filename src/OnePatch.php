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

		add_action( 'init', array( $this, 'remove_wp_version_meta' ) ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.
		add_filter( 'init', array( $this, 'disable_xmlrpc' ), PHP_INT_MAX ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.
		add_filter( 'wp_login_errors', array( $this, 'custom_login_error_message' ) ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.

		add_action( 'init', array( $this, 'prevent_user_enum_via_query_param' ) ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.
		add_action( 'template_redirect', array( $this, 'prevent_user_enum_via_template' ) ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.

		// REST API endpoint tightening methods.
		add_filter( 'rest_authentication_errors', array( $this, 'boot_non_logged_users_from_rest' ) ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.
		add_filter( 'rest_endpoints', array( $this, 'block_specific_endpoints' ) ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.

		// Limit login attempts TODO this still doesn't work, but is less broken. Fix.
		add_filter( 'authenticate', array( $this, 'handle_login_attempts_and_lockout' ), 30, 3 ); // tested and implemented in the settings page -- BEFORE CODE REVIEW.
		add_action( 'login_enqueue_scripts', array( $this, 'hide_login_box_if_locked_out' ) );
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
		if ( empty( $this->settings['limit_login_attempts'] ) ) {
			return $user;
		}

		if ( empty( $username ) ) {
			return $user;
		}

		$max_attempts     = 3;
		$lockout_duration = 30;
		$transient_prefix = 'login_attempts_';

		// Check if the user is already locked out.
		$lockout_time = get_transient( 'lockout_' . $username );

		if ( $lockout_time ) {
			// Set a cookie to indicate the user is locked out.
			setcookie( 'login_lockout_' . $username, '1', time() + $lockout_duration * MINUTE_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

			return new WP_Error(
				'too_many_attempts',
				sprintf(
				/* translators: %d: Number of minutes until login retry is allowed */
					__( 'Too many failed login attempts. Please try again in %d minutes.', 'your-text-domain' ),
					ceil( ( $lockout_time + $lockout_duration * MINUTE_IN_SECONDS - time() ) / 60 )
				)
			);
		}

		// Track login attempts.
		$attempts = get_transient( $transient_prefix . $username ) ? get_transient( $transient_prefix . $username ) : 0;

		// If max attempts reached, set lockout.
		if ( $attempts >= $max_attempts ) {
			set_transient( 'lockout_' . $username, time(), $lockout_duration * MINUTE_IN_SECONDS );
			setcookie( 'login_lockout_' . $username, '1', time() + $lockout_duration * MINUTE_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

			return new WP_Error(
				'too_many_attempts',
				sprintf(
					/* translators: %d: Number of minutes until login retry is allowed */
					__( 'Too many failed login attempts. Please try again in %d minutes.', 'your-text-domain' ),
					$lockout_duration
				)
			);
		}

		// If credentials are invalid, increment the attempt counter.
		if ( is_wp_error( $user ) ) {
			$attempts++;
			set_transient( $transient_prefix . $username, $attempts, 10 * MINUTE_IN_SECONDS );

			// If this was the final attempt, set the lockout.
			if ( $attempts >= $max_attempts ) {
				set_transient( 'lockout_' . $username, time(), $lockout_duration * MINUTE_IN_SECONDS );
				setcookie( 'login_lockout_' . $username, '1', time() + $lockout_duration * MINUTE_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );

				return new WP_Error(
					'too_many_attempts',
					sprintf(
						/* translators: %d: Number of minutes until login retry is allowed */
						__( 'Too many failed login attempts. Please try again in %d minutes.', 'your-text-domain' ),
						$lockout_duration
					)
				);
			}

			// Otherwise, return the invalid credential error.
			return new WP_Error(
				'invalid_credentials',
				__( 'Invalid login credentials. Please try again.', 'your-text-domain' )
			);
		}

		// If credentials are valid, reset the attempt counter.
		delete_transient( $transient_prefix . $username );
		setcookie( 'login_lockout_' . $username, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );

		return $user;
	}

	/**
	 * Adds JavaScript to hide the login box if the user is locked out.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function hide_login_box_if_locked_out(): void {
		if ( empty( $this->settings['limit_login_attempts'] ) ) {
			return;
		}

		// verify nonce.
		if ( ! isset( $_POST['security_settings_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security_settings_nonce'] ) ), 'security_settings_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'your-text-domain' ) );
		}

		// Check if the user is locked out via the cookie.
		$username = '';
		if ( isset( $_POST['log'] ) ) {
			$username = sanitize_user( wp_unslash( $_POST['log'] ) );
		}
		$lockout_cookie = $username ? isset( $_COOKIE[ 'login_lockout_' . $username ] ) : false;

		if ( $lockout_cookie ) {
			echo '<style>#loginform { display: none; }</style>';

			$error_message = sprintf(
				/* translators: %d: Number of minutes until login retry is allowed */
				__( 'Too many failed login attempts. Please try again in %d minutes.', 'your-text-domain' ),
				30
			);
			wp_enqueue_script( 'jquery' );
			wp_add_inline_script(
				'jquery',
				sprintf(
					'jQuery(function($) {
                            $("body").on("login_message", function(ev, loginMessage) {
                            if ($("#login-message").length) {
                            $("#login-message").html(%s);
                        }
                      });
                     });',
					wp_json_encode(
						sprintf(
							'<p class="message error">%s</p>',
							esc_html( $error_message )
						)
					)
				)
			);
		}
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
	 * Exiting on xmlrpc_methods is a bit aggressive, but it's the best way to get a totally empty response
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function disable_xmlrpc(): void {
		if ( empty( $this->settings['disable_xmlrpc'] ) ) {
			return;
		}

		add_filter( 'xmlrpc_enabled', '__return_false' );

		add_filter(
			'xmlrpc_methods',
			function () {
				exit;
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
			$errors->errors[ $code ] = array( __( 'Invalid login credentials. Please try again.', 'textdomain' ) );
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
}
