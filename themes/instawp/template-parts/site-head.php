<?php
/**
 * Shared top shell for all home-build pages (marketing + blog).
 * Doctype, head (wp_head), body open, and the #site-nav mount chrome.js fills.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="site-nav"></div>
