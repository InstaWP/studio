<?php
/**
 * InstaStudio companion theme — structured blog.
 *
 * Real WordPress posts (categories, authors, search) rendered through the
 * InstaStudio design so the blog matches the source-rendered pages. Agents
 * create posts (e.g. via InstaMCP `create_content`); humans manage them in
 * wp-admin. Pages stay HTML files (the iwp-studio plugin); the blog stays
 * structured content here.
 *
 * The post card is a SINGLE SOURCE: assets/partials/card.html.
 * Generic by design — category theming is derived from the site's own
 * categories (no hardcoded list), and all display copy is filterable.
 */
defined( 'ABSPATH' ) || exit;

/** Is the current request a blog view (index / post / archive / search / 404)? */
function iwps_is_blog_context() {
	return is_home() || is_singular( 'post' ) || is_category() || is_tag()
		|| is_author() || is_date() || is_search() || is_404();
}

/* --------------------------------------------------------------------------
   Assets — the blog reuses the InstaStudio nav/footer (chrome.js) + fonts so
   it looks like the rest of the site. chrome.js + link rewriting come from the
   iwp-studio plugin when active; the blog still renders without it.
   -------------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! iwps_is_blog_context() ) {
		return;
	}
	$ver = wp_get_theme()->get( 'Version' ) ?: '1.0.0';

	// Fonts (self-contained so the design holds on any host).
	wp_enqueue_style(
		'iwps-fonts',
		'https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap',
		array(),
		null
	);

	// The Studio site base tokens/skin, if the plugin is present (keeps the blog
	// in sync with the source-rendered pages). blog.css loads after and is
	// self-contained, so this is optional.
	if ( defined( 'INSTAWP_HB_URL' ) ) {
		$base = INSTAWP_HB_DIR . 'assets/style.css';
		if ( file_exists( $base ) ) {
			wp_enqueue_style( 'iwps-site', INSTAWP_HB_URL . 'assets/style.css', array( 'iwps-fonts' ), filemtime( $base ) );
		}
	}

	// Blog design + single-post behaviour (shipped with the theme).
	wp_enqueue_style( 'iwps-blog', get_theme_file_uri( 'assets/blog.css' ), array( 'iwps-fonts' ), $ver );
	if ( is_singular( 'post' ) ) {
		wp_enqueue_script( 'iwps-post', get_theme_file_uri( 'assets/post.js' ), array(), $ver, true );
	}

	// Shared nav/footer via the plugin's chrome.js (+ its .html→route rewriter).
	if ( defined( 'INSTAWP_HB_URL' ) && function_exists( 'instawp_hb_chrome_linkfix' ) && function_exists( 'instawp_hb_asset' ) ) {
		instawp_hb_chrome_linkfix( instawp_hb_asset( 'chrome.js' ) );
	}
}, 20 );

/* --------------------------------------------------------------------------
   Category theming — fully generic. Every category gets a stable gradient +
   icon derived from its slug, so ANY site's categories are themed automatically.
   -------------------------------------------------------------------------- */

/** Top categories (by post count) for the filter pills. */
function iwps_blog_cats( $limit = 8 ) {
	$terms = get_terms( array(
		'taxonomy'   => 'category',
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => $limit,
		'hide_empty' => true,
		'exclude'    => array( (int) get_option( 'default_category' ) ),
	) );
	return ( is_array( $terms ) && ! is_wp_error( $terms ) ) ? $terms : array();
}

/** Decorative card icons keyed by name. */
function iwps_blog_icons() {
	return array(
		'terminal' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M13 15h4"/></svg>',
		'chip'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 8V4M9 4h6M9 14h.01M15 14h.01"/></svg>',
		'building' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg>',
		'refresh'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v6l4 2M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v4h-4"/></svg>',
		'doc'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h6"/></svg>',
		'code'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 6-6 6 6 6M16 6l6 6-6 6"/></svg>',
		'server'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01M7 17h.01"/></svg>',
		'news'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="14" height="16" rx="1.5"/><path d="M17 8h3v10a2 2 0 0 1-2 2M7 8h6M7 12h6M7 16h4"/></svg>',
		'spark'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/></svg>',
		'tag'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>',
	);
}

