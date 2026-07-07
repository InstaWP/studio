<?php
/**
 * 404. Server-rendered through the home-build skin: dark header + search +
 * a few recent posts to recover the visit.
 */
get_template_part( 'template-parts/site-head' );
?>

<header class="phero center search-hero">
	<div class="phero-bg" aria-hidden="true"><span class="grid"></span><span class="g g1"></span><span class="g g2"></span></div>
	<div class="wrap">
		<span class="eyebrow"><span class="dot"></span>404</span>
		<h1>We couldn&rsquo;t find that page</h1>
		<p class="bl-sub">The link may be broken or the page may have moved. Try a search, or head back to the blog.</p>
		<form class="search center" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
			<input type="search" name="s" placeholder="Search articles&hellip;" aria-label="Search articles" />
		</form>
		<div style="margin-top:22px"><a class="btn btn-primary" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Go to the blog <span class="arr">&rarr;</span></a></div>
	</div>
</header>

<?php
$recent = get_posts( array(
	'post_type'           => 'post',
	'posts_per_page'      => 3,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
) );
if ( $recent ) :
	?>
	<main class="feed">
		<div class="wrap">
			<div class="grid">
				<?php foreach ( $recent as $rp ) { echo instawp_blog_card( $rp ); } ?>
			</div>
		</div>
	</main>
<?php endif; ?>

<?php get_template_part( 'template-parts/site-foot' ); ?>
