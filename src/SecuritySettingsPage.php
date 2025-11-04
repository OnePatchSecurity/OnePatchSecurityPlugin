<?php
/**
 * Class SecuritySettingsPage
 *
 * A Class for providing an admin interface to toggle the various options.
 *
 * @package SecuritySettingsPage
 *
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace OnePatch;

/**
 * Handles the security settings administration interface for OnePatch Security.
 *
 * This class provides a complete WordPress admin interface for managing security settings,
 * including user enumeration prevention, login attempt limitations, REST API restrictions,
 * and other security hardening options. The settings page is accessible to administrators
 * under Settings → Security in the WordPress dashboard.
 *
 * Key features:
 * - Creates and manages a dedicated security settings page
 * - Handles secure saving of settings with nonce verification
 * - Provides detailed tooltips explaining each security option
 * - Groups related settings into logical sections
 * - Enqueues necessary admin assets (CSS/JS)
 *
 * The class follows WordPress security best practices including:
 * - Nonce verification for all form submissions
 * - Proper capability checks
 * - Secure option storage
 * - Escaped output
 *
 * @package OnePatch
 * @since   1.0.0
 */
class SecuritySettingsPage {
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the Security Settings page to the WordPress admin under the "Settings" menu.
	 *
	 * This method registers an options page in the WordPress admin menu.
	 * The page is titled "Security Settings" and allows users with the
	 * 'manage_options' capability to configure security-related settings.
	 *
	 * The page will be accessible via Settings > Security in the WordPress admin dashboard.
	 * The page callback renders the actual content of the options page via the `render_options_page` method.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_options_page(): void {
		add_options_page(
			'Security Settings',
			'Security',
			'manage_options',
			'security-settings',
			array( $this, 'render_options_page' )
		);
	}

	/**
	 * Registers the security settings for the Security Settings page.
	 *
	 * This method registers the 'security_settings' option under the 'security_settings_group'
	 * settings group in WordPress. This allows the plugin to store and manage the security-related
	 * settings that users configure on the Security Settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
                'security_settings_group',
                'security_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'sanitize_security_settings_array',
				'default'           => array(),
			)
        );
	}

	/**
	 * Sanitize security settings array - convert all values to booleans
	 *
	 * @param array $input The unsanitized input array.
	 * @return array The sanitized array with boolean values.
	 */
	 public function sanitize_security_settings_array( array $input ): array {
		$sanitized = array();

		foreach ( $input as $key => $value ) {
			// Sanitize the key and ensure the value is boolean
			$sanitized_key = sanitize_key( $key );
			$sanitized[ $sanitized_key ] = (bool) $value;
		}

		return $sanitized;
	}


	/**
	 * Enqueue the assets for the security settings page.
	 *
	 * This method is responsible for loading the necessary CSS styles
	 * for the security settings page in the WordPress admin area.
	 * It ensures that the custom styles are applied when the page
	 * is rendered, enhancing the user interface and experience.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'security-settings-style',
			ONE_PATCH_CSS_URL . 'styles.min.css',
			array(),
			ONE_PATCH_VERSION
		);
		wp_enqueue_script(
			'custom-script',
			ONE_PATCH_JS_URL . 'script.min.js',
			array( 'jquery' ),
			ONE_PATCH_VERSION,
			true
		);
	}

	/**
	 * Renders the security settings options page.
	 *
	 * The checkboxes correspond to security features, and their checked state reflects the current
	 * settings stored in the 'security_settings' option.
	 * The form is styled using the `.security-settings-list and `.security-setting-item` classes.
	 *
	 * @since 1.0.0
	 */
	public function render_options_page(): void {
		if ( isset( $_POST['submit'] ) ) {
			if ( ! isset( $_POST['security_settings_nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security_settings_nonce'] ) ), 'security_settings_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'one-patch-security' ) );
			}

			$settings = array(
				'remove_wp_version_meta'            => isset( $_POST['security_settings-remove_wp_version_meta'] ) ? 1 : 0,
				'custom_login_error_message'        => isset( $_POST['security_settings-custom_login_error_message'] ) ? 1 : 0,
				'limit_login_attempts'              => isset( $_POST['security_settings-limit_login_attempts'] ) ? 1 : 0,
				'prevent_user_enum_via_query_param' => isset( $_POST['security_settings-prevent_user_enum_via_query_param'] ) ? 1 : 0,
				'prevent_user_enum_via_template'    => isset( $_POST['security_settings-prevent_user_enum_via_template'] ) ? 1 : 0,
				'boot_non_logged_users_from_rest'   => isset( $_POST['security_settings-boot_non_logged_users_from_rest'] ) ? 1 : 0,
				'block_specific_endpoints'          => isset( $_POST['security_settings-block_specific_endpoints'] ) ? 1 : 0,
				'force_secure_cookies'              => isset( $_POST['security_settings-force_secure_cookies'] ) ? 1 : 0,
				'disable_xmlrpc'                    => isset( $_POST['security_settings-disable_xmlrpc'] ) ? 1 : 0,
			);
			update_option( 'security_settings', $settings );

			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		$settings = get_option( 'security_settings', array() );

		?>
		<div class="wrap">
			<h1>One Patch Security Settings</h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'security_settings_nonce', 'security_settings_nonce' ); ?>

