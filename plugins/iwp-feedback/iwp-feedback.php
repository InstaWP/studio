<?php
/**
 * Plugin Name:       InstaWP Feedback
 * Plugin URI:        https://instawp.com/
 * Description:       Lightweight front-end feedback for the team. A floating widget lets logged-in team members drop a pin on the exact spot of any page and leave a note. TEAM ONLY: the public never sees the widget or the pins, and cannot read/post via REST (every endpoint is login-gated). Reviewed in wp-admin -> Feedback. No page builder, no framework.
 * Version:           1.0.0
 * Author:            InstaWP
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * License:           GPL-2.0-or-later
 * Text Domain:       instawp-feedback
 *
 * Pieces:
 *   - gate   : who sees the widget (logged-in OR the ?feedback=on cookie). Team-only by default.
 *   - cpt    : `feedback` post type (private, admin-only UI) — one post per note.
 *   - rest   : instawp-feedback/v1/submit  (create a note, public+gated+rate-limited)
 *              instawp-feedback/v1/list    (pins for the current page, gated)
 *   - assets : the floating widget (assets/feedback.js + .css), enqueued only when gated-in.
 *   - admin  : columns / filters / a details meta box / a "new" count bubble in the menu.
 *   - cli    : wp iwpfb sample|clear|flush
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IWPFB_VERSION', '1.0.0' );
define( 'IWPFB_FILE', __FILE__ );
define( 'IWPFB_DIR', plugin_dir_path( __FILE__ ) );
define( 'IWPFB_URL', plugin_dir_url( __FILE__ ) );
define( 'IWPFB_PT', 'feedback' );          // post type key
define( 'IWPFB_HIDE_COOKIE', 'iwpfb_off' ); // per-browser "hide the widget" cookie

require_once IWPFB_DIR . 'includes/meta.php';
require_once IWPFB_DIR . 'includes/gate.php';
require_once IWPFB_DIR . 'includes/cpt.php';
require_once IWPFB_DIR . 'includes/rest.php';
require_once IWPFB_DIR . 'includes/exchange-core.php'; // export/import data layer (shared with CLI)
require_once IWPFB_DIR . 'includes/assets.php';
require_once IWPFB_DIR . 'includes/cli.php';

if ( is_admin() ) {
	require_once IWPFB_DIR . 'includes/admin.php';
	require_once IWPFB_DIR . 'includes/exchange.php'; // admin UI for export/import
}

register_activation_hook( __FILE__, function () {
	iwpfb_register_post_types();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
