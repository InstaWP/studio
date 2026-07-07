<?php
/**
 * WP-CLI surface: `wp iwpfb <command>`. Full CRUD + export/import for feedback notes,
 * plus reply + test helpers. The data layer is in exchange-core.php (loaded first).
 *
 *   CRUD     : create · get · list · update · delete
 *   thread   : reply
 *   batch    : export [--unresolved] · import
 *   test     : sample · clear · flush
 *
 * NB: the sample/list "page path" option is --page= (not --path=, a WP-CLI global flag).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	class IWPFB_CLI {

		/** A feedback post must exist or we bail. */
		private function require_note( $id ) {
			$id = (int) $id;
			if ( ! $id || get_post_type( $id ) !== IWPFB_PT ) {
				WP_CLI::error( "No feedback note with ID {$id}." );
			}
			return $id;
		}

		/** One feedback note as a flat row for tables/csv. */
		private function row( $id ) {
			$p = get_post( $id );
			return array(
				'id'      => (int) $id,
				'status'  => iwpfb_clean_status( iwpfb_get( $id, 'status', 'new' ) ),
				'type'    => iwpfb_clean_type( iwpfb_get( $id, 'type', 'other' ) ),
				'from'    => iwpfb_get( $id, 'name', '' ),
				'page'    => iwpfb_get( $id, 'path', '/' ),
				'comment' => $p ? wp_trim_words( $p->post_content, 14, '…' ) : '',
				'replies' => count( iwpfb_get_replies( $id ) ),
				'date'    => $p ? get_the_date( 'Y-m-d H:i', $p ) : '',
			);
		}

		/**
		 * Create a feedback note (no pin location — CLI notes are page-level).
		 *
		 * ## OPTIONS
		 * --message=<text>  : the feedback (required)
		 * [--name=<name>]   : who left it (default: CLI)
		 * [--type=<type>]   : bug|idea|copy|design|question|other (default: other)
		 * [--page=<path>]   : page path it's about (default: /)
		 * [--url=<url>]     : full page URL (default: home_url(page))
		 * [--status=<s>]    : new|in_progress|resolved|wontfix (default: new)
		 * [--porcelain]     : output only the new ID
		 *
		 * ## EXAMPLES
		 *     wp iwpfb create --message="Hero headline is weak" --type=copy --page=/ --name="Vikas"
		 */
		public function create( $args, $assoc ) {
			$msg = isset( $assoc['message'] ) ? trim( (string) $assoc['message'] ) : '';
			if ( '' === $msg ) {
				WP_CLI::error( '--message is required.' );
			}
			$path = iwpfb_norm_path( isset( $assoc['page'] ) ? $assoc['page'] : '/' );
			$id   = wp_insert_post( array(
				'post_type'    => IWPFB_PT,
				'post_status'  => 'publish',
				'post_title'   => wp_trim_words( $msg, 12, '…' ),
				'post_content' => sanitize_textarea_field( $msg ),
			), true );
			if ( is_wp_error( $id ) ) {
				WP_CLI::error( $id->get_error_message() );
			}
			iwpfb_set( $id, 'name', sanitize_text_field( isset( $assoc['name'] ) ? $assoc['name'] : 'CLI' ) );
			iwpfb_set( $id, 'type', iwpfb_clean_type( isset( $assoc['type'] ) ? $assoc['type'] : 'other' ) );
			iwpfb_set( $id, 'status', iwpfb_clean_status( isset( $assoc['status'] ) ? $assoc['status'] : 'new' ) );
			iwpfb_set( $id, 'path', $path );
			iwpfb_set( $id, 'url', isset( $assoc['url'] ) ? esc_url_raw( $assoc['url'] ) : home_url( $path ) );
			iwpfb_set( $id, 'submitted_at', current_time( 'mysql' ) );

			if ( isset( $assoc['porcelain'] ) ) {
				WP_CLI::line( (string) $id );
				return;
			}
			WP_CLI::success( "Created feedback note #{$id}." );
		}

		/**
		 * Show one feedback note (with its replies).
		 *
		 * ## OPTIONS
		 * <id>             : the note ID
		 * [--format=<fmt>] : table (default) or json
		 *
		 * ## EXAMPLES
		 *     wp iwpfb get 21737
		 *     wp iwpfb get 21737 --format=json
		 */
		public function get( $args, $assoc ) {
			$id = $this->require_note( $args[0] );
			$p  = get_post( $id );
			$data = array(
				'id'         => $id,
				'status'     => iwpfb_clean_status( iwpfb_get( $id, 'status', 'new' ) ),
				'type'       => iwpfb_clean_type( iwpfb_get( $id, 'type', 'other' ) ),
				'from'       => iwpfb_get( $id, 'name', '' ),
				'page'       => iwpfb_get( $id, 'path', '/' ),
				'url'        => iwpfb_get( $id, 'url', '' ),
				'element'    => iwpfb_get( $id, 'element', '' ),
				'pin'        => round( (float) iwpfb_get( $id, 'rel_x', 0 ) * 100 ) . '% / ' . round( (float) iwpfb_get( $id, 'rel_y', 0 ) * 100 ) . '%',
				'date'       => get_the_date( 'Y-m-d H:i', $p ),
				'comment'    => $p->post_content,
				'resolution' => iwpfb_get( $id, 'resolution', '' ),
				'replies'    => array_map( 'iwpfb_format_reply', iwpfb_get_replies( $id ) ),
			);

			if ( isset( $assoc['format'] ) && 'json' === $assoc['format'] ) {
				WP_CLI::line( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
				return;
			}
			foreach ( array( 'id', 'status', 'type', 'from', 'page', 'url', 'date', 'element', 'pin' ) as $k ) {
				WP_CLI::line( str_pad( $k . ':', 12 ) . $data[ $k ] );
			}
			WP_CLI::line( "comment:\n  " . str_replace( "\n", "\n  ", trim( $data['comment'] ) ) );
			if ( $data['resolution'] ) {
				WP_CLI::line( "resolution:\n  " . str_replace( "\n", "\n  ", trim( $data['resolution'] ) ) );
			}
			if ( $data['replies'] ) {
				WP_CLI::line( 'replies (' . count( $data['replies'] ) . '):' );
				foreach ( $data['replies'] as $rp ) {
					WP_CLI::line( '  - ' . $rp['name'] . ( $rp['admin'] ? ' [Team]' : '' ) . ' (' . $rp['date'] . '): ' . $rp['text'] );
				}
			}
		}

		/**
		 * List feedback notes.
		 *
		 * ## OPTIONS
		 * [--status=<s>]   : filter by status (new|in_progress|resolved|wontfix), or "unresolved"
		 * [--unresolved]   : shorthand for --status=unresolved (not resolved / wontfix)
		 * [--type=<type>]  : filter by type
		 * [--page=<ref>]   : filter by page — accepts a full URL, path, or bare slug
		 * [--match=<m>]    : how --page matches: path (exact, default) | slug | auto
		 * [--fields=<f>]   : comma list (default: id,status,type,from,page,comment,replies,date)
		 * [--format=<fmt>] : table (default) | json | csv | ids | count
		 *
		 * ## EXAMPLES
		 *     wp iwpfb list
		 *     wp iwpfb list --unresolved
		 *     wp iwpfb list --status=new --type=copy
		 *     wp iwpfb list --page=https://site/agency-program/
		 *     wp iwpfb list --format=count
		 */
		public function list( $args, $assoc ) {
			$filter = '';
			if ( ! empty( $assoc['unresolved'] ) ) {
				$filter = 'unresolved';
			} elseif ( ! empty( $assoc['status'] ) ) {
				$filter = sanitize_key( $assoc['status'] );
			}

			$clauses = array();
			$sclause = iwpfb_status_meta_query( $filter );
			if ( $sclause ) {
				$clauses[] = $sclause;
			}
			if ( ! empty( $assoc['type'] ) ) {
				$clauses[] = array( 'key' => IWPFB_META_PREFIX . 'type', 'value' => sanitize_key( $assoc['type'] ) );
			}

			// --page accepts a URL / path / slug; --match widens it (default: exact path).
			$page_ids = null;
			if ( isset( $assoc['page'] ) ) {
				$match    = isset( $assoc['match'] ) ? sanitize_key( $assoc['match'] ) : 'path';
				$page_ids = iwpfb_page_item_ids( $assoc['page'], $match, '' );
			}

			$qargs = array(
				'post_type'      => IWPFB_PT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			);
			if ( $clauses ) {
				if ( count( $clauses ) > 1 ) {
					$clauses['relation'] = 'AND';
				}
				$qargs['meta_query'] = $clauses;
			}
			if ( is_array( $page_ids ) && empty( $page_ids ) ) {
				$ids = array(); // page filter matched nothing (never let post__in => [] mean "all")
			} else {
				if ( is_array( $page_ids ) ) {
					$qargs['post__in'] = $page_ids;
				}
				$ids = get_posts( $qargs );
			}

			$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
			if ( 'count' === $format ) {
				WP_CLI::line( (string) count( $ids ) );
				return;
			}
			if ( 'ids' === $format ) {
				WP_CLI::line( implode( ' ', $ids ) );
				return;
			}
			$rows   = array_map( array( $this, 'row' ), $ids );
			$fields = isset( $assoc['fields'] )
				? array_map( 'trim', explode( ',', $assoc['fields'] ) )
				: array( 'id', 'status', 'type', 'from', 'page', 'comment', 'replies', 'date' );
			WP_CLI\Utils\format_items( $format, $rows, $fields );
		}

		/**
		 * Show every feedback note for ONE page, resolved from a URL, path, or slug.
		 * The easy way to pull a page's feedback without knowing its exact stored path
		 * (a full URL, a path, or a bare slug all work).
		 *
		 * ## OPTIONS
		 * <ref>            : page URL, path, or slug (e.g. https://site/agency-program/, /agency-program, agency-program)
		 * [--match=<m>]    : auto (default) | path (exact) | slug (any path ending in this slug)
		 * [--status=<s>]   : filter by status (new|in_progress|resolved|wontfix), or "unresolved"
		 * [--unresolved]   : shorthand for --status=unresolved
		 * [--type=<type>]  : filter by type
		 * [--fields=<f>]   : comma list (default: id,status,type,from,page,comment,replies,date)
		 * [--format=<fmt>] : table (default) | json | csv | ids | count
		 *
		 * ## EXAMPLES
		 *     wp iwpfb page /agency-program
		 *     wp iwpfb page https://instawp-marketing.instawp.site/agency-program/
		 *     wp iwpfb page agency-program --unresolved
		 *     wp iwpfb page agency-program --format=json
		 */
		public function page( $args, $assoc ) {
			$ref = isset( $args[0] ) ? (string) $args[0] : '';
			if ( '' === trim( $ref ) ) {
				WP_CLI::error( 'Pass a page URL, path, or slug. e.g. wp iwpfb page /agency-program' );
			}
			$match = isset( $assoc['match'] ) ? sanitize_key( $assoc['match'] ) : 'auto';
			if ( ! in_array( $match, array( 'auto', 'path', 'slug' ), true ) ) {
				WP_CLI::error( '--match must be one of: auto, path, slug.' );
			}
			$filter = '';
			if ( ! empty( $assoc['unresolved'] ) ) {
				$filter = 'unresolved';
			} elseif ( ! empty( $assoc['status'] ) ) {
				$filter = sanitize_key( $assoc['status'] );
			}

			$ids = iwpfb_page_item_ids( $ref, $match, $filter );
			if ( ! empty( $assoc['type'] ) ) {
				$type = sanitize_key( $assoc['type'] );
				$ids  = array_values( array_filter( $ids, function ( $id ) use ( $type ) {
					return iwpfb_clean_type( iwpfb_get( $id, 'type', 'other' ) ) === $type;
				} ) );
			}

			$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
			if ( 'count' === $format ) {
				WP_CLI::line( (string) count( $ids ) );
				return;
			}
			if ( 'ids' === $format ) {
				WP_CLI::line( implode( ' ', $ids ) );
				return;
			}
			$rows   = array_map( array( $this, 'row' ), $ids );
			$fields = isset( $assoc['fields'] )
				? array_map( 'trim', explode( ',', $assoc['fields'] ) )
				: array( 'id', 'status', 'type', 'from', 'page', 'comment', 'replies', 'date' );
			if ( empty( $rows ) && ! in_array( $format, array( 'json', 'csv' ), true ) ) {
				WP_CLI::log( 'No feedback found for ' . iwpfb_resolve_path( $ref ) . ( 'path' === $match ? '' : " (match: {$match})" ) . '.' );
				return;
			}
			WP_CLI\Utils\format_items( $format, $rows, $fields );
		}

		/**
		 * Update fields on a note.
		 *
		 * ## OPTIONS
		 * <id>                 : the note ID
		 * [--status=<s>]       : new|in_progress|resolved|wontfix
		 * [--type=<type>]      : bug|idea|copy|design|question|other
		 * [--resolution=<txt>] : the resolution note
		 * [--message=<txt>]    : replace the comment body
		 * [--name=<name>]      : who left it
		 *
		 * ## EXAMPLES
		 *     wp iwpfb update 21737 --status=resolved --resolution="Rewrote the headline"
		 */
		public function update( $args, $assoc ) {
			$id  = $this->require_note( $args[0] );
			$did = array();
			if ( isset( $assoc['status'] ) ) {
				iwpfb_set( $id, 'status', iwpfb_clean_status( $assoc['status'] ) );
				$did[] = 'status';
			}
			if ( isset( $assoc['type'] ) ) {
				iwpfb_set( $id, 'type', iwpfb_clean_type( $assoc['type'] ) );
				$did[] = 'type';
			}
			if ( isset( $assoc['resolution'] ) ) {
				iwpfb_set( $id, 'resolution', sanitize_textarea_field( (string) $assoc['resolution'] ) );
				$did[] = 'resolution';
			}
			if ( isset( $assoc['name'] ) ) {
				iwpfb_set( $id, 'name', sanitize_text_field( (string) $assoc['name'] ) );
				$did[] = 'name';
			}
			if ( isset( $assoc['message'] ) ) {
				$m = sanitize_textarea_field( (string) $assoc['message'] );
				wp_update_post( array( 'ID' => $id, 'post_content' => $m, 'post_title' => wp_trim_words( $m, 12, '…' ) ) );
				$did[] = 'message';
			}
			if ( ! $did ) {
				WP_CLI::error( 'Nothing to update. Pass --status / --type / --resolution / --message / --name.' );
			}
			WP_CLI::success( "Updated #{$id} (" . implode( ', ', $did ) . ').' );
		}

		/**
		 * Delete one or more notes (trashed by default; recoverable in wp-admin).
		 *
		 * ## OPTIONS
		 * <id>...    : one or more note IDs
		 * [--force]  : permanently delete instead of trashing
		 *
		 * ## EXAMPLES
		 *     wp iwpfb delete 21737
		 *     wp iwpfb delete 21737 21738 --force
		 */
		public function delete( $args, $assoc ) {
			if ( ! $args ) {
				WP_CLI::error( 'Pass one or more note IDs.' );
			}
			$force = ! empty( $assoc['force'] );
			$n     = 0;
			foreach ( $args as $a ) {
				$id = (int) $a;
				if ( get_post_type( $id ) !== IWPFB_PT ) {
					WP_CLI::warning( "Skipped {$a} (not a feedback note)." );
					continue;
				}
				if ( $force ) {
					wp_delete_post( $id, true );
				} else {
					wp_trash_post( $id );
				}
				$n++;
			}
			WP_CLI::success( ( $force ? 'Deleted ' : 'Trashed ' ) . $n . ' note(s).' );
		}

		/**
		 * Add a reply to a note's thread.
		 *
		 * ## OPTIONS
		 * <id>            : the note ID
		 * --text=<text>   : the reply (required)
		 * [--name=<name>] : who's replying (default: Team with --team, else CLI)
		 * [--team]        : mark the reply as a Team/admin reply (badged)
		 *
		 * ## EXAMPLES
		 *     wp iwpfb reply 21737 --text="Fixed, please re-check" --team
		 */
		public function reply( $args, $assoc ) {
			$id   = $this->require_note( $args[0] );
			$text = isset( $assoc['text'] ) ? trim( (string) $assoc['text'] ) : '';
			if ( '' === $text ) {
				WP_CLI::error( '--text is required.' );
			}
			$team = ! empty( $assoc['team'] );
			$name = isset( $assoc['name'] ) ? sanitize_text_field( $assoc['name'] ) : ( $team ? 'Team' : 'CLI' );
			iwpfb_add_reply( $id, $name, $text, '', $team );
			WP_CLI::success( "Reply added to #{$id}." );
		}

		/**
		 * Export feedback as JSON (re-importable) or Markdown (read-only).
		 *
		 * ## OPTIONS
		 * [--status=<s>]   : limit to a status, or "unresolved"
		 * [--unresolved]   : shorthand for --status=unresolved
		 * [--page=<ref>]   : limit to one page — accepts a full URL, path, or bare slug
		 * [--match=<m>]    : how --page matches: auto (default) | path (exact) | slug
		 * [--format=<fmt>] : json (default) | md
		 * [--file=<path>]  : write to a file; otherwise prints to STDOUT
		 *
		 * ## EXAMPLES
		 *     wp iwpfb export --unresolved --file=feedback.json
		 *     wp iwpfb export --page=https://site/agency-program/ --file=agency.json
		 *     wp iwpfb export --status=new --format=md
		 *     wp iwpfb export > all.json
		 */
		public function export( $args, $assoc ) {
			$filter = '';
			if ( ! empty( $assoc['unresolved'] ) ) {
				$filter = 'unresolved';
			} elseif ( ! empty( $assoc['status'] ) ) {
				$filter = sanitize_key( $assoc['status'] );
			}
			$format = isset( $assoc['format'] ) ? sanitize_key( $assoc['format'] ) : 'json';

			// Optional single-page scope (URL / path / slug).
			$only_ids = null;
			if ( isset( $assoc['page'] ) ) {
				$match    = isset( $assoc['match'] ) ? sanitize_key( $assoc['match'] ) : 'auto';
				$only_ids = iwpfb_page_item_ids( $assoc['page'], $match, '' );
			}

			if ( 'md' === $format || 'markdown' === $format ) {
				$out = iwpfb_export_markdown( iwpfb_collect( $filter, $only_ids ), $filter );
			} else {
				$out = wp_json_encode( iwpfb_build_export( $filter, $only_ids ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}

			if ( ! empty( $assoc['file'] ) ) {
				$bytes = file_put_contents( $assoc['file'], $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				if ( false === $bytes ) {
					WP_CLI::error( 'Could not write ' . $assoc['file'] );
				}
				WP_CLI::success( 'Wrote ' . $assoc['file'] . ' (' . size_format( $bytes ) . ').' );
			} else {
				WP_CLI::line( $out );
			}
		}

		/**
		 * Import an edited JSON export (matches by id; applies status + resolution).
		 *
		 * ## OPTIONS
		 * <file>       : path to the JSON export
		 * [--dry-run]  : report what WOULD change without writing
		 *
		 * ## EXAMPLES
		 *     wp iwpfb import feedback.json
		 *     wp iwpfb import feedback.json --dry-run
		 */
		public function import( $args, $assoc ) {
			$file = isset( $args[0] ) ? $args[0] : '';
			if ( ! $file || ! file_exists( $file ) ) {
				WP_CLI::error( 'File not found: ' . $file );
			}
			$raw  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$json = json_decode( (string) $raw, true );
			if ( ! is_array( $json ) ) {
				WP_CLI::error( 'Not a valid JSON feedback export.' );
			}
			$dry = ! empty( $assoc['dry-run'] );
			$res = iwpfb_apply_import( $json, ! $dry );
			if ( $dry ) {
				WP_CLI::success( "Dry run: {$res['updated']} item(s) WOULD be updated, {$res['skipped']} skipped." );
			} else {
				WP_CLI::success( "Import complete: {$res['updated']} updated, {$res['skipped']} skipped." );
			}
		}

		/**
		 * Seed sample feedback notes (for testing).
		 *
		 * ## OPTIONS
		 * [--count=<n>] : how many (default 5)
		 * [--page=<p>]  : page path to attach them to (default /). NB: not --path (a WP-CLI global flag).
		 *
		 * ## EXAMPLES
		 *     wp iwpfb sample --count=8 --page=/pricing
		 */
		public function sample( $args, $assoc ) {
			$count = isset( $assoc['count'] ) ? max( 1, (int) $assoc['count'] ) : 5;
			$path  = isset( $assoc['page'] ) ? iwpfb_norm_path( $assoc['page'] ) : '/';
			$types = array_keys( iwpfb_types() );
			$names = array( 'Vikas', 'Aman', 'Priya', 'Sam', 'Jordan' );
			$notes = array(
				'The hero headline could be punchier — feels generic.',
				'This button colour is too close to the background.',
				'Typo here: "recieve" should be "receive".',
				'Love this section. Can we reuse it on the pricing page?',
				'Spacing feels tight on mobile — worth checking.',
			);
			$n = 0;
			for ( $i = 0; $i < $count; $i++ ) {
				$msg = $notes[ $i % count( $notes ) ];
				$id  = wp_insert_post( array(
					'post_type'    => IWPFB_PT,
					'post_status'  => 'publish',
					'post_title'   => wp_trim_words( $msg, 12, '…' ),
					'post_content' => $msg,
				) );
				if ( $id && ! is_wp_error( $id ) ) {
					iwpfb_set( $id, 'name', $names[ $i % count( $names ) ] );
					iwpfb_set( $id, 'type', $types[ $i % count( $types ) ] );
					iwpfb_set( $id, 'status', 'new' );
					iwpfb_set( $id, 'path', $path );
					iwpfb_set( $id, 'url', home_url( $path ) );
					iwpfb_set( $id, 'rel_x', round( 0.2 + ( $i % 5 ) * 0.12, 3 ) );
					iwpfb_set( $id, 'rel_y', round( 0.15 + ( $i % 4 ) * 0.18, 3 ) );
					iwpfb_set( $id, 'page_x', 200 + $i * 40 );
					iwpfb_set( $id, 'page_y', 300 + $i * 120 );
					iwpfb_set( $id, 'submitted_at', current_time( 'mysql' ) );
					$n++;
				}
			}
			WP_CLI::success( "Seeded {$n} feedback note(s) on {$path}." );
		}

		/**
		 * Delete ALL feedback. CAUTION: real team notes live here — prefer `delete <id>`.
		 *
		 * ## OPTIONS
		 * [--yes] : skip the confirmation prompt
		 */
		public function clear( $args, $assoc ) {
			WP_CLI::confirm( 'Delete ALL feedback notes (including real ones)?', $assoc );
			$ids = get_posts( array(
				'post_type'      => IWPFB_PT,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			) );
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
			WP_CLI::success( 'Deleted ' . count( $ids ) . ' feedback note(s).' );
		}

		/** Flush rewrite rules. */
		public function flush() {
			flush_rewrite_rules();
			WP_CLI::success( 'Rewrite rules flushed.' );
		}
	}

	WP_CLI::add_command( 'iwpfb', 'IWPFB_CLI' );
}
