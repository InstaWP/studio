<?php
/**
 * Export / import DATA layer — pure functions, NO admin hooks. Always loaded (the
 * admin UI in exchange.php and the WP-CLI commands in cli.php both build on this;
 * CLI runs with is_admin() === false, so this must not live behind that gate).
 *
 * Filter values understood everywhere ($filter):
 *   ''                  -> all
 *   <a valid status>    -> just that status (new|in_progress|resolved|wontfix)
 *   'unresolved'|'open' -> still actionable: NOT resolved and NOT wontfix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Human-friendly instructions baked into every export (for whoever works the file). */
function iwpfb_export_instructions() {
	return 'Each item is front-end feedback left by the team, grouped by the page it is about. '
		. 'To work through it: read each item\'s "comment" (what to change) and "location" (where on the page), '
		. 'make the change, then set that item\'s "status" to one of new|in_progress|resolved|wontfix '
		. '("resolved" = done) and optionally write what you did in "resolution". '
		. 'Do NOT change "id". Re-import this exact file at wp-admin -> Feedback -> Export / Import to apply your edits.';
}

/** Is $filter the "still actionable" pseudo-filter? */
function iwpfb_is_open_filter( $filter ) {
	return in_array( $filter, array( 'unresolved', 'open' ), true );
}

/** Build the meta_query clause for a status/open filter (or array() for "all"). */
function iwpfb_status_meta_query( $filter ) {
	if ( iwpfb_is_open_filter( $filter ) ) {
		return array(
			'relation' => 'OR',
			array( 'key' => IWPFB_META_PREFIX . 'status', 'value' => array( 'resolved', 'wontfix' ), 'compare' => 'NOT IN' ),
			array( 'key' => IWPFB_META_PREFIX . 'status', 'compare' => 'NOT EXISTS' ),
		);
	}
	if ( $filter && array_key_exists( $filter, iwpfb_statuses() ) ) {
		return array( 'key' => IWPFB_META_PREFIX . 'status', 'value' => $filter );
	}
	return array();
}

/** Display label for a filter ('' / status key / open). */
function iwpfb_filter_label( $filter ) {
	if ( iwpfb_is_open_filter( $filter ) ) {
		return __( 'Unresolved', 'instawp-feedback' );
	}
	$s = iwpfb_statuses();
	return isset( $s[ $filter ] ) ? $s[ $filter ] : '';
}

/* --------------------------------------------------- page reference resolving */

/**
 * Resolve a human-supplied page reference to the normalized path we store in
 * _iwpfb_path. Accepts a full URL, a scheme-relative URL, a path, or a bare slug:
 *
 *   https://host/agency-program/?x=1  -> /agency-program
 *   //host/features/hosting           -> /features/hosting
 *   /agency-program/                  -> /agency-program
 *   agency-program                    -> /agency-program
 *   use-cases/agency-program          -> /use-cases/agency-program
 *
 * (iwpfb_norm_path() alone mangles a full URL into "/https:/host/...", which is why
 *  passing a URL to --page used to match nothing.)
 */
function iwpfb_resolve_path( $ref ) {
	$ref = trim( (string) $ref );
	if ( '' === $ref ) {
		return '/';
	}
	// Full or scheme-relative URL -> keep only the path component.
	if ( preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $ref ) || 0 === strpos( $ref, '//' ) ) {
		$p   = wp_parse_url( $ref, PHP_URL_PATH );
		$ref = ( null === $p || '' === $p ) ? '/' : $p;
	}
	// Drop any query string / fragment left on a bare path.
	$ref = preg_replace( '/[?#].*$/', '', $ref );
	return iwpfb_norm_path( $ref );
}

/** Last non-empty path segment (a page "slug"); '' for the home path. */
function iwpfb_page_slug( $path ) {
	$path = trim( iwpfb_norm_path( $path ), '/' );
	if ( '' === $path ) {
		return '';
	}
	$parts = explode( '/', $path );
	return end( $parts );
}

/**
 * Feedback post IDs for one page, resolved from a URL / path / slug. $mode:
 *   'path' : exact normalized-path match (precise; the default for --page).
 *   'slug' : any note whose page slug (last path segment) matches — catches a page
 *            whose URL moved (e.g. /x and /section/x) so nothing is orphaned.
 *   'auto' : exact path first; if that finds nothing, fall back to slug.
 * $filter is the usual status/unresolved filter ('' = any). IDs are date-ASC.
 */