/** Initials for an avatar chip, e.g. "Ada Lovelace" => "AL". */
function iwps_blog_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$a = isset( $parts[0][0] ) ? $parts[0][0] : '';
	$b = ( count( $parts ) > 1 && isset( $parts[ count( $parts ) - 1 ][0] ) ) ? $parts[ count( $parts ) - 1 ][0] : '';
	return strtoupper( $a . $b );
}

/** Stable avatar tint class ('', 'b', 'c') from an author id. */
function iwps_blog_av_class( $author_id ) {
	$opts = array( '', 'b', 'c' );
	return $opts[ (int) $author_id % 3 ];
}

/** Estimated reading time, e.g. "7 min" (~200 wpm). */
function iwps_blog_read_time( $post = null ) {
	$post  = get_post( $post );
	$words = str_word_count( wp_strip_all_tags( $post ? $post->post_content : '' ) );
	return max( 1, (int) round( $words / 200 ) ) . ' min';
}

/** Primary category term (first non-default, else first, else null). */
function iwps_blog_primary_cat( $post_id ) {
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	$default = (int) get_option( 'default_category' );
	foreach ( $terms as $t ) {
		if ( $t->term_id !== $default ) {
			return $t;
		}
	}
	return $terms[0];
}

/** Generic per-category theming derived from the slug (stable, automatic). */
function iwps_blog_term_style( $term ) {
	$icons  = array_keys( iwps_blog_icons() );
	$thumbs = array( 'th-a', 'th-b', 'th-c' );
	$slug   = $term ? $term->slug : 'article';
	$h      = crc32( $slug );
	// "ai"-ish categories get the accent skin + spark icon.
	$is_ai  = (bool) preg_match( '/\b(ai|ml|gpt|llm|agent)\b/i', $slug );
	return array(
		'thumb' => $is_ai ? 'th-ai' : $thumbs[ $h % count( $thumbs ) ],
		'icon'  => $is_ai ? 'spark' : $icons[ $h % count( $icons ) ],
		'pill'  => $is_ai ? 'ai' : '',
	);
}

/** Display meta for a post's primary category. */
function iwps_blog_cat_meta( $post_id ) {
	$term  = iwps_blog_primary_cat( $post_id );
	$style = iwps_blog_term_style( $term );
	return array_merge( $style, array(
		'label' => $term ? $term->name : 'Article',
		'term'  => $term,
	) );
}

/** The single-source card template (assets/partials/card.html), comment stripped, cached. */
function iwps_blog_card_template() {
	static $tpl = null;
	if ( null !== $tpl ) {
		return $tpl;
	}
	$f   = get_theme_file_path( 'assets/partials/card.html' );
	$tpl = file_exists( $f ) ? (string) file_get_contents( $f ) : '';
	$tpl = preg_replace( '/<!--.*?-->/s', '', $tpl, 1 );
	return $tpl = trim( $tpl );
}

/** Fill the shared card template with a {{token}} => value map. */
function iwps_blog_fill_card( $map ) {
	return preg_replace_callback(
		'/\{\{(\w+)\}\}/',
		function ( $m ) use ( $map ) {
			return isset( $map[ $m[1] ] ) ? $map[ $m[1] ] : '';
		},
		iwps_blog_card_template()
	);
}

/** Card thumbnail URL: WP featured image, else first in-content <img>, cached. */
function iwps_blog_card_img( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	if ( has_post_thumbnail( $post ) ) {
		$u = get_the_post_thumbnail_url( $post, 'medium_large' );
		if ( $u ) {
			return $u;
		}
	}
	$cached = get_post_meta( $post->ID, '_iwp_card_img', true );
	if ( '' !== $cached ) {
		return '0' === $cached ? '' : $cached;
	}
	$src = '';
	if ( preg_match( '#<img[^>]+src=["\']([^"\']+)["\']#i', (string) $post->post_content, $m ) ) {
		$src = $m[1];
	}
	update_post_meta( $post->ID, '_iwp_card_img', '' !== $src ? $src : '0' );
	return $src;
}

