<?php
/**
 * wp-admin experience for the `feedback` post type:
 *   - list columns: status badge, note, from, type, page (opens the URL), date
 *   - filter dropdowns for status + type
 *   - a "Feedback details" side meta box (editable status/type + read-only context)
 *   - a count bubble of NEW feedback on the menu item
 * All review happens here (notifications were set to dashboard-only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ----------------------------------------------------------- list columns */

add_filter( 'manage_' . IWPFB_PT . '_posts_columns', function ( $cols ) {
	$new = array();
	$new['cb']            = isset( $cols['cb'] ) ? $cols['cb'] : '<input type="checkbox" />';
	$new['iwpfb_status']  = __( 'Status', 'instawp-feedback' );
	$new['title']         = __( 'Note', 'instawp-feedback' );
	$new['iwpfb_from']    = __( 'From', 'instawp-feedback' );
	$new['iwpfb_type']    = __( 'Type', 'instawp-feedback' );
	$new['iwpfb_page']    = __( 'Page', 'instawp-feedback' );
	$new['date']          = isset( $cols['date'] ) ? $cols['date'] : __( 'Date', 'instawp-feedback' );
	return $new;
} );

add_action( 'manage_' . IWPFB_PT . '_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'iwpfb_status':
			$s      = iwpfb_clean_status( iwpfb_get( $post_id, 'status', 'new' ) );
			$labels = iwpfb_statuses();
			printf(
				'<span class="iwpfb-badge iwpfb-st-%1$s">%2$s</span>',
				esc_attr( $s ),
				esc_html( $labels[ $s ] ?? $s )
			);
			break;

		case 'iwpfb_from':
			echo esc_html( iwpfb_get( $post_id, 'name', '—' ) );
			break;

		case 'iwpfb_type':
			$t      = iwpfb_clean_type( iwpfb_get( $post_id, 'type', 'other' ) );
			$labels = iwpfb_types();
			echo esc_html( $labels[ $t ] ?? $t );
			break;

		case 'iwpfb_page':
			$url  = iwpfb_get( $post_id, 'url' );
			$path = iwpfb_get( $post_id, 'path', '—' );
			if ( $url ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener">%s ↗</a>',
					esc_url( $url ),
					esc_html( $path )
				);
			} else {
				echo esc_html( $path );
			}
			break;
	}
}, 10, 2 );

add_filter( 'manage_edit-' . IWPFB_PT . '_sortable_columns', function ( $cols ) {
	$cols['iwpfb_status'] = 'iwpfb_status';
	return $cols;
} );

/* ----------------------------------------------------------- list filters */

add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( IWPFB_PT !== $post_type ) {
		return;
	}
	$cur_s = isset( $_GET['iwpfb_status'] ) ? sanitize_key( wp_unslash( $_GET['iwpfb_status'] ) ) : '';
	$cur_t = isset( $_GET['iwpfb_type'] ) ? sanitize_key( wp_unslash( $_GET['iwpfb_type'] ) ) : '';

	echo '<select name="iwpfb_status"><option value="">' . esc_html__( 'All statuses', 'instawp-feedback' ) . '</option>';
	foreach ( iwpfb_statuses() as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $cur_s, $val, false ), esc_html( $label ) );
	}
	echo '</select>';

	echo '<select name="iwpfb_type"><option value="">' . esc_html__( 'All types', 'instawp-feedback' ) . '</option>';
	foreach ( iwpfb_types() as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $cur_t, $val, false ), esc_html( $label ) );
	}
	echo '</select>';
} );

add_filter( 'parse_query', function ( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || empty( $query->query_vars['post_type'] ) || IWPFB_PT !== $query->query_vars['post_type'] ) {
		return;
	}
	$meta = array();
	if ( ! empty( $_GET['iwpfb_status'] ) ) {
		$meta[] = array( 'key' => IWPFB_META_PREFIX . 'status', 'value' => sanitize_key( wp_unslash( $_GET['iwpfb_status'] ) ) );
	}
	if ( ! empty( $_GET['iwpfb_type'] ) ) {
		$meta[] = array( 'key' => IWPFB_META_PREFIX . 'type', 'value' => sanitize_key( wp_unslash( $_GET['iwpfb_type'] ) ) );
	}
	if ( $meta ) {
		$query->set( 'meta_query', $meta );
	}
} );

