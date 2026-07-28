<?php
defined( 'ABSPATH' ) || exit;
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'style', 'script', 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
	add_theme_support( 'automatic-feed-links' );
} );

// Structured blog: real WP posts rendered in the InstaStudio design.
// Pages are HTML files (the iwp-studio plugin); the blog is structured content here.
require_once __DIR__ . '/inc/blog.php';
