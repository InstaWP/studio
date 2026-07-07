<?php
/**
 * REST endpoints (namespace instawp-feedback/v1). Both are gated by iwpfb_can_use()
 * — logged-in OR holding the team cookie — so the public can neither submit nor read
 * notes even though the routes are technically open.
 *
 *   POST /submit  { name, message, type, url, path, page_title, selector,
 *                   rel_x, rel_y, page_x, page_y, doc_w, doc_h, viewport }
 *                 -> { ok, id }
 *   GET  /list    { path }  -> { items: [ { id, name, type, status, message,
 *                   selector, rel_x, rel_y, page_x, page_y, doc_w, doc_h, date } ] }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Light per-IP rate limit: max $max requests / 60s per bucket. */
function iwpfb_rate_ok( $bucket, $max = 20 ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	$key = 'iwpfb_rl_' . md5( $bucket . '|' . $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $key, $n + 1, 60 );
	return true;
}

/** Clamp a posted fraction to 0..1 (float). */
function iwpfb_frac( $v ) {
	$v = (float) $v;
	if ( $v < 0 ) { $v = 0.0; }
	if ( $v > 1 ) { $v = 1.0; }
	return round( $v, 5 );
}

/** Normalize a path the same way the JS does: strip trailing slashes, default "/". */
function iwpfb_norm_path( $path ) {
	$path = '/' . ltrim( (string) $path, '/' );
	$path = rtrim( $path, '/' );
	return '' === $path ? '/' : $path;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'instawp-feedback/v1', '/submit', array(
		'methods'             => 'POST',
		'callback'            => 'iwpfb_rest_submit',
		'permission_callback' => 'iwpfb_can_use',
		'args'                => array(
			'name'    => array( 'required' => true, 'type' => 'string' ),
			'message' => array( 'required' => true, 'type' => 'string' ),
		),
	) );
	register_rest_route( 'instawp-feedback/v1', '/list', array(
		'methods'             => 'GET',
		'callback'            => 'iwpfb_rest_list',
		'permission_callback' => 'iwpfb_can_use',
		'args'                => array(
			'path' => array( 'required' => false, 'type' => 'string' ),
			'url'  => array( 'required' => false, 'type' => 'string' ),
		),
	) );
	register_rest_route( 'instawp-feedback/v1', '/delete', array(
		'methods'             => 'POST',
		'callback'            => 'iwpfb_rest_delete',
		'permission_callback' => 'iwpfb_can_use',
		'args'                => array(
			'id' => array( 'required' => true, 'type' => 'integer' ),
		),
	) );
	register_rest_route( 'instawp-feedback/v1', '/reply', array(
		'methods'             => 'POST',
		'callback'            => 'iwpfb_rest_reply',
		'permission_callback' => 'iwpfb_can_use',
		'args'                => array(
			'id'   => array( 'required' => true, 'type' => 'integer' ),
			'text' => array( 'required' => true, 'type' => 'string' ),
		),
	) );
} );

/* ------------------------------------------------------------------ replies */

/** Replies thread for a note (always an array). */
function iwpfb_get_replies( $post_id ) {
	$r = get_post_meta( $post_id, IWPFB_META_PREFIX . 'replies', true );
	return is_array( $r ) ? array_values( $r ) : array();
}

/** Append a reply (server-trusted fields filled here) and persist. Returns the stored reply. */
function iwpfb_add_reply( $post_id, $name, $text, $uid = '', $is_admin = null ) {
	$replies = iwpfb_get_replies( $post_id );
	$reply   = array(
		'name'    => trim( mb_substr( sanitize_text_field( (string) $name ), 0, 80 ) ),
		'text'    => trim( mb_substr( sanitize_textarea_field( (string) $text ), 0, 3000 ) ),
		'uid'     => sanitize_text_field( (string) $uid ),
		'admin'   => ( null === $is_admin ? ( current_user_can( 'edit_posts' ) ? 1 : 0 ) : ( $is_admin ? 1 : 0 ) ),
		'user_id' => get_current_user_id(),
		't'       => time(),
		'ts'      => current_time( 'mysql' ),
	);
	$replies[] = $reply;
	update_post_meta( $post_id, IWPFB_META_PREFIX . 'replies', $replies );
	return $reply;
}