/* ------------------------------------------------------------- meta box */

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'iwpfb_details',
		__( 'Feedback details', 'instawp-feedback' ),
		'iwpfb_render_meta_box',
		IWPFB_PT,
		'side',
		'high'
	);
} );

function iwpfb_render_meta_box( $post ) {
	wp_nonce_field( 'iwpfb_save', 'iwpfb_nonce' );
	$id     = $post->ID;
	$status = iwpfb_clean_status( iwpfb_get( $id, 'status', 'new' ) );
	$type   = iwpfb_clean_type( iwpfb_get( $id, 'type', 'other' ) );
	$url    = iwpfb_get( $id, 'url' );

	echo '<p><label for="iwpfb_status"><strong>' . esc_html__( 'Status', 'instawp-feedback' ) . '</strong></label><br>';
	echo '<select id="iwpfb_status" name="iwpfb_status" style="width:100%">';
	foreach ( iwpfb_statuses() as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $status, $val, false ), esc_html( $label ) );
	}
	echo '</select></p>';

	echo '<p><label for="iwpfb_type"><strong>' . esc_html__( 'Type', 'instawp-feedback' ) . '</strong></label><br>';
	echo '<select id="iwpfb_type" name="iwpfb_type" style="width:100%">';
	foreach ( iwpfb_types() as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $type, $val, false ), esc_html( $label ) );
	}
	echo '</select></p>';

	echo '<p><label for="iwpfb_resolution"><strong>' . esc_html__( 'Resolution', 'instawp-feedback' ) . '</strong></label><br>';
	echo '<textarea id="iwpfb_resolution" name="iwpfb_resolution" rows="3" style="width:100%" placeholder="' . esc_attr__( 'What was done (also set by import).', 'instawp-feedback' ) . '">' . esc_textarea( iwpfb_get( $id, 'resolution', '' ) ) . '</textarea></p>';

	// Conversation thread (shared with the front-end widget).
	echo '<hr><p style="margin:0 0 6px"><strong>' . esc_html__( 'Replies', 'instawp-feedback' ) . '</strong></p>';
	$replies = iwpfb_get_replies( $id );
	if ( $replies ) {
		foreach ( $replies as $rp ) {
			$f = iwpfb_format_reply( $rp );
			echo '<div style="border-left:2px solid #dcdfe3;padding:1px 0 5px 9px;margin-bottom:6px">';
			echo '<div style="font-size:12px"><strong>' . esc_html( $f['name'] ) . '</strong>';
			if ( $f['admin'] ) {
				echo ' <span style="background:#11BF85;color:#fff;border-radius:3px;padding:0 5px;font-size:10px;font-weight:600">' . esc_html__( 'Team', 'instawp-feedback' ) . '</span>';
			}
			echo ' <span style="color:#8c8f94">' . esc_html( $f['date'] ) . '</span></div>';
			echo '<div style="font-size:12.5px;white-space:pre-wrap;word-wrap:break-word">' . esc_html( $f['text'] ) . '</div>';
			echo '</div>';
		}
	} else {
		echo '<p style="color:#8c8f94;font-size:12px;margin:0 0 8px">' . esc_html__( 'No replies yet.', 'instawp-feedback' ) . '</p>';
	}
	echo '<p><label for="iwpfb_reply"><strong>' . esc_html__( 'Add a reply', 'instawp-feedback' ) . '</strong></label><br>';
	echo '<textarea id="iwpfb_reply" name="iwpfb_reply" rows="2" style="width:100%" placeholder="' . esc_attr__( 'Reply to this feedback (posted as Team)…', 'instawp-feedback' ) . '"></textarea></p>';

	echo '<hr><table class="iwpfb-meta" style="width:100%;font-size:12px;line-height:1.5">';
	$rows = array(
		__( 'From', 'instawp-feedback' )     => iwpfb_get( $id, 'name', '—' ),
		__( 'Page', 'instawp-feedback' )     => $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( iwpfb_get( $id, 'path', $url ) ) . ' ↗</a>' : esc_html( iwpfb_get( $id, 'path', '—' ) ),
		__( 'Page title', 'instawp-feedback' ) => esc_html( iwpfb_get( $id, 'page_title', '—' ) ),
		__( 'Element', 'instawp-feedback' )  => esc_html( iwpfb_get( $id, 'element', '—' ) ),
		__( 'Selector', 'instawp-feedback' ) => '<code style="font-size:11px;word-break:break-all">' . esc_html( iwpfb_get( $id, 'selector', '—' ) ) . '</code>',
		__( 'Pin', 'instawp-feedback' )      => esc_html( round( (float) iwpfb_get( $id, 'rel_x', 0 ) * 100 ) . '% / ' . round( (float) iwpfb_get( $id, 'rel_y', 0 ) * 100 ) . '%' ),
		__( 'Viewport', 'instawp-feedback' ) => esc_html( iwpfb_get( $id, 'viewport', '—' ) ),
		__( 'Submitted', 'instawp-feedback' ) => esc_html( iwpfb_get( $id, 'submitted_at', '—' ) ),
		__( 'IP', 'instawp-feedback' )       => esc_html( iwpfb_get( $id, 'ip', '—' ) ),
	);
	foreach ( $rows as $k => $v ) {
		echo '<tr><th style="text-align:left;padding:3px 8px 3px 0;vertical-align:top;color:#646970;font-weight:600;white-space:nowrap">' . esc_html( $k ) . '</th><td style="padding:3px 0;vertical-align:top">' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values escaped above
	}
	echo '</table>';

	$ua = iwpfb_get( $id, 'user_agent' );
	if ( $ua ) {
		echo '<p style="margin-top:8px;color:#8c8f94;font-size:11px;word-break:break-word">' . esc_html( $ua ) . '</p>';
	}
}