function iwpfb_page_item_ids( $ref, $mode = 'path', $filter = '' ) {
	$path   = iwpfb_resolve_path( $ref );
	$status = iwpfb_status_meta_query( $filter );
	$base   = array(
		'post_type'      => IWPFB_PT,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);

	// Exact-path pass.
	$path_clause        = array( 'key' => IWPFB_META_PREFIX . 'path', 'value' => $path );
	$args               = $base;
	$args['meta_query'] = $status ? array( 'relation' => 'AND', $path_clause, $status ) : array( $path_clause );
	$exact              = get_posts( $args );

	if ( 'path' === $mode || ( 'auto' === $mode && ! empty( $exact ) ) ) {
		return $exact;
	}

	// Slug pass — filter in PHP for precision (the dataset is small; avoids a fragile
	// SQL LIKE that would also match /agency-program-old etc.).
	$slug = iwpfb_page_slug( $path );
	if ( '' === $slug ) {
		return $exact; // home path: no meaningful slug fallback.
	}
	$args = $base;
	if ( $status ) {
		$args['meta_query'] = array( $status );
	}
	$hits = array();
	foreach ( get_posts( $args ) as $id ) {
		if ( iwpfb_page_slug( iwpfb_get( $id, 'path', '/' ) ) === $slug ) {
			$hits[] = $id;
		}
	}
	return $hits;
}

/**
 * Collect feedback grouped by page, honoring $filter (see file header). Pass
 * $only_ids (an array of post IDs, e.g. from iwpfb_page_item_ids()) to restrict the
 * export to one page; an empty array means "no matches" and yields no pages (guarding
 * the WP footgun where post__in => [] is treated as "no restriction").
 */
function iwpfb_collect( $filter = '', $only_ids = null ) {
	if ( is_array( $only_ids ) && empty( $only_ids ) ) {
		return array();
	}
	$args = array(
		'post_type'      => IWPFB_PT,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	);
	$clause = iwpfb_status_meta_query( $filter );
	if ( $clause ) {
		$args['meta_query'] = array( $clause );
	}
	if ( is_array( $only_ids ) ) {
		$args['post__in'] = $only_ids;
	}

	$q     = new WP_Query( $args );
	$pages = array();
	foreach ( $q->posts as $p ) {
		$id   = $p->ID;
		$path = iwpfb_get( $id, 'path', '/' );
		if ( ! isset( $pages[ $path ] ) ) {
			$pages[ $path ] = array(
				'path'  => $path,
				'url'   => iwpfb_get( $id, 'url', home_url( $path ) ),
				'title' => iwpfb_get( $id, 'page_title', '' ),
				'items' => array(),
			);
		}
		$pages[ $path ]['items'][] = array(
			'id'         => $id,
			'status'     => iwpfb_clean_status( iwpfb_get( $id, 'status', 'new' ) ),
			'resolution' => iwpfb_get( $id, 'resolution', '' ),
			'type'       => iwpfb_clean_type( iwpfb_get( $id, 'type', 'other' ) ),
			'from'       => iwpfb_get( $id, 'name', '' ),
			'comment'    => $p->post_content,
			'replies'    => array_map(
				function ( $rp ) {
					$f = iwpfb_format_reply( $rp );
					return array( 'from' => $f['name'], 'team' => $f['admin'], 'text' => $f['text'], 'date' => $f['date'] );
				},
				iwpfb_get_replies( $id )
			),
			'location'   => array(
				'element'  => iwpfb_get( $id, 'element', '' ),
				'selector' => iwpfb_get( $id, 'selector', '' ),
				'pin'      => round( (float) iwpfb_get( $id, 'rel_x', 0 ) * 100 ) . '% / ' . round( (float) iwpfb_get( $id, 'rel_y', 0 ) * 100 ) . '%',
			),
			'date'       => get_the_date( 'Y-m-d H:i', $p ),
		);
	}
	return array_values( $pages );
}

/** Total item count across page groups. */
function iwpfb_count_items( $pages ) {
	$n = 0;
	foreach ( $pages as $pg ) {
		$n += count( $pg['items'] );
	}
	return $n;
}

