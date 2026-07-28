<?php
/**
 * Plugin Name:       InstaStudio
 * Plugin URI:        https://github.com/InstaWP/studio
 * Description:       Source-rendered WordPress — serve plain HTML files from a source directory as real pages (no page builder, no block editor, no build step), with in-place visual editing. The InstaStudio engine, works alongside any theme.
 * Version:           1.0.0
 * Author:            InstaWP
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       iwp-studio
 *
 * The engine used to live in a theme; it's a plugin so the capability rides on any
 * theme. Pair it with a minimal companion theme (themes/iwp-studio) or your own.
 */

defined( 'ABSPATH' ) || exit;

define( 'IWPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'IWPS_URL', plugin_dir_url( __FILE__ ) );

// Source directory of your HTML pages (the source of truth). Default lives INSIDE
// wp-content (`wp-content/site/`) so the source MIGRATES with the site — host-to-host
// and sandbox->production carry wp-content, but not always arbitrary webroot dirs.
// Override BOTH in wp-config.php to point elsewhere.
if ( ! defined( 'INSTAWP_HB_DIR' ) ) {
	// Honor the legacy webroot location (`<wp-root>/site/`) if the source already
	// lives there; otherwise default to the migration-safe wp-content location.
	if ( ! is_dir( WP_CONTENT_DIR . '/site' ) && is_dir( ABSPATH . 'site' ) ) {
		define( 'INSTAWP_HB_DIR', ABSPATH . 'site/' );
	} else {
		define( 'INSTAWP_HB_DIR', WP_CONTENT_DIR . '/site/' );
	}
}
if ( ! defined( 'INSTAWP_HB_URL' ) ) {
	// Derive the matching URL from the resolved dir (content_url under wp-content,
	// else home_url under the webroot).
	$iwps_dir = untrailingslashit( INSTAWP_HB_DIR );
	if ( 0 === strpos( $iwps_dir, untrailingslashit( WP_CONTENT_DIR ) ) ) {
		define( 'INSTAWP_HB_URL', content_url( substr( $iwps_dir, strlen( untrailingslashit( WP_CONTENT_DIR ) ) ) ) . '/' );
	} else {
		define( 'INSTAWP_HB_URL', home_url( substr( $iwps_dir, strlen( untrailingslashit( ABSPATH ) ) ) ) . '/' );
	}
	unset( $iwps_dir );
}

require_once IWPS_DIR . 'includes/render.php';   // the source-rendered engine
require_once IWPS_DIR . 'includes/editor.php';   // Edit in Place
require_once IWPS_DIR . 'includes/cli.php';      // wp instastudio pages

register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
