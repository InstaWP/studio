<?php
/**
 * WP-CLI:  wp instastudio pages
 * Create a published WP page for every source .html that lacks one (nested slugs
 * get ancestor pages), and set the `home` page as the front page. --dry-run supported.
 */
defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	class InstaStudio_CLI {

		/**
		 * Sync WordPress pages from the source HTML files.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Show what would be created without changing anything.
		 *
		 * @when after_wp_load
		 */
		public function pages( $args, $assoc ) {
			$dry     = isset( $assoc['dry-run'] );
			$map     = instawp_homebuild_pages();
			$created = 0;
			$exists  = 0;

			if ( ! $map ) {
				\WP_CLI::warning( 'No pages found. Is INSTAWP_HB_DIR (' . INSTAWP_HB_DIR . ') present and full of .html files?' );
				return;
			}

			foreach ( $map as $slug => $file ) {
				$path = ( 'home' === $slug ) ? 'home' : $slug;
				list( $id, $made ) = $this->ensure_page( $path, $dry );
				if ( $made ) {
					$created++;
					\WP_CLI::log( ( $dry ? '[dry] ' : '' ) . "page  $path  <-  $file" );
				} else {
					$exists++;
				}
			}

			$front = '';
			if ( isset( $map['home'] ) ) {
				$home = get_page_by_path( 'home' );
				if ( $home ) {
					if ( ! $dry ) {
						update_option( 'show_on_front', 'page' );
						update_option( 'page_on_front', $home->ID );
					}
					$front = '  |  front page = home';
				}
			}

			if ( ! $dry ) {
				flush_rewrite_rules( false );
			}
			\WP_CLI::success( "pages: {$created} created, {$exists} already existed{$front}" . ( $dry ? '  (dry run)' : '' ) );
		}

		private function ensure_page( $path, $dry ) {
			$existing = get_page_by_path( $path );
			if ( $existing ) {
				return array( $existing->ID, false );
			}
			$made   = false;
			$parent = 0;
			$accum  = '';
			foreach ( explode( '/', $path ) as $seg ) {
				$accum = $accum ? "$accum/$seg" : $seg;
				$node  = get_page_by_path( $accum );
				if ( $node ) {
					$parent = $node->ID;
					continue;
				}
				$made  = true;
				$title = ucwords( str_replace( '-', ' ', 'home' === $seg ? 'Home' : $seg ) );
				if ( $dry ) {
					$parent = -1;
					continue;
				}
				$parent = wp_insert_post( array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_name'   => $seg,
					'post_parent' => max( 0, (int) $parent ),
				) );
			}
			return array( $parent, $made );
		}
	}

	\WP_CLI::add_command( 'instastudio', 'InstaStudio_CLI' );
}
