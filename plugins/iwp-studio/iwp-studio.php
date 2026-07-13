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

// Source directory of your HTML pages (the source of truth). Override BOTH in
// wp-config.php to point elsewhere. Default: <wp-root>/site/.
defined( 'INSTAWP_HB_DIR' ) || define( 'INSTAWP_HB_DIR', ABSPATH . 'site/' );
defined( 'INSTAWP_HB_URL' ) || define( 'INSTAWP_HB_URL', home_url( '/site/' ) );

require_once IWPS_DIR . 'includes/render.php';   // the source-rendered engine
require_once IWPS_DIR . 'includes/editor.php';   // Edit in Place
require_once IWPS_DIR . 'includes/cli.php';      // wp instastudio pages

register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