/** Shape one reply for output. */
function iwpfb_format_reply( $r ) {
	$t = isset( $r['t'] ) ? (int) $r['t'] : ( isset( $r['ts'] ) ? (int) strtotime( get_gmt_from_date( $r['ts'] ) ) : 0 );
	return array(
		'name'  => isset( $r['name'] ) ? $r['name'] : '',
		'text'  => isset( $r['text'] ) ? $r['text'] : '',
		'admin' => ! empty( $r['admin'] ),
		'date'  => $t ? wp_date( 'M j, Y g:i a', $t ) : '',
		'ago'   => $t ? sprintf( /* translators: %s: human time diff */ __( '%s ago', 'instawp-feedback' ), human_time_diff( $t ) ) : '',
	);
}

function iwpfb_rest_reply( WP_REST_Request $request ) {
	if ( ! iwpfb_rate_ok( 'reply', 40 ) ) {
		return new WP_Error( 'iwpfb_rate', __( 'Too many requests.', 'instawp-feedback' ), array( 'status' => 429 ) );
	}
	$id = (int) $request->get_param( 'id' );
	if ( ! $id || get_post_type( $id ) !== IWPFB_PT ) {
		return new WP_Error( 'iwpfb_404', __( 'That feedback no longer exists.', 'instawp-feedback' ), array( 'status' => 404 ) );
	}
	$text = trim( sanitize_textarea_field( (string) $request->get_param( 'text' ) ) );
	if ( '' === $text ) {
		return new WP_Error( 'iwpfb_empty', __( 'Write a reply first.', 'instawp-feedback' ), array( 'status' => 400 ) );
	}
	$name = trim( sanitize_text_field( (string) $request->get_param( 'name' ) ) );
	if ( '' === $name ) {
		$name = is_user_logged_in() ? wp_get_current_user()->display_name : __( 'Someone', 'instawp-feedback' );
	}

	$reply = iwpfb_add_reply( $id, $name, $text, (string) $request->get_param( 'uid' ) );

	/** Fires after a reply is added to a feedback note. */
	do_action( 'iwpfb_replied', $id, $reply );

	return rest_ensure_response( array( 'ok' => true, 'id' => $id, 'reply' => iwpfb_format_reply( $reply ) ) );
}

/* ----------------------------------------------------------------- delete */

/**
 * Delete one feedback note. Allowed when the requester OWNS it — their per-browser
 * author token matches the stored one (hash_equals), or they're the logged-in author —
 * OR they can edit posts (admins/editors). Trashed (recoverable in wp-admin), not purged.
 */
function iwpfb_rest_delete( WP_REST_Request $request ) {
	if ( ! iwpfb_rate_ok( 'delete', 30 ) ) {
		return new WP_Error( 'iwpfb_rate', __( 'Too many requests.', 'instawp-feedback' ), array( 'status' => 429 ) );
	}

	$id = (int) $request->get_param( 'id' );
	if ( ! $id || get_post_type( $id ) !== IWPFB_PT ) {
		return new WP_Error( 'iwpfb_404', __( 'That feedback no longer exists.', 'instawp-feedback' ), array( 'status' => 404 ) );
	}

	$uid        = sanitize_text_field( (string) $request->get_param( 'uid' ) );
	$owner_uid  = (string) iwpfb_get( $id, 'author_uid', '' );
	$owner_user = (int) iwpfb_get( $id, 'user_id', 0 );

	$can_manage    = current_user_can( 'edit_posts' ) || current_user_can( 'delete_post', $id );
	$owns_by_token = ( '' !== $uid && '' !== $owner_uid && hash_equals( $owner_uid, $uid ) );
	$owns_by_user  = ( is_user_logged_in() && $owner_user && $owner_user === get_current_user_id() );

	if ( ! ( $can_manage || $owns_by_token || $owns_by_user ) ) {
		return new WP_Error( 'iwpfb_forbidden', __( 'You can only delete your own feedback.', 'instawp-feedback' ), array( 'status' => 403 ) );
	}

	$res = wp_trash_post( $id );
	if ( ! $res ) {
		return new WP_Error( 'iwpfb_delfail', __( 'Could not delete that note.', 'instawp-feedback' ), array( 'status' => 500 ) );
	}

	/** Fires after a feedback note is deleted (trashed). */
	do_action( 'iwpfb_deleted', $id );

	return rest_ensure_response( array( 'ok' => true, 'id' => $id ) );
}

