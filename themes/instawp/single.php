<?php
/**
 * Single post. Server-rendered through the home-build blog-detail design:
 * dark title block + prose + auto-built sticky TOC + author bio + related + CTA.
 * Detail styles live in assets/blog.css; behaviour in assets/post.js (both shared
 * with the static preview). The in-article components (.takeaways/.callout/.code/
 * .cmp/.faq) are authored in the post content itself and styled by blog.css.
 */
get_template_part( 'template-parts/site-head' );

while ( have_posts() ) :
	the_post();
	$post_id   = get_the_ID();
	$author_id = (int) get_post_field( 'post_author', $post_id );
	$author    = get_the_author_meta( 'display_name', $author_id );
	$role      = get_the_author_meta( 'iwp_role', $author_id );
	$cats      = get_the_category();
	$read      = instawp_blog_read_time( $post_id );

	// Inject ids + data-toc into <h2>s and collect the TOC.
	list( $content_html, $toc ) = instawp_blog_toc( apply_filters( 'the_content', get_the_content() ) );
	?>

<div class="progress" id="progress" aria-hidden="true"></div>

<article>
	<header class="post-hero">
		<div class="post-hero-bg" aria-hidden="true"><span class="grid"></span><span class="g g1"></span><span class="g g2"></span></div>
		<div class="wrap">
			<h1><?php the_title(); ?></h1>
			<?php if ( $cats ) : ?>
				<div class="cat-row">
					<?php foreach ( array_slice( $cats, 0, 2 ) as $c ) : ?>
						<a class="cat<?php echo ( 'ai' === $c->slug ) ? ' ai' : ''; ?>" href="<?php echo esc_url( get_category_link( $c ) ); ?>"><span><?php echo esc_html( $c->name ); ?></span></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<nav class="crumb" aria-label="Breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span class="sep">/</span>
				<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog</a><span class="sep">/</span>
				<?php if ( $cats ) : ?>
					<a href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a><span class="sep">/</span>
				<?php endif; ?>
				<span class="here"><?php echo esc_html( wp_trim_words( get_the_title(), 8, '&hellip;' ) ); ?></span>
			</nav>
			<?php $dek = get_the_excerpt(); if ( $dek ) : ?>
				<p class="post-dek"><?php echo esc_html( $dek ); ?></p>
			<?php endif; ?>
			<div class="meta">
				<div class="author">
					<span class="avatar"><?php echo esc_html( instawp_blog_initials( $author ) ); ?></span>
					<div>
						<div class="a-name"><?php echo esc_html( $author ); ?></div>
						<div class="a-role"><?php echo esc_html( $role ? $role : 'InstaWP' ); ?></div>
					</div>
				</div>
				<span class="meta-div" aria-hidden="true"></span>
				<div class="meta-dots">
					<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> Updated <b><?php echo esc_html( get_the_modified_date( 'M j, Y' ) ); ?></b></span>
					<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> <b><?php echo esc_html( $read ); ?> read</b></span>
				</div>
			</div>
		</div>
	</header>

	<!-- ===== BODY + STICKY TOC ===== -->
	<div class="wrap">
		<div class="post-body">

			<div class="prose">
				<?php echo $content_html; // processed post content (trusted) ?>
			</div>

			<?php if ( count( $toc ) > 1 ) : ?>
				<aside class="toc" id="toc">
					<button class="toc-toggle" type="button" aria-expanded="false">On this page <span class="tg-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 9l6 6 6-6"/></svg></span></button>
					<div class="toc-label">On this page</div>
					<nav>
						<ul>
							<?php foreach ( $toc as $t ) : ?>
								<li><a href="#<?php echo esc_attr( $t['id'] ); ?>"><?php echo esc_html( $t['text'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				</aside>
			<?php endif; ?>

		</div><!-- /post-body -->
	</div><!-- /wrap -->
</article>

<!-- ===== POST FOOTER (bio / share / related / newsletter) ===== -->
<div class="wrap">
	<div class="post-foot">

		<div class="bio">
			<span class="avatar"><?php echo esc_html( instawp_blog_initials( $author ) ); ?></span>
			<div class="bio-bd">
				<div class="bio-name"><?php echo esc_html( $author ); ?></div>
				<?php if ( $role ) : ?><div class="bio-role"><?php echo esc_html( $role ); ?></div><?php endif; ?>
				<?php $bio = get_the_author_meta( 'description', $author_id ); if ( $bio ) : ?>
					<p class="bio-tx"><?php echo esc_html( $bio ); ?></p>
				<?php endif; ?>
				<div class="bio-links">
					<a href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18c-4.5 1.5-4.5-2.5-6-3m12 6v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.3 4.3 0 0 0-.1-3.2s-1-.3-3.4 1.3a11.7 11.7 0 0 0-6 0C6.3 1.3 5.3 1.6 5.3 1.6a4.3 4.3 0 0 0-.1 3.2A4.6 4.6 0 0 0 3.9 8c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V20"/></svg> More posts</a>
				</div>
			</div>
		</div>

		<div class="divider"></div>

		<!-- share -->
		<div class="share">
			<span class="sh-l">Share this:</span>
			<a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( get_permalink() ); ?>&text=<?php echo rawurlencode( get_the_title() ); ?>" target="_blank" rel="noopener" aria-label="Share on X"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 1.2h3.6l-7.8 9 9.2 12.2h-7.2l-5.6-7.4-6.5 7.4H1l8.4-9.6L0 1.2h7.4l5.1 6.8zM17.6 19.5h2L6.5 3.2H4.3z"/></svg></a>
			<a href="https://news.ycombinator.com/submitlink?u=<?php echo rawurlencode( get_permalink() ); ?>&t=<?php echo rawurlencode( get_the_title() ); ?>" target="_blank" rel="noopener" aria-label="Share on Hacker News"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v18H3zm8.1 11.7v3.6h1.8v-3.6L16 8.7h-2l-2 4-2-4H8z"/></svg></a>
			<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( get_permalink() ); ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5A2.5 2.5 0 1 1 0 3.5a2.5 2.5 0 0 1 4.98 0zM.5 8h4V24h-4zM8 8h3.8v2.2h.05c.53-1 1.83-2.2 3.77-2.2 4 0 4.75 2.65 4.75 6.1V24h-4v-7c0-1.67-.03-3.8-2.32-3.8s-2.68 1.8-2.68 3.68V24H8z"/></svg></a>
		</div>

		<?php
		$related = instawp_blog_related( $post_id, 3 );
		if ( $related ) :
			$rth = array( 'th1', 'th2', 'th3' );
			?>
			<section class="related">
				<span class="eyebrow"><span class="dot"></span>Keep reading</span>
				<div class="rel-grid">
					<?php foreach ( $related as $i => $rp ) :
						$rmeta = instawp_blog_cat_meta( $rp->ID );
						?>
						<a class="rcard" href="<?php echo esc_url( get_permalink( $rp ) ); ?>">
							<div class="thumb <?php echo esc_attr( $rth[ $i % 3 ] ); ?>"><span class="mk"></span><span class="gl"></span><span class="tcat"><?php echo esc_html( $rmeta['label'] ); ?></span><?php $rimg = instawp_blog_card_img( $rp ); if ( $rimg ) { echo '<img class="pc-thumb-img" src="' . esc_url( $rimg ) . '" alt="' . esc_attr( get_the_title( $rp ) ) . '" loading="lazy">'; } ?></div>
							<div class="rc-bd">
								<h3><?php echo esc_html( get_the_title( $rp ) ); ?></h3>
								<div class="rc-meta"><span><?php echo esc_html( get_the_author_meta( 'display_name', $rp->post_author ) ); ?></span><span class="sep">&middot;</span><span><?php echo esc_html( instawp_blog_read_time( $rp ) ); ?></span></div>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

	</div>
</div>

<button class="totop" id="totop" type="button" aria-label="Back to top"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 19V5M5 12l7-7 7 7"/></svg></button>

<div id="site-cta"></div>

<?php endwhile; ?>

<?php get_template_part( 'template-parts/site-foot' ); ?>
