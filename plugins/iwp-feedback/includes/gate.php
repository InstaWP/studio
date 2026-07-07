<?php
/**
 * Visibility. TEAM ONLY: the widget, the pins, and the submit/read/reply REST endpoints
 * are available ONLY to logged-in WordPress users. The public never sees the widget and
 * cannot read or post feedback (even by hitting the REST API directly — every endpoint's
 * permission_callback is iwpfb_can_use()). Team members log in to wp-admin, then see the
 * widget on the front end. Logged-in users can still hide it per-browser with
 * ?feedback=off (re-enable with ?feedback=on).
 *
 * This is the safe posture for a live public site. To change who qualifies, override in
 * one line from a mu-plugin / the theme's functions.php, e.g. restrict to editors:
 *     add_filter( 'iwpfb_active',  function () { return current_user_can( 'edit_posts' ); } );
 *     add_filter( 'iwpfb_can_use', function () { return current_user_can( 'edit_posts' ); } );
 * or reopen it to everyone (the old trusted-team model): return true from both.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ?feedback=off hides the widget for this browser; ?feedback=on shows it again. */
function iwpfb_process_gate() {
	if ( is_admin() || ! isset( $_GET['feedback'] ) ) {
		return;
	}
	$v    = sanitize_key( wp_unslash( $_GET['feedback'] ) );
	$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
	$dom  = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

	if ( in_array( $v, array( 'off', '0', 'no', 'false' ), true ) ) {
		setcookie( IWPFB_HIDE_COOKIE, '1', time() + YEAR_IN_SECONDS, $path, $dom, is_ssl(), false );
		$_COOKIE[ IWPFB_HIDE_COOKIE ] = '1';
	} elseif ( in_array( $v, array( 'on', '1', 'yes', 'true' ), true ) ) {
		setcookie( IWPFB_HIDE_COOKIE, '', time() - 3600, $path, $dom, is_ssl(), false );
		unset( $_COOKIE[ IWPFB_HIDE_COOKIE ] );
	}
}
add_action( 'init', 'iwpfb_process_gate' );

/**
 * Should the widget render for this front-end request? (Memoized.)
 * Logged-in users only, unless they hid it for this browser. Filterable.
 */
function iwpfb_active() {
	static $active = null;
	if ( null !== $active ) {
		return $active;
	}
	if ( is_admin()
		|| wp_doing_ajax()
		|| wp_doing_cron()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
	) {
		return $active = false;
	}
	$active = is_user_logged_in() && empty( $_COOKIE[ IWPFB_HIDE_COOKIE ] );
	return $active = (bool) apply_filters( 'iwpfb_active', $active );
}

/** Who can submit/read via REST. Logged-in team only (public gets 403). Filterable. */
function iwpfb_can_use() {
	return (bool) apply_filters( 'iwpfb_can_use', is_user_logged_in() );
}