/* ----------------------------------------------------------------- submit */

function iwpfb_rest_submit( WP_REST_Request $request ) {
	if ( ! iwpfb_rate_ok( 'submit', 30 ) ) {
		return new WP_Error( 'iwpfb_rate', __( 'Too many submissions. Please wait a moment.', 'instawp-feedback' ), array( 'status' => 429 ) );
	}

	$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$msg  = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
	$name = trim( mb_substr( $name, 0, 80 ) );
	$msg  = trim( $msg );

	if ( '' === $name || '' === $msg ) {
		return new WP_Error( 'iwpfb_invalid', __( 'Please enter your name and a note.', 'instawp-feedback' ), array( 'status' => 400 ) );
	}
	if ( mb_strlen( $msg ) > 5000 ) {
		$msg = mb_substr( $msg, 0, 5000 );
	}

	$type     = iwpfb_clean_type( $request->get_param( 'type' ) );
	$uid      = sanitize_text_field( (string) $request->get_param( 'uid' ) );
	$url       = esc_url_raw( (string) $request->get_param( 'url' ) );
	$path      = iwpfb_norm_path( $request->get_param( 'path' ) ?: wp_parse_url( $url, PHP_URL_PATH ) );
	$page_title = sanitize_text_field( (string) $request->get_param( 'page_title' ) );

	$post_id = wp_insert_post( array(
		'post_type'    => IWPFB_PT,
		'post_status'  => 'publish', // private CPT — "publish" just means visible in the admin list
		'post_title'   => wp_trim_words( $msg, 12, '…' ),
		'post_content' => $msg,
	), true );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return new WP_Error( 'iwpfb_insert', __( 'Could not save your feedback.', 'instawp-feedback' ), array( 'status' => 500 ) );
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	iwpfb_set( $post_id, 'name',         $name );
	iwpfb_set( $post_id, 'type',         $type );
	iwpfb_set( $post_id, 'status',       'new' );
	iwpfb_set( $post_id, 'url',          $url );
	iwpfb_set( $post_id, 'path',         $path );
	iwpfb_set( $post_id, 'page_title',   $page_title );
	iwpfb_set( $post_id, 'selector',     sanitize_text_field( (string) $request->get_param( 'selector' ) ) );
	iwpfb_set( $post_id, 'element',      sanitize_text_field( (string) $request->get_param( 'element' ) ) );
	iwpfb_set( $post_id, 'rel_x',        iwpfb_frac( $request->get_param( 'rel_x' ) ) );
	iwpfb_set( $post_id, 'rel_y',        iwpfb_frac( $request->get_param( 'rel_y' ) ) );
	iwpfb_set( $post_id, 'page_x',       (int) $request->get_param( 'page_x' ) );
	iwpfb_set( $post_id, 'page_y',       (int) $request->get_param( 'page_y' ) );
	iwpfb_set( $post_id, 'doc_w',        (int) $request->get_param( 'doc_w' ) );
	iwpfb_set( $post_id, 'doc_h',        (int) $request->get_param( 'doc_h' ) );
	iwpfb_set( $post_id, 'viewport',     sanitize_text_field( (string) $request->get_param( 'viewport' ) ) );
	iwpfb_set( $post_id, 'user_agent',   $ua );
	iwpfb_set( $post_id, 'ip',           $ip );
	iwpfb_set( $post_id, 'user_id',      get_current_user_id() );
	iwpfb_set( $post_id, 'author_uid',   $uid );
	iwpfb_set( $post_id, 'submitted_at', current_time( 'mysql' ) );

	/** Fires after a feedback note is stored. */
	do_action( 'iwpfb_submitted', $post_id );

	return rest_ensure_response( array(
		'ok'   => true,
		'id'   => $post_id,
		'item' => iwpfb_format_item( get_post( $post_id ), $uid ),
	) );
}