add_action( 'save_post_' . IWPFB_PT, function ( $post_id ) {
	if ( ! isset( $_POST['iwpfb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['iwpfb_nonce'] ) ), 'iwpfb_save' ) ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['iwpfb_status'] ) ) {
		iwpfb_set( $post_id, 'status', iwpfb_clean_status( wp_unslash( $_POST['iwpfb_status'] ) ) );
	}
	if ( isset( $_POST['iwpfb_type'] ) ) {
		iwpfb_set( $post_id, 'type', iwpfb_clean_type( wp_unslash( $_POST['iwpfb_type'] ) ) );
	}
	if ( isset( $_POST['iwpfb_resolution'] ) ) {
		iwpfb_set( $post_id, 'resolution', sanitize_textarea_field( wp_unslash( $_POST['iwpfb_resolution'] ) ) );
	}
	if ( isset( $_POST['iwpfb_reply'] ) ) {
		$rtext = trim( sanitize_textarea_field( wp_unslash( $_POST['iwpfb_reply'] ) ) );
		if ( '' !== $rtext ) {
			$me = wp_get_current_user();
			iwpfb_add_reply( $post_id, ( $me && $me->exists() ) ? $me->display_name : __( 'Team', 'instawp-feedback' ), $rtext, '', true );
		}
	}
} );

/* ----------------------------------------------------- menu count + styles */

/** Count of NEW feedback (for the menu bubble). */
function iwpfb_new_count() {
	$q = new WP_Query( array(
		'post_type'      => IWPFB_PT,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array(
			array( 'key' => IWPFB_META_PREFIX . 'status', 'value' => 'new' ),
		),
	) );
	return (int) $q->found_posts;
}

add_action( 'admin_menu', function () {
	global $menu;
	$count = iwpfb_new_count();
	if ( ! $count || ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . IWPFB_PT === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>';
			break;
		}
	}
}, 99 );

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || IWPFB_PT !== $screen->post_type ) {
		return;
	}
	echo '<style>
		.iwpfb-badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.6;color:#fff}
		.iwpfb-st-new{background:#11BF85}
		.iwpfb-st-in_progress{background:#d28b00}
		.iwpfb-st-resolved{background:#6b7280}
		.iwpfb-st-wontfix{background:#b91c1c}
		.column-iwpfb_status{width:110px}
		.column-iwpfb_type{width:90px}
		.column-iwpfb_from{width:140px}
	</style>';
} );
