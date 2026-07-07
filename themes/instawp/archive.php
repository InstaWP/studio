<?php
/**
 * Archive (category / tag / author / date). Server-rendered through the
 * home-build archive design: dark header + category filter + card grid + pager.
 */
get_template_part( 'template-parts/site-head' );

global $wp_query;
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$cats  = instawp_blog_cats();

// Header copy by archive type.
$cur_cat = '';
if ( is_category() ) {
	$term      = get_queried_object();
	$cur_cat   = $term->slug;
	$arc_title = single_cat_title( '', false );
	$kicker    = 'Category';
	$arc_desc  = term_description() ? wp_strip_all_tags( term_description() ) : 'Articles in ' . $arc_title . '.';
} elseif ( is_tag() ) {
	$arc_title = single_tag_title( '', false );
	$kicker    = 'Tag';
	$arc_desc  = term_description() ? wp_strip_all_tags( term_description() ) : 'Articles tagged ' . $arc_title . '.';
} elseif ( is_author() ) {
	$arc_title = get_the_author_meta( 'display_name', get_queried_object_id() );
	$kicker    = 'Author';
	$bio       = get_the_author_meta( 'description', get_queried_object_id() );
	$arc_desc  = $bio ? $bio : 'Posts by ' . $arc_title . '.';
} else {
	$arc_title = wp_strip_all_tags( get_the_archive_title() );
	$kicker    = 'Archive';
	$arc_desc  = 'Articles from the InstaWP blog.';
}
$count = (int) $wp_query->found_posts;
?>

<!-- ARCHIVE HEADER -->
<header class="phero">
	<div class="phero-bg" aria-hidden="true"><span class="grid"></span><span class="g g1"></span><span class="g g2"></span></div>
	<div class="wrap">
		<nav class="crumb" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span class="sep">/</span>
			<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a><span class="sep">/</span>
			<span class="here"><?php echo esc_html( $arc_title ); ?></span>
		</nav>
		<span class="eyebrow"><span class="dot"></span><?php echo esc_html( $kicker ); ?></span>
		<h1 class="arc-h1"><?php echo esc_html( $arc_title ); ?></h1>
		<p class="arc-desc"><?php echo esc_html( $arc_desc ); ?></p>
		<div class="arc-count"><?php echo (int) $count . ' ' . ( 1 === $count ? 'article' : 'articles' ); ?></div>
	</div>
</header>

<!-- CATEGORY FILTER -->
<div class="filterbar">
	<div class="wrap">
		<div class="pills">
			<a class="pill" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">All</a>
			<?php
			foreach ( $cats as $slug => $meta ) :
				$term = get_category_by_slug( $slug );
				if ( ! $term ) {
					continue;
				}
				?>
				<a class="pill<?php echo ( $cur_cat === $slug ) ? ' on' : ''; ?>" href="<?php echo esc_url( get_category_link( $term ) ); ?>"><?php echo esc_html( $meta['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- FEED -->
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
			<div class="empty">No articles in this category yet.</div>
		<?php endif; ?>
	</div>
</main>

<?php get_template_part( 'template-parts/site-foot' ); ?>