/** The full export payload (JSON shape) for a filter (optionally one page via $only_ids). */
function iwpfb_build_export( $filter = '', $only_ids = null ) {
	$pages = iwpfb_collect( $filter, $only_ids );
	return array(
		'_format'       => 'instawp-feedback/v1',
		'_instructions' => iwpfb_export_instructions(),
		'exported_at'   => current_time( 'mysql' ),
		'site_url'      => home_url( '/' ),
		'filter'        => $filter ? $filter : 'all',
		'statuses'      => array_keys( iwpfb_statuses() ),
		'types'         => array_keys( iwpfb_types() ),
		'count'         => iwpfb_count_items( $pages ),
		'pages'         => $pages,
	);
}

/** Read-only Markdown view (a tidy checklist grouped by page). */
function iwpfb_export_markdown( $pages, $filter = '' ) {
	$labels = array_merge( iwpfb_statuses(), iwpfb_types() );
	$flabel = iwpfb_filter_label( $filter );
	$out    = '# InstaWP feedback' . ( $flabel ? ' (' . $flabel . ')' : '' ) . "\n\n";
	$out   .= '> ' . iwpfb_export_instructions() . " (Note: this Markdown view is read-only; re-import the JSON file to apply changes.)\n\n";
	$out   .= '_Exported ' . esc_html( current_time( 'mysql' ) ) . ' · ' . iwpfb_count_items( $pages ) . " item(s)._\n\n";

	foreach ( $pages as $pg ) {
		$out .= '## ' . ( $pg['title'] ? $pg['title'] . ' — ' : '' ) . $pg['path'] . "\n";
		$out .= '<' . $pg['url'] . ">\n\n";
		foreach ( $pg['items'] as $it ) {
			$done = in_array( $it['status'], array( 'resolved', 'wontfix' ), true );
			$out .= '- [' . ( $done ? 'x' : ' ' ) . '] **#' . $it['id'] . '** '
				. '`' . ( $labels[ $it['type'] ] ?? $it['type'] ) . '` · ' . $it['from'] . ' · ' . $it['date']
				. ' · _' . ( $labels[ $it['status'] ] ?? $it['status'] ) . "_\n";
			$out .= '  > ' . str_replace( "\n", "\n  > ", trim( $it['comment'] ) ) . "\n";
			if ( $it['location']['element'] ) {
				$out .= '  _at:_ `' . $it['location']['element'] . '` (' . $it['location']['pin'] . ")\n";
			}
			if ( $it['resolution'] ) {
				$out .= '  _resolution:_ ' . $it['resolution'] . "\n";
			}
			$out .= "\n";
		}
	}
	return $out;
}

/**
 * Apply a decoded export back onto feedback posts. Accepts the full export shape
 * ({ pages:[{items:[]}] }), an { items:[] } shape, or a bare array of items. Matches
 * by id; updates status + resolution only. $apply=false previews (counts, no writes).
 * Returns array( updated, skipped ).
 */
function iwpfb_apply_import( $json, $apply = true ) {
	$items = array();
	if ( ! empty( $json['pages'] ) && is_array( $json['pages'] ) ) {
		foreach ( $json['pages'] as $pg ) {
			if ( ! empty( $pg['items'] ) && is_array( $pg['items'] ) ) {
				$items = array_merge( $items, $pg['items'] );
			}
		}
	} elseif ( ! empty( $json['items'] ) && is_array( $json['items'] ) ) {
		$items = $json['items'];
	} elseif ( isset( $json[0] ) ) {
		$items = $json;
	}

	$updated = 0;
	$skipped = 0;
	foreach ( $items as $it ) {
		$id = isset( $it['id'] ) ? (int) $it['id'] : 0;
		if ( ! $id || get_post_type( $id ) !== IWPFB_PT ) {
			$skipped++;
			continue;
		}
		$changed = false;
		if ( isset( $it['status'] ) ) {
			if ( $apply ) {
				iwpfb_set( $id, 'status', iwpfb_clean_status( $it['status'] ) );
			}
			$changed = true;
		}
		if ( array_key_exists( 'resolution', $it ) ) {
			if ( $apply ) {
				iwpfb_set( $id, 'resolution', sanitize_textarea_field( (string) $it['resolution'] ) );
			}
			$changed = true;
		}
		if ( $changed ) {
			$updated++;
		} else {
			$skipped++;
		}
	}

	return array( 'updated' => $updated, 'skipped' => $skipped );
}
