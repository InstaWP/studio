<?php
/**
 * Blog index (posts page). Dark hero + category filter + featured post +
 * card grid + sidebar rail. Card markup = single source assets/partials/card.html.
 */
defined( 'ABSPATH' ) || exit;
get_template_part( 'template-parts/site-head' );

global $wp_query;
$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$items    = $wp_query->posts;
$featured = ( 1 === $paged && ! empty( $items ) ) ? array_shift( $items ) : null;
$cats     = iwps_blog_cats();
?>

<header class="phero center">
	<div class="phero-bg" aria-hidden="true"><span class="grid"></span><span class="g g1"></span><span class="g g2"></span></div>
	<div class="wrap">
		<span class="eyebrow"><span class="dot"></span><?php echo esc_html( iwps_blog_title() ); ?></span>
		<h1 class="bl-h1"><?php echo esc_html( iwps_blog_tagline() ); ?></h1>
		<form class="search center" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
			<input type="search" name="s" placeholder="Search articles&hellip;" aria-label="Search articles" value="<?php echo esc_attr( get_search_query() ); ?>" />
		</form>
	</div>
</header>

<?php if ( $cats ) : ?>
<div class="filterbar">
	<div class="wrap">
		<div class="pills">
			<a class="pill on" href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/' ) ); ?>">All</a>
			<?php foreach ( $cats as $term ) : ?>
				<a class="pill" href="<?php echo esc_url( get_category_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<main class="feed">
	<div class="wrap">

		<?php if ( $featured ) :
			$fmeta = iwps_blog_cat_meta( $featured->ID );
			$ic    = iwps_blog_icons();
			$fauth = (int) $featured->post_author;
			$fname = get_the_author_meta( 'display_name', $fauth );
			$fimg  = iwps_blog_card_img( $featured );
			?>
			<a class="featured" href="<?php echo esc_url( get_permalink( $featured ) ); ?>">
				<div class="thumb <?php echo esc_attr( $fmeta['thumb'] ); ?>"><span class="mk"></span><span class="gl"></span><span class="ico"><?php echo isset( $ic[ $fmeta['icon'] ] ) ? $ic[ $fmeta['icon'] ] : ''; ?></span><?php if ( $fimg ) { echo '<img class="pc-thumb-img" src="' . esc_url( $fimg ) . '" alt="' . esc_attr( get_the_title( $featured ) ) . '" loading="lazy">'; } ?></div>
				<div class="ft-bd">
					<div class="ft-tags"><span class="ft-flag">&#9733; Featured</span><span class="cat-pill <?php echo esc_attr( $fmeta['pill'] ); ?>"><?php echo esc_html( $fmeta['label'] ); ?></span></div>
					<h2><?php echo esc_html( get_the_title( $featured ) ); ?></h2>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $featured ), 34 ) ); ?></p>
					<div class="ft-foot">
						<div class="pmeta"><span class="av <?php echo esc_attr( iwps_blog_av_class( $fauth ) ); ?>"><?php echo esc_html( iwps_blog_initials( $fname ) ); ?></span> <?php echo esc_html( $fname ); ?> <span class="sep">&middot;</span> <?php echo esc_html( get_the_date( 'j M Y', $featured ) ); ?> <span class="sep">&middot;</span> <?php echo esc_html( iwps_blog_read_time( $featured ) ); ?></div>
						<span class="ft-read">Read article <span class="arr">&rarr;</span></span>
					</div>
				</div>
			</a>
		<?php endif; ?>

		<div class="feed-cols">
			<div>
				<?php if ( $items ) : ?>
					<div class="grid">
						<?php foreach ( $items as $p ) { echo iwps_blog_card( $p ); } ?>
					</div>
				<?php elseif ( ! $featured ) : ?>
					<div class="empty">No articles published yet. Check back soon.</div>
				<?php endif; ?>

				<?php if ( $wp_query->max_num_pages > 1 ) : ?>
					<div class="pager">
						<?php echo paginate_links( array(
							'total'     => $wp_query->max_num_pages,
							'current'   => $paged,
							'mid_size'  => 1,
							'prev_text' => '&larr; Prev',
							'next_text' => 'Next &rarr;',
						) ); ?>
					</div>
				<?php endif; ?>
			</div>

			<aside class="rail">
				<?php
				$most = get_posts( array(
					'post_type'           => 'post',
					'posts_per_page'      => 3,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'post__not_in'        => $featured ? array( $featured->ID ) : array(),
				) );
				if ( $most ) : ?>
					<div class="rail-card">
						<h4>Latest posts</h4>
						<?php $n = 1; foreach ( $most as $mp ) :
							$mmeta = iwps_blog_cat_meta( $mp->ID ); ?>
							<div class="pop"><span class="n"><?php echo (int) $n; ?></span><div><a href="<?php echo esc_url( get_permalink( $mp ) ); ?>"><?php echo esc_html( get_the_title( $mp ) ); ?></a><div class="pp-meta"><?php echo esc_html( $mmeta['label'] ); ?> &middot; <?php echo esc_html( iwps_blog_read_time( $mp ) ); ?></div></div></div>
						<?php $n++; endforeach; ?>
					</div>
				<?php endif; ?>

				<?php
				$authors = get_users( array(
					'has_published_posts' => array( 'post' ),
					'number'              => 3,
					'orderby'             => 'post_count',
					'order'               => 'DESC',
				) );
				if ( $authors ) : ?>
					<div class="rail-card">
						<h4>Authors</h4>
						<?php foreach ( $authors as $au ) :
							$count = count_user_posts( $au->ID, 'post' ); ?>
							<a class="pa" href="<?php echo esc_url( get_author_posts_url( $au->ID ) ); ?>"><span class="av <?php echo esc_attr( iwps_blog_av_class( $au->ID ) ); ?>"><?php echo esc_html( iwps_blog_initials( $au->display_name ) ); ?></span><span class="pa-bd"><b><?php echo esc_html( $au->display_name ); ?></b><span><?php echo (int) $count; ?> posts</span></span></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php $cta = iwps_blog_rail_cta(); if ( is_array( $cta ) && ! empty( $cta['title'] ) ) : ?>
					<div class="rail-cta">
						<h4><?php echo esc_html( $cta['title'] ); ?></h4>
						<?php if ( ! empty( $cta['body'] ) ) : ?><p><?php echo esc_html( $cta['body'] ); ?></p><?php endif; ?>
						<?php if ( ! empty( $cta['cta_url'] ) ) : ?><a class="btn btn-primary" href="<?php echo esc_url( $cta['cta_url'] ); ?>"><?php echo esc_html( $cta['cta_label'] ?? 'Learn more' ); ?> <span class="arr">&rarr;</span></a><?php endif; ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
</main>

<?php get_template_part( 'template-parts/site-foot' ); ?>