/* ------------------------------------------------------------------- list */

function iwpfb_rest_list( WP_REST_Request $request ) {
	if ( ! iwpfb_rate_ok( 'list', 120 ) ) {
		return new WP_Error( 'iwpfb_rate', __( 'Too many requests.', 'instawp-feedback' ), array( 'status' => 429 ) );
	}

	// Pins for a page — the widget sends `path` (window.location.pathname); API callers
	// may pass a full `url` instead. Both are resolved to the stored normalized path.
	$ref = $request->get_param( 'path' );
	if ( ( null === $ref || '' === $ref ) && $request->get_param( 'url' ) ) {
		$ref = $request->get_param( 'url' );
	}
	$path   = iwpfb_resolve_path( $ref );
	$viewer = sanitize_text_field( (string) $request->get_param( 'uid' ) );

	$q = new WP_Query( array(
		'post_type'      => IWPFB_PT,
		'post_status'    => 'publish',
		'posts_per_page' => 300,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => IWPFB_META_PREFIX . 'path', 'value' => $path ),
		),
	) );

	$items = array_map(
		function ( $p ) use ( $viewer ) {
			return iwpfb_format_item( $p, $viewer );
		},
		$q->posts
	);

	return rest_ensure_response( array( 'items' => array_values( $items ) ) );
}

/**
 * Shape a feedback post for the front-end widget. $viewer_uid is the requesting
 * browser's author token; we compare it (and the logged-in user id) server-side and
 * expose only a boolean `mine` — the raw author tokens are never returned to anyone.
 */
function iwpfb_format_item( $post, $viewer_uid = '' ) {
	$id     = $post->ID;
	$status = iwpfb_clean_status( iwpfb_get( $id, 'status', 'new' ) );

	$item_uid  = (string) iwpfb_get( $id, 'author_uid', '' );
	$item_user = (int) iwpfb_get( $id, 'user_id', 0 );
	$mine      = false;
	if ( '' !== $viewer_uid && '' !== $item_uid && hash_equals( $item_uid, (string) $viewer_uid ) ) {
		$mine = true;
	}
	if ( ! $mine && $item_user && is_user_logged_in() && $item_user === get_current_user_id() ) {
		$mine = true;
	}

	return array(
		'id'       => $id,
		'name'     => iwpfb_get( $id, 'name' ),
		'mine'     => $mine,
		'type'     => iwpfb_clean_type( iwpfb_get( $id, 'type', 'other' ) ),
		'status'   => $status,
		'message'  => $post->post_content,
		'replies'  => array_map( 'iwpfb_format_reply', iwpfb_get_replies( $id ) ),
		'selector' => iwpfb_get( $id, 'selector' ),
		'element'  => iwpfb_get( $id, 'element' ),
		'rel_x'    => (float) iwpfb_get( $id, 'rel_x', 0 ),
		'rel_y'    => (float) iwpfb_get( $id, 'rel_y', 0 ),
		'page_x'   => (int) iwpfb_get( $id, 'page_x', 0 ),
		'page_y'   => (int) iwpfb_get( $id, 'page_y', 0 ),
		'doc_w'    => (int) iwpfb_get( $id, 'doc_w', 0 ),
		'doc_h'    => (int) iwpfb_get( $id, 'doc_h', 0 ),
		'date'     => get_the_date( 'M j, Y g:i a', $post ),
		'ago'      => sprintf( /* translators: %s: human time diff */ __( '%s ago', 'instawp-feedback' ), human_time_diff( get_post_time( 'U', true, $post ) ) ),
	);
}
