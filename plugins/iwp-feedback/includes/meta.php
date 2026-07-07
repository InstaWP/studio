<?php
/**
 * Meta prefix + tiny get/set helpers, plus the canonical type/status vocabularies.
 * One feedback post = one note; the note body is post_content, everything else is meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IWPFB_META_PREFIX', '_iwpfb_' );

/** Read one prefixed meta value (with a default). */
function iwpfb_get( $post_id, $key, $default = '' ) {
	$v = get_post_meta( $post_id, IWPFB_META_PREFIX . $key, true );
	return ( '' === $v || null === $v ) ? $default : $v;
}

/** Write one prefixed meta value. */
function iwpfb_set( $post_id, $key, $value ) {
	return update_post_meta( $post_id, IWPFB_META_PREFIX . $key, $value );
}

/** Allowed feedback types (value => label). Filterable. */
function iwpfb_types() {
	return apply_filters( 'iwpfb_types', array(
		'bug'      => __( 'Bug', 'instawp-feedback' ),
		'idea'     => __( 'Idea', 'instawp-feedback' ),
		'copy'     => __( 'Copy', 'instawp-feedback' ),
		'design'   => __( 'Design', 'instawp-feedback' ),
		'question' => __( 'Question', 'instawp-feedback' ),
		'other'    => __( 'Other', 'instawp-feedback' ),
	) );
}

/** Triage statuses (value => label). */
function iwpfb_statuses() {
	return apply_filters( 'iwpfb_statuses', array(
		'new'         => __( 'New', 'instawp-feedback' ),
		'in_progress' => __( 'In progress', 'instawp-feedback' ),
		'resolved'    => __( 'Resolved', 'instawp-feedback' ),
		'wontfix'     => __( "Won't fix", 'instawp-feedback' ),
	) );
}

/** Normalize an arbitrary value to a valid type (default: other). */
function iwpfb_clean_type( $value ) {
	$value = sanitize_key( (string) $value );
	return array_key_exists( $value, iwpfb_types() ) ? $value : 'other';
}

/** Normalize an arbitrary value to a valid status (default: new). */
function iwpfb_clean_status( $value ) {
	$value = sanitize_key( (string) $value );
	return array_key_exists( $value, iwpfb_statuses() ) ? $value : 'new';
}