				<!-- more info box that all tooltips use -->
				<div id="custom-tooltip-info" class="hidden">
					<p id="custom-tooltip-text"></p>
					<button id="close-tooltip-info" aria-label="Close">×</button>
				</div>

				<div class="security-settings-list">

					<!-- General Security Settings -->
					<h3>General</h3>
					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-remove_wp_version_meta" value="1" <?php checked( $settings['remove_wp_version_meta'] ?? 0, 1 ); ?> />
							<span>Remove WordPress version metadata</span>
							<button class="tooltip" aria-describedby="tooltip-1">?
								<span class="tooltip-content" id="tooltip-1">
								Hides the WordPress version number from your site's metadata. This prevents attackers from targeting known vulnerabilities in specific versions of WordPress. <br/>
								</span>
							</button>
						</label>
					</div>

					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-disable_xmlrpc" value="1" <?php checked( $settings['disable_xmlrpc'] ?? 0, 1 ); ?> />
							<span>Disable XML-RPC Methods</span>
							<button class="tooltip" aria-describedby="tooltip-2">?
								<span class="tooltip-content" id="tooltip-2">
								Disables XML-RPC, a feature that can be exploited for brute force attacks and DDoS attacks. If you don't use remote publishing or pingbacks, it's safe to disable this. <br/>
								</span>
							</button>
						</label>
					</div>

					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-custom_login_error_message" value="1" <?php checked( $settings['custom_login_error_message'] ?? 0, 1 ); ?> />
							<span>Anonymize Login Failure Message</span>
							<button class="tooltip" aria-describedby="tooltip-3">?
								<span class="tooltip-content" id="tooltip-3">
								Hides whether a username or password is incorrect during login attempts. This makes it harder for attackers to guess valid usernames. <br/>
								</span>
							</button>
						</label>
					</div>

					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-limit_login_attempts" value="1" <?php checked( $settings['limit_login_attempts'] ?? 0, 1 ); ?> />
							<span>Limit Login Attempts</span>
							<button class="tooltip" aria-describedby="tooltip-4">?
								<span class="tooltip-content" id="tooltip-4">
								Restricts the number of login attempts to prevent brute force attacks. After three failed attempts, the user will be temporarily locked out. <br/>
								</span>
							</button>
						</label>
					</div>

					<!-- User Enumeration Protection -->
					<h3>User Enumeration</h3>
					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-prevent_user_enum_via_template" value="1" <?php checked( $settings['prevent_user_enum_via_template'] ?? 0, 1 ); ?> />
							<span>Prevent User Enumeration Via Author Template</span>
							<button class="tooltip" aria-describedby="tooltip-6">?
								<span class="tooltip-content" id="tooltip-6">
								Stops attackers from finding usernames through author archive pages. This adds an extra layer of security to your site. <br/><br/>
                                <strong>Warning: </strong>This disables author archive pages, which may affect theme features or author-specific content.
								</span>
							</button>
						</label>
					</div>

					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-prevent_user_enum_via_query_param" value="1" <?php checked( $settings['prevent_user_enum_via_query_param'] ?? 0, 1 ); ?> />
							<span>Prevent User Enumeration Via Query Parameter</span>
							<button class="tooltip" aria-describedby="tooltip-5">?
								<span class="tooltip-content" id="tooltip-5">
								Prevents attackers from discovering usernames by blocking access to user lists. This makes it harder for them to target specific accounts. <br/><br/>
                                    <strong>Warning: </strong>This blocks <code>?author=</code> queries, which may interfere with plugins or features that use user IDs.
								</span>
							</button>
						</label>
					</div>

					<!-- REST API Restrictions -->
					<h3>REST API</h3>
					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-boot_non_logged_users_from_rest" value="1" <?php checked( $settings['boot_non_logged_users_from_rest'] ?? 0, 1 ); ?> />
							<span>Prevent Non-logged-in users from accessing REST API Endpoints</span>
							<button class="tooltip" aria-describedby="tooltip-8">?
								<span class="tooltip-content" id="tooltip-8">
								Restricts access to the WordPress REST API for non-logged-in users. This prevents unauthorized access to sensitive data. <br/><br/>
                                <strong>Warning: </strong>Blocking public REST API access may break features or plugins that rely on public endpoints.
								</span>
							</button>
						</label>
					</div>

					<div class="security-setting-item">
						<label>
							<input type="checkbox" name="security_settings-block_specific_endpoints" value="1" <?php checked( $settings['block_specific_endpoints'] ?? 0, 1 ); ?> />
							<span>Remove 'Users' and 'Plugins' Endpoints for all Users</span>
							<button class="tooltip" aria-describedby="tooltip-9">?
								<span class="tooltip-content" id="tooltip-9">
								Hides the 'Users' and 'Plugins' endpoints in the REST API. This prevents attackers from gathering information about your site's users and plugins. <br/><br/>
                                <strong>Warning: </strong>Disabling these endpoints improves privacy but can impact functionality in themes or plugins that rely on them.
								</span>
							</button>
						</label>
					</div>

				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<div class="promotion">
			<h3>Not sure what settings you need?</h3>
			<p>Use our <a href="https://github.com/OnePatchSecurity/OnePatchPenTestingTool" target="_blank">security testing tool</a> to find out where your site is vulnerable.</p>
		</div>
		<?php
	}
}
