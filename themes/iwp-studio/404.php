<?php
/** 404 — dark header + search + back-home link, in the blog skin. */
defined( 'ABSPATH' ) || exit;
get_template_part( 'template-parts/site-head' );
$blog_id  = (int) get_option( 'page_for_posts' );
$blog_url = $blog_id ? get_permalink( $blog_id ) : home_url( '/' );
?>
<header class="phero center">
	<div class="phero-bg" aria-hidden="true"><span class="grid"></span><span class="g g1"></span><span class="g g2"></span></div>
	<div class="wrap">
		<span class="eyebrow"><span class="dot"></span>404</span>
		<h1 class="bl-h1">This page wandered off.</h1>
		<p class="bl-sub">The page you were after doesn&rsquo;t exist or moved. Search, or head back.</p>
		<form class="search center" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
			<input type="search" name="s" placeholder="Search articles&hellip;" aria-label="Search articles" />
		</form>
		<p style="margin-top:22px"><a class="btn btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back home</a> &nbsp; <a class="btn" href="<?php echo esc_url( $blog_url ); ?>">Visit the blog</a></p>
	</div>
</header>
<?php get_template_part( 'template-parts/site-foot' ); ?>