/** Render a WP post as a blog card (server-side, from the single-source template). */
function iwps_blog_card( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$ic   = iwps_blog_icons();
	$meta = iwps_blog_cat_meta( $post->ID );
	$auth = (int) $post->post_author;
	$name = get_the_author_meta( 'display_name', $auth );
	$img  = iwps_blog_card_img( $post );
	return iwps_blog_fill_card( array(
		'href'       => esc_url( get_permalink( $post ) ),
		'thumbClass' => esc_attr( $meta['thumb'] ),
		'icon'       => isset( $ic[ $meta['icon'] ] ) ? $ic[ $meta['icon'] ] : '',
		'thumbImg'   => $img ? '<img class="pc-thumb-img" src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $post ) ) . '" loading="lazy">' : '',
		'pillClass'  => 'cat-pill' . ( $meta['pill'] ? ' ' . esc_attr( $meta['pill'] ) : '' ),
		'catLabel'   => esc_html( $meta['label'] ),
		'title'      => esc_html( get_the_title( $post ) ),
		'excerpt'    => esc_html( wp_trim_words( get_the_excerpt( $post ), 26 ) ),
		'avClass'    => iwps_blog_av_class( $auth ),
		'avInitials' => esc_html( iwps_blog_initials( $name ) ),
		'author'     => esc_html( $name ),
		'date'       => esc_html( get_the_date( 'j M Y', $post ) ),
		'read'       => esc_html( iwps_blog_read_time( $post ) ),
	) );
}

/** Add id + data-toc to every <h2> and collect them for the sticky TOC. */
function iwps_blog_toc( $html ) {
	$items = array();
	$used  = array();
	$html  = preg_replace_callback(
		'/<h2\b([^>]*)>(.*?)<\/h2>/is',
		function ( $m ) use ( &$items, &$used ) {
			$attrs = $m[1];
			$text  = trim( wp_strip_all_tags( $m[2] ) );
			if ( preg_match( '/\bid=["\']([^"\']+)["\']/', $attrs, $idm ) ) {
				$id = $idm[1];
			} else {
				$id = sanitize_title( $text );
				if ( '' === $id ) {
					$id = 'section';
				}
				$base = $id; $n = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n; $n++;
				}
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ] = true;
			if ( strpos( $attrs, 'data-toc' ) === false ) {
				$attrs .= ' data-toc';
			}
			$items[] = array( 'id' => $id, 'text' => $text );
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$html
	);
	return array( $html, $items );
}

/** Posts related to a post (same primary category), topped up with recent. */
function iwps_blog_related( $post_id, $limit = 3 ) {
	$term = iwps_blog_primary_cat( $post_id );
	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => $limit,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( $term ) {
		$args['category__in'] = array( $term->term_id );
	}
	$q = get_posts( $args );
	if ( count( $q ) < $limit ) {
		$extra = get_posts( array(
			'post_type'           => 'post',
			'posts_per_page'      => $limit - count( $q ),
			'post__not_in'        => array_merge( array( $post_id ), wp_list_pluck( $q, 'ID' ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );
		$q = array_merge( $q, $extra );
	}
	return $q;
}

/* --------------------------------------------------------------------------
   Filterable display copy (override in a child theme / mu-plugin per site).
   -------------------------------------------------------------------------- */
function iwps_blog_title() {
	return apply_filters( 'iwps_blog_title', get_bloginfo( 'name' ) . ' Blog' );
}
function iwps_blog_tagline() {
	return apply_filters( 'iwps_blog_tagline', get_bloginfo( 'description' ) ?: 'Notes, guides, and updates.' );
}
/** Optional sidebar CTA: return array(title, body, cta_label, cta_url) or null to hide. */
function iwps_blog_rail_cta() {
	return apply_filters( 'iwps_blog_rail_cta', null );
}
