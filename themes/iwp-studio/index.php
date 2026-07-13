<?php
/* Minimal fallback. Source-rendered pages are handled by the InstaStudio plugin
   (via template_include); this only renders non-source requests. */
defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main style="max-width:680px;margin:80px auto;padding:0 24px;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#222;line-height:1.6">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
	<h1 style="letter-spacing:-.02em"><?php the_title(); ?></h1>
	<?php the_content(); ?>
<?php endwhile; else : ?>
	<p>Nothing here yet. Source-rendered pages are served by the InstaStudio plugin.</p>
<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
