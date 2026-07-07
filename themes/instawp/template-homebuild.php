<?php
/**
 * Template Name: Home-Build Marketing Page
 *
 * Standalone shell for variations/home-build marketing pages. Renders the page
 * BODY straight from the source file (instawp_render_homebuild), wrapped in the
 * shared site-head / site-foot parts (the #site-nav / #site-footer mounts that
 * chrome.js fills). No block editor. Assigned via the template_include filter
 * in functions.php. The blog uses its own classic templates (single.php, etc.).
 */
$slug = instawp_homebuild_slug();
get_template_part( 'template-parts/site-head' );

echo instawp_render_homebuild( $slug ); // trusted source HTML

get_template_part( 'template-parts/site-foot' );
