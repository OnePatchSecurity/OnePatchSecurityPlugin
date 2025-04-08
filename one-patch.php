<?php
/**
 * Plugin Name:       One Patch Security
 * Plugin URI:        https://1patchsecurity.com/tools
 * Description:       A lightweight solution for common WordPress security vulnerabilities.
 * Version:           1.0.0
 * Author:            1 Patch Security
 * Author URI:        https://1patchsecurity.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html#SEC1
 *
 * @package           1Patch
 * @since             1.0.0
 */

// Bail if accessed directly.
if ( ! defined( 'ABPLUGIN_PATH' ) ) {
	define( 'ABPLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

// Load Composer autoloader.
if ( file_exists( ABPLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once ABPLUGIN_PATH . 'vendor/autoload.php';
}

// Define plugin URLs.
if ( ! defined( 'ONE_PATCH_URL' ) ) {
	define( 'ONE_PATCH_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'ONE_PATCH_ASSETS_URL' ) ) {
	define( 'ONE_PATCH_ASSETS_URL', ONE_PATCH_URL . 'client/' );
}
if ( ! defined( 'ONE_PATCH_CSS_URL' ) ) {
	define( 'ONE_PATCH_CSS_URL', ONE_PATCH_ASSETS_URL . 'css/' );
}
if ( ! defined( 'ONE_PATCH_JS_URL' ) ) {
	define( 'ONE_PATCH_JS_URL', ONE_PATCH_ASSETS_URL . 'js/' );
}
if ( ! defined( 'ONE_PATCH_VERSION' ) ) {
	define( 'ONE_PATCH_VERSION', '1.0.0' );
}

/**
 * Initialize the plugin
 *
 * @return void
 *
 * @since 1.0.0
 */
function init_one_patch(): void {
	new \OnePatch\OnePatch();
	new \OnePatch\SecuritySettingsPage();
}
add_action( 'plugins_loaded', 'init_one_patch' );
