<?php
/**
 * Export / Import — wp-admin UI (admin-only). The data layer lives in exchange-core.php
 * (always loaded, shared with the WP-CLI commands).
 *
 * Export  : wp-admin -> Feedback -> Export / Import downloads a structured JSON file
 *           (grouped by page; a top-level "_instructions" field explains the loop), or
 *           a read-only Markdown view. The "Include" filter can limit to one status or
 *           to "Unresolved" (still-actionable: not resolved, not wontfix).
 * Import  : upload that JSON back; items matched by id get status + resolution applied.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------- export */

add_action( 'admin_post_iwpfb_export', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'instawp-feedback' ) );
	}
	check_admin_referer( 'iwpfb_export' );

	$filter = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
	$format = isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'json';
	$stamp  = gmdate( 'Ymd-His' );

	nocache_headers();

	if ( 'md' === $format ) {
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="feedback-' . $stamp . '.md"' );
		echo iwpfb_export_markdown( iwpfb_collect( $filter ), $filter ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file download, escaped within builder
		exit;
	}

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="feedback-' . $stamp . '.json"' );
	echo wp_json_encode( iwpfb_build_export( $filter ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
} );

/* ------------------------------------------------------------- import */

add_action( 'admin_post_iwpfb_import', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'instawp-feedback' ) );
	}
	check_admin_referer( 'iwpfb_import' );

	$back = admin_url( 'edit.php?post_type=' . IWPFB_PT . '&page=iwpfb-exchange' );

	if ( empty( $_FILES['iwpfb_file'] ) || ! empty( $_FILES['iwpfb_file']['error'] ) || empty( $_FILES['iwpfb_file']['tmp_name'] ) ) {
		wp_safe_redirect( add_query_arg( 'iwpfb_msg', 'nofile', $back ) );
		exit;
	}
	if ( (int) $_FILES['iwpfb_file']['size'] > 5 * 1024 * 1024 ) {
		wp_safe_redirect( add_query_arg( 'iwpfb_msg', 'toobig', $back ) );
		exit;
	}

	$raw  = file_get_contents( $_FILES['iwpfb_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp upload
	$json = json_decode( (string) $raw, true );
	if ( ! is_array( $json ) ) {
		wp_safe_redirect( add_query_arg( 'iwpfb_msg', 'badjson', $back ) );
		exit;
	}

	$res = iwpfb_apply_import( $json );

	wp_safe_redirect( add_query_arg( array(
		'iwpfb_msg'     => 'done',
		'iwpfb_updated' => $res['updated'],
		'iwpfb_skipped' => $res['skipped'],
	), $back ) );
	exit;
} );

/* --------------------------------------------------------- admin page */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=' . IWPFB_PT,
		__( 'Export / Import Feedback', 'instawp-feedback' ),
		__( 'Export / Import', 'instawp-feedback' ),
		'edit_posts',
		'iwpfb-exchange',
		'iwpfb_render_exchange_page'
	);
} );

function iwpfb_render_exchange_page() {
	$post_url = admin_url( 'admin-post.php' );
	$total    = iwpfb_count_items( iwpfb_collect( '' ) );
	$open     = iwpfb_count_items( iwpfb_collect( 'unresolved' ) );

	$msg = isset( $_GET['iwpfb_msg'] ) ? sanitize_key( wp_unslash( $_GET['iwpfb_msg'] ) ) : '';
	echo '<div class="wrap"><h1>' . esc_html__( 'Export / Import Feedback', 'instawp-feedback' ) . '</h1>';

	if ( 'done' === $msg ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: 1: updated count, 2: skipped count */
				__( 'Import complete: %1$d item(s) updated, %2$d skipped.', 'instawp-feedback' ),
				(int) ( $_GET['iwpfb_updated'] ?? 0 ),
				(int) ( $_GET['iwpfb_skipped'] ?? 0 )
			) )
		);
	} elseif ( $msg ) {
		$errs = array(
			'nofile'  => __( 'No file was uploaded.', 'instawp-feedback' ),
			'toobig'  => __( 'That file is too large (5MB max).', 'instawp-feedback' ),
			'badjson' => __( 'That file is not a valid feedback JSON export.', 'instawp-feedback' ),
		);
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $errs[ $msg ] ?? __( 'Something went wrong.', 'instawp-feedback' ) ) );
	}

	echo '<p style="max-width:680px;color:#50575e">' . esc_html__(
		'Export feedback as one structured file (grouped by page, with each pin location and comment). Hand it off, work through it, set each item\'s status to "resolved", then import the same file back to mark those items done. The same is scriptable: see "wp iwpfb" on the command line.',
		'instawp-feedback'
	) . '</p>';

	echo '<div style="display:flex;gap:24px;flex-wrap:wrap;max-width:980px">';

	/* ---- export card ---- */
	echo '<div class="card" style="flex:1;min-width:340px;padding:4px 20px 20px">';
	echo '<h2>' . esc_html__( 'Export', 'instawp-feedback' ) . '</h2>';
	echo '<p>' . esc_html( sprintf(
		/* translators: 1: total count, 2: unresolved count */
		__( '%1$d feedback item(s) total, %2$d unresolved.', 'instawp-feedback' ),
		$total,
		$open
	) ) . '</p>';
	echo '<form method="get" action="' . esc_url( $post_url ) . '">';
	echo '<input type="hidden" name="action" value="iwpfb_export">';
	wp_nonce_field( 'iwpfb_export' );
	echo '<p><label>' . esc_html__( 'Include', 'instawp-feedback' ) . ' ';
	echo '<select name="status">';
	echo '<option value="">' . esc_html__( 'All statuses', 'instawp-feedback' ) . '</option>';
	echo '<option value="unresolved">' . esc_html__( 'Unresolved only (not resolved / wontfix)', 'instawp-feedback' ) . '</option>';
	foreach ( iwpfb_statuses() as $val => $label ) {
		printf( '<option value="%s">%s</option>', esc_attr( $val ), esc_html( $label ) );
	}
	echo '</select></label></p>';
	echo '<p>';
	echo '<button type="submit" name="format" value="json" class="button button-primary">' . esc_html__( 'Download JSON (re-importable)', 'instawp-feedback' ) . '</button> ';
	echo '<button type="submit" name="format" value="md" class="button">' . esc_html__( 'Download Markdown (read-only)', 'instawp-feedback' ) . '</button>';
	echo '</p>';
	echo '</form>';
	echo '</div>';

	/* ---- import card ---- */
	echo '<div class="card" style="flex:1;min-width:340px;padding:4px 20px 20px">';
	echo '<h2>' . esc_html__( 'Import', 'instawp-feedback' ) . '</h2>';
	echo '<p>' . esc_html__( 'Upload an edited JSON export. Items are matched by id; their status and resolution are updated. Anything else (comment, page, pin) is left untouched.', 'instawp-feedback' ) . '</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $post_url ) . '">';
	echo '<input type="hidden" name="action" value="iwpfb_import">';
	wp_nonce_field( 'iwpfb_import' );
	echo '<p><input type="file" name="iwpfb_file" accept="application/json,.json" required></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Import & apply', 'instawp-feedback' ) . '</button></p>';
	echo '</form>';
	echo '</div>';

	echo '</div></div>';
}
