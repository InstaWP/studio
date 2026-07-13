<?php
/**
 * Full-page shell for a source-rendered page. Loaded via the template_include
 * filter (bypasses the theme's templates). Emits doctype/head (wp_head), the
 * #site-nav / #site-footer mounts chrome.js fills, and the rendered page body.
 */
defined( 'ABSPATH' ) || exit;
$slug = instawp_homebuild_slug();
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

<?php echo instawp_render_homebuild( $slug ); // trusted source HTML ?>

<div id="site-footer"></div>

<?php wp_footer(); ?>
</body>
</html>
