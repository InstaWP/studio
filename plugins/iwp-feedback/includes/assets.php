<?php
/**
 * Enqueue the floating widget — only when the request is gated-in (iwpfb_active()).
 * Self-contained CSS/JS (its own namespace), so it does not depend on the theme's
 * chrome.js / kit.css and never collides with page styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! iwpfb_active() ) {
		return;
	}

	$css_path = IWPFB_DIR . 'assets/feedback.css';
	$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : IWPFB_VERSION;
	wp_enqueue_style( 'iwpfb', IWPFB_URL . 'assets/feedback.css', array(), $css_ver );

	$js_path = IWPFB_DIR . 'assets/feedback.js';
	$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : IWPFB_VERSION;
	wp_enqueue_script( 'iwpfb', IWPFB_URL . 'assets/feedback.js', array(), $js_ver, true );

	$current = wp_get_current_user();

	wp_localize_script( 'iwpfb', 'IWPFB', array(
		'rest'      => esc_url_raw( rest_url( 'instawp-feedback/v1' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'loggedIn'  => is_user_logged_in(),
		'types'     => iwpfb_types(),
		'statuses'  => iwpfb_statuses(),
		'user'      => $current && $current->exists() ? $current->display_name : '',
		'isAdmin'   => current_user_can( 'edit_posts' ),
		'adminPost' => admin_url( 'post.php' ),
		'i18n'      => array(
			'launch'   => __( 'Feedback', 'instawp-feedback' ),
			'place'    => __( 'Leave feedback', 'instawp-feedback' ),
			'hint'     => __( 'Feedback mode is on. Click anywhere on the page to leave a note. Keep going, then click Done (or press Esc) when finished.', 'instawp-feedback' ),
			'done'     => __( 'Done', 'instawp-feedback' ),
			'name'     => __( 'Your name', 'instawp-feedback' ),
			'postingas'=> __( 'Posting as', 'instawp-feedback' ),
			'change'   => __( 'change', 'instawp-feedback' ),
			'you'      => __( 'You', 'instawp-feedback' ),
			'mineonly' => __( 'Show only mine', 'instawp-feedback' ),
			'showall'  => __( 'Show all notes', 'instawp-feedback' ),
			'message'  => __( 'What stands out? Bug, idea, copy tweak…', 'instawp-feedback' ),
			'send'     => __( 'Send', 'instawp-feedback' ),
			'sending'  => __( 'Sending…', 'instawp-feedback' ),
			'cancel'   => __( 'Cancel', 'instawp-feedback' ),
			'thanks'   => __( 'Thanks! Feedback sent.', 'instawp-feedback' ),
			'error'    => __( 'Could not send. Try again.', 'instawp-feedback' ),
			'needname' => __( 'Add your name and a note first.', 'instawp-feedback' ),
			'showpins' => __( 'Show pins', 'instawp-feedback' ),
			'hidepins' => __( 'Hide pins', 'instawp-feedback' ),
			'hideme'   => __( 'Hide widget for me', 'instawp-feedback' ),
			'openadmin'=> __( 'Open in admin', 'instawp-feedback' ),
			'delete'   => __( 'Delete', 'instawp-feedback' ),
			'confirmdel'=> __( 'Confirm delete', 'instawp-feedback' ),
			'deleting' => __( 'Deleting…', 'instawp-feedback' ),
			'deleted'  => __( 'Feedback deleted.', 'instawp-feedback' ),
			'reply'    => __( 'Reply', 'instawp-feedback' ),
			'replyph'  => __( 'Write a reply…', 'instawp-feedback' ),
			'replying' => __( 'Replying…', 'instawp-feedback' ),
			'replied'  => __( 'Reply added.', 'instawp-feedback' ),
			'team'     => __( 'Team', 'instawp-feedback' ),
			'on'       => __( 'on', 'instawp-feedback' ),
		),
	) );
}, 20 );
