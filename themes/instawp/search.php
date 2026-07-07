<?php
/**
 * Search results. Server-rendered through the home-build search design:
 * dark header + native WP search form + card grid (+ empty state).
 */
get_template_part( 'template-parts/site-head' );

global $wp_query;
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$q     = get_search_query();
$count = (int) $wp_query->found_posts;
?>

<!-- SEARCH HEADER -->
<header class="phero search-hero">
	<div class="phero-bg" aria-hidden="true"><span class="grid"></span><span class="g g1"></span><span class="g g2"></span></div>
	<div class="wrap">
		<nav class="crumb" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span class="sep">/</span>
			<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a><span class="sep">/</span>
			<span class="here">Search</span>
		</nav>
		<h1>Search the blog</h1>
		<form class="search<?php echo $q ? ' has' : ''; ?>" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
			<input type="search" name="s" placeholder="Search articles&hellip;" aria-label="Search articles" value="<?php echo esc_attr( $q ); ?>" autofocus />
		</form>
		<div class="sh-count">
			<?php if ( $q ) : ?>
				<b><?php echo (int) $count; ?></b> <?php echo 1 === $count ? 'result' : 'results'; ?> for &ldquo;<?php echo esc_html( $q ); ?>&rdquo;
			<?php endif; ?>
		</div>
	</div>
</header>

<!-- RESULTS -->
<main class="feed">
	<div class="wrap">
		<?php if ( have_posts() ) : ?>
			<div class="grid">
				<?php while ( have_posts() ) : the_post(); echo instawp_blog_card( get_post() ); endwhile; ?>
			</div>
			<?php if ( $wp_query->max_num_pages > 1 ) : ?>
				<div class="pager">
					<?php
					echo paginate_links( array(
						'total'     => $wp_query->max_num_pages,
						'current'   => $paged,
						'mid_size'  => 1,
						'prev_text' => '&larr; Prev',
						'next_text' => 'Next &rarr;',
					) );
					?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="empty">
				<?php if ( $q ) : ?>
					No articles match &ldquo;<?php echo esc_html( $q ); ?>&rdquo;. Try another term.
				<?php else : ?>
					Type a search term above to find articles.
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</main>

<?php get_template_part( 'template-parts/site-foot' ); ?>
