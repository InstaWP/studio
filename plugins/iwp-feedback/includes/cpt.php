<?php
/**
 * The `feedback` post type. Private: no public single/archive, excluded from search,
 * not in the default REST surface (we expose our own gated routes instead). It exists
 * purely so the team can triage notes in wp-admin -> Feedback.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the feedback post type. Called on init and on activation. */
function iwpfb_register_post_types() {
	register_post_type( IWPFB_PT, array(
		'labels'              => array(
			'name'               => __( 'Feedback', 'instawp-feedback' ),
			'singular_name'      => __( 'Feedback', 'instawp-feedback' ),
			'menu_name'          => __( 'Feedback', 'instawp-feedback' ),
			'all_items'          => __( 'All Feedback', 'instawp-feedback' ),
			'edit_item'          => __( 'Feedback', 'instawp-feedback' ),
			'view_item'          => __( 'View Feedback', 'instawp-feedback' ),
			'search_items'       => __( 'Search Feedback', 'instawp-feedback' ),
			'not_found'          => __( 'No feedback yet.', 'instawp-feedback' ),
			'not_found_in_trash' => __( 'No feedback in trash.', 'instawp-feedback' ),
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_admin_bar'   => false,
		'show_in_nav_menus'   => false,
		'show_in_rest'        => false, // classic editor; our own REST routes handle the front end
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'menu_icon'           => 'dashicons-format-status',
		'menu_position'       => 26,
		'supports'            => array( 'title', 'editor' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	) );
}
add_action( 'init', 'iwpfb_register_post_types' );

/** Block the front end from ever rendering a feedback note as a page. */
add_action( 'template_redirect', function () {
	if ( is_singular( IWPFB_PT ) ) {
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}
} );
