<?php
/**
 * InstaWP Theme — lightweight, no page builder or block editor.
 *
 * Marketing pages render from the home-build SOURCE OF TRUTH
 * (variations/home-build/) via a tiny file renderer + a standalone template.
 * No block editor, no Tailwind compiler, no DB-stored content. Edit the
 * home-build HTML/CSS and refresh — the WordPress page reflects it.
 * See APP-UI.md and memory wp-implementation-approach.md.
 */

// Source directory of your HTML pages (the source of truth). Override both in
// wp-config.php to point elsewhere. Default: <wp-root>/site/.
defined( 'INSTAWP_HB_DIR' ) || define( 'INSTAWP_HB_DIR', ABSPATH . 'site/' );        // filesystem
defined( 'INSTAWP_HB_URL' ) || define( 'INSTAWP_HB_URL', home_url( '/site/' ) );     // web

// In-place editor for the home-build source (LOCAL admins only; no-op on the
// mirror/prod). Writes edits straight back to the variations/home-build files.
require_once __DIR__ . '/inc/homebuild-editor.php';

/**
 * Published marketing pages: WP page slug => file under variations/home-build/.
 * Add a line here (and a WP page with that slug) to publish a page.
 */
function instawp_homebuild_pages() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$dir = rtrim( INSTAWP_HB_DIR, '/' );

	// 1) Explicit map via <source>/pages.json ( { "slug": "relative/file.html" } ) — full control.
	$json = $dir . '/pages.json';
	if ( is_readable( $json ) ) {
		$decoded = json_decode( (string) file_get_contents( $json ), true );
		if ( is_array( $decoded ) && $decoded ) {
			return $map = apply_filters( 'instawp_homebuild_pages', $decoded );
		}
	}

	// 2) Otherwise auto-scan: every .html under the source dir becomes a page. The slug is
	//    the path relative to the source dir minus ".html"; a root index.html maps to "home".
	$map = array();
	if ( is_dir( $dir ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( ! $file->isFile() || 0 !== strcasecmp( $file->getExtension(), 'html' ) ) {
				continue;
			}
			$rel = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) ) ), '/' );
			// Skip shared assets, partials, and dot-dirs (backups etc.).
			if ( 0 === strpos( $rel, 'assets/' ) || 0 === strpos( $rel, 'partials/' )
				|| false !== strpos( $rel, '/partials/' ) || false !== strpos( $rel, '/.' ) || '.' === $rel[0] ) {
				continue;
			}
			$slug = preg_replace( '/\.html$/i', '', $rel );
			$slug = ( 'index' === $slug ) ? 'home' : $slug;
			$map[ $slug ] = $rel;
		}
		ksort( $map );
	}
	return $map = apply_filters( 'instawp_homebuild_pages', $map );
}

/**
 * home-build file => WP route, for rewriting internal links to WP URLs.
 *
 * Keyed by BOTH the full relative path (authoritative, collision-free) and the
 * basename (fallback). Some basenames collide across folders, e.g. the agencies
 * use-case (use-cases/agencies.html -> /for-agencies/) vs the agency directory
 * (agency/agencies.html -> /agencies/); the full-path key disambiguates them, and
 * link resolution prefers the full path before falling back to the basename.
 */
function instawp_homebuild_linkmap() {
	// 1) Authoritative full-relative-path => route.
	$paths = array();
	foreach ( instawp_homebuild_pages() as $slug => $file ) {
		if ( 'index.html' === basename( $file ) ) {
			$route = home_url( '/' );
		} elseif ( strpos( $file, 'features/' ) === 0 ) {
			$route = home_url( '/features/' . $slug . '/' ); // feature pages are nested under /features/
		} else {
			$route = home_url( '/' . $slug . '/' );
		}
		$paths[ $file ] = $route;
	}
	// Plugin / dynamic routes whose source mock lives in a subfolder.
	$paths['blog-list.html']            = home_url( '/blog/' );           // dynamic WP blog
	$paths['directory/plugins.html']    = home_url( '/plugins/' );        // instawp-directory-wporg
	$paths['directory/themes.html']     = home_url( '/themes/' );
	$paths['templates/templates.html']  = home_url( '/templates/' );      // instawp-template-store
	$paths['agency/agencies.html']      = home_url( '/agencies/' );       // instawp-agency-directory
	$paths['tools/tools.html']          = home_url( '/tools/' );          // instawp-tools
	$paths['tools/plugin-detector.html'] = home_url( '/tools/plugin-detector/' );
	$paths['tools/plugin-analyzer.html'] = home_url( '/tools/plugin-analyzer/' );
	$paths['case-studies/case-studies.html'] = home_url( '/case-studies/' ); // instawp-case-studies
	$paths['webinars/webinars.html']         = home_url( '/webinars/' );     // instawp-webinars
	$paths['showcase/showcase.html']         = home_url( '/showcase/' );     // instawp-showcase

	// 2) Build map: full-path keys first, then basename fallbacks (first occurrence wins).
	$map = $paths;
	foreach ( $paths as $file => $route ) {
		$base = basename( $file );
		if ( ! isset( $map[ $base ] ) ) {
			$map[ $base ] = $route;
		}
	}
	return $map;
}

/** Current request's home-build slug, or '' if this isn't a mapped page. */
function instawp_homebuild_slug() {
	if ( ! is_page() && ! is_front_page() ) {
		return '';
	}
	$obj  = get_queried_object();
	$slug = ( $obj && isset( $obj->post_name ) ) ? $obj->post_name : '';
	$map  = instawp_homebuild_pages();
	return isset( $map[ $slug ] ) ? $slug : '';
}
function instawp_is_homebuild_page() {
	return instawp_homebuild_slug() !== '';
}

/**
 * The /features/ parent is only a URL container for the nested feature pages
 * (/features/hosting/ etc.); it has no page of its own, so send it to the
 * platform overview. Runs after the redirects plugin (priority 0).
 */
add_action( 'template_redirect', function () {
	if ( is_page() ) {
		$obj = get_queried_object();
		if ( $obj && isset( $obj->post_name, $obj->post_parent ) && 'features' === $obj->post_name && 0 === (int) $obj->post_parent ) {
			wp_safe_redirect( home_url( '/platform/' ), 301 );
			exit;
		}
	}
}, 1 );

/**
 * Feed the marketing pages' hand-crafted <title> + <meta name="description"> (from the
 * home-build source files) into Rank Math, so it outputs them (incl. og/twitter, which
 * Rank Math derives from these) instead of the generic "%title% %sep% %sitename%" template.
 * Home-build stays the single source of truth for marketing-page SEO copy.
 */
/**
 * Parse a home-build source file's SEO copy (<title> + meta description) once per
 * request, tolerant of attribute order / quoting / line breaks (DOMDocument, not a
 * rigid single-line regex). Cached per slug. Returns array( 'title', 'description' ).
 */
function instawp_homebuild_seo( $slug ) {
	static $cache = array();
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}
	$out  = array( 'title' => '', 'description' => '' );
	$html = instawp_homebuild_html( $slug );
	if ( $html === '' ) {
		return $cache[ $slug ] = $out;
	}

	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	// Force UTF-8 so multibyte copy (·, curly quotes) is read and measured correctly.
	$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	$titles = $doc->getElementsByTagName( 'title' );
	if ( $titles->length > 0 ) {
		$out['title'] = trim( html_entity_decode( $titles->item( 0 )->textContent, ENT_QUOTES, 'UTF-8' ) );
	}
	foreach ( $doc->getElementsByTagName( 'meta' ) as $meta ) {
		if ( strtolower( $meta->getAttribute( 'name' ) ) === 'description' ) {
			$out['description'] = trim( html_entity_decode( $meta->getAttribute( 'content' ), ENT_QUOTES, 'UTF-8' ) );
			break;
		}
	}

	// Dev-only nudge: a mapped marketing page should always carry both.
	if ( ( $out['title'] === '' || $out['description'] === '' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$missing = array();
		if ( $out['title'] === '' )      { $missing[] = '<title>'; }
		if ( $out['description'] === '' ) { $missing[] = 'meta description'; }
		error_log( sprintf( '[instawp] home-build page "%s" is missing %s', $slug, implode( ' + ', $missing ) ) );
	}

	return $cache[ $slug ] = $out;
}

function instawp_homebuild_meta_tag( $tag ) {
	$slug = instawp_homebuild_slug();
	if ( ! $slug ) {
		return '';
	}
	$seo = instawp_homebuild_seo( $slug );
	if ( 'title' === $tag ) {
		return $seo['title'];
	}
	if ( 'description' === $tag ) {
		return $seo['description'];
	}
	return '';
}
add_filter( 'rank_math/frontend/title', function ( $title ) {
	$t = instawp_homebuild_meta_tag( 'title' );
	return $t ? $t : $title;
}, 20 );
add_filter( 'rank_math/frontend/description', function ( $desc ) {
	$d = instawp_homebuild_meta_tag( 'description' );
	return $d ? $d : $desc;
}, 20 );

/** Read the home-build HTML file for a slug; '' if unknown/missing. */
function instawp_homebuild_html( $slug ) {
	$map = instawp_homebuild_pages();
	if ( ! isset( $map[ $slug ] ) ) {
		return '';
	}
	$file = INSTAWP_HB_DIR . $map[ $slug ];
	return file_exists( $file ) ? file_get_contents( $file ) : '';
}

/** The <head> portion of a home-build doc (for asset discovery). */
function instawp_homebuild_head( $html ) {
	$end = strpos( $html, '</head>' );
	return $end !== false ? substr( $html, 0, $end ) : $html;
}

/**
 * Render a home-build page BODY: any <head> <style> blocks + the markup between
 * #site-nav and #site-footer, with ../assets/ rewritten to the source URL.
 */
function instawp_render_homebuild( $slug ) {
	$html = instawp_homebuild_html( $slug );
	if ( $html === '' ) {
		return '<!-- home-build source missing for "' . esc_html( $slug ) . '" -->';
	}
	$head   = instawp_homebuild_head( $html );
	$styles = preg_match_all( '/<style[^>]*>.*?<\/style>/s', $head, $m ) ? implode( "\n", $m[0] ) : '';

	$nav  = '<div id="site-nav"></div>';
	$foot = '<div id="site-footer"></div>';
	$i    = strpos( $html, $nav );
	$j    = strpos( $html, $foot );
	$body = ( $i !== false && $j !== false ) ? substr( $html, $i + strlen( $nav ), $j - $i - strlen( $nav ) ) : '';

	// Carry the page's OWN inline scripts so it behaves exactly as authored: the
	// `js` flag in <head> (gates reveal CSS) and the reveal/counter scripts after
	// #site-footer. External <script src> (chrome.js, home.js…) are enqueued
	// separately, so skip anything with a src attribute.
	$no_src       = '/<script\b(?![^>]*\ssrc=)[^>]*>.*?<\/script>/is';
	$head_scripts = preg_match_all( $no_src, $head, $hs ) ? implode( "\n", $hs[0] ) : '';
	$tail         = ( $j !== false ) ? substr( $html, $j + strlen( $foot ) ) : '';
	$tail_scripts = preg_match_all( $no_src, $tail, $ts ) ? implode( "\n", $ts[0] ) : '';

	// Rewrite relative asset refs ("assets/", "../assets/", …) to the source URL,
	// anchored to a quote/paren so external URLs are untouched. Applied everywhere.
	$assets_re    = '#([("\'])(?:\.\./)*assets/#';
	$repl         = '$1' . INSTAWP_HB_URL . 'assets/';
	$styles       = preg_replace( $assets_re, $repl, $styles );
	$body         = preg_replace( $assets_re, $repl, $body );
	$head_scripts = preg_replace( $assets_re, $repl, $head_scripts );
	$tail_scripts = preg_replace( $assets_re, $repl, $tail_scripts );

	// Rewrite internal page links (any relative *.html) to their WP routes — body only.
	$lm   = instawp_homebuild_linkmap();
	$body = preg_replace_callback(
		'#href=(["\'])([^"\']+?\.html)((?:\#[^"\']*)?)\1#i',
		function ( $m ) use ( $lm ) {
			// Prefer the full relative path (collision-free), fall back to basename.
			$path  = preg_replace( '#^(?:\./|\.\./)+#', '', $m[2] ); // strip leading ./ and ../
			$route = isset( $lm[ $path ] ) ? $lm[ $path ] : ( isset( $lm[ basename( $m[2] ) ] ) ? $lm[ basename( $m[2] ) ] : '' );
			return $route ? 'href=' . $m[1] . $route . $m[3] . $m[1] : $m[0];
		},
		$body
	);

	// Filter point: the in-place editor (inc/homebuild-editor.php) annotates the
	// body with data-iwp-id here when an admin is editing locally. No-op otherwise.
	$body = apply_filters( 'instawp_render_body', $body, $slug );

	// js-flag first (sets html.js before body paints), then styles, body, reveal scripts.
	return $head_scripts . "\n" . $styles . "\n" . $body . "\n" . $tail_scripts;
}

/** Carry the source file's <body class="…"> onto the WP body (e.g. .ap-page sets the page bg). */
add_filter( 'body_class', function ( $classes ) {
	$slug = instawp_homebuild_slug();
	if ( ! $slug ) {
		return $classes;
	}
	$html = instawp_homebuild_html( $slug );
	if ( $html && preg_match( '/<body\b[^>]*\bclass=["\']([^"\']+)["\']/i', $html, $m ) ) {
		$classes = array_merge( $classes, preg_split( '/\s+/', trim( $m[1] ) ) );
	}
	return $classes;
} );

/** Use the source file's crafted <title> as the document title on marketing pages. */
add_filter( 'pre_get_document_title', function ( $title ) {
	$slug = instawp_homebuild_slug();
	if ( ! $slug ) {
		return $title;
	}
	$seo = instawp_homebuild_seo( $slug );
	return $seo['title'] !== '' ? $seo['title'] : $title;
} );

/**
 * Resilience net for SEO <head> output. Rank Math normally emits the meta
 * description + canonical (from the same source copy, via the filters above). If
 * Rank Math is ever inactive, the renderer's rebuilt <head> would otherwise ship
 * neither on marketing pages, so emit them directly. Dormant whenever Rank Math
 * is present, to avoid duplicate tags.
 */
add_action( 'wp_head', function () {
	if ( defined( 'RANK_MATH_VERSION' ) || ! instawp_is_homebuild_page() ) {
		return;
	}
	$slug = instawp_homebuild_slug();
	if ( ! $slug ) {
		return;
	}
	$seo = instawp_homebuild_seo( $slug );
	if ( $seo['description'] !== '' ) {
		echo '<meta name="description" content="' . esc_attr( $seo['description'] ) . '">' . "\n";
	}
	echo '<link rel="canonical" href="' . esc_url( get_permalink() ) . '">' . "\n";
}, 1 );

/** URL of a page's og card if it exists ('' otherwise). */
function instawp_homebuild_og_image_url( $slug ) {
	$rel = 'assets/og/' . $slug . '.png';
	return file_exists( INSTAWP_HB_DIR . $rel ) ? INSTAWP_HB_URL . $rel : '';
}

/**
 * og:image for the whole front end. Rank Math emits og:image ONLY when it has a
 * featured/configured image (it does not output a fallback on marketing pages,
 * the blog index, or archives), so the theme owns the image:
 *   - marketing pages  -> their generated per-page card (assets/og/<slug>.png)
 *   - everything else   -> the brand default card (assets/og/_default.png)
 * We skip singular posts that have a featured image, since Rank Math already
 * emits that as the og:image (avoids a duplicate). 1200x630, twitter:card is
 * already summary_large_image.
 */
add_action( 'wp_head', function () {
	$img = '';
	$alt = '';
	if ( instawp_is_homebuild_page() ) {
		$slug = instawp_homebuild_slug();
		$img  = $slug ? instawp_homebuild_og_image_url( $slug ) : '';
		if ( $slug ) {
			$seo = instawp_homebuild_seo( $slug );
			$alt = $seo['title'] !== '' ? $seo['title'] : get_the_title();
		}
	} else {
		if ( is_singular() && has_post_thumbnail() ) {
			return; // Rank Math emits the featured image as og:image
		}
		$def = 'assets/og/_default.png';
		if ( file_exists( INSTAWP_HB_DIR . $def ) ) {
			$img = INSTAWP_HB_URL . $def;
			$alt = get_bloginfo( 'name' );
		}
	}
	if ( $img === '' ) {
		return;
	}
	echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	echo '<meta property="og:image:secure_url" content="' . esc_url( $img ) . '">' . "\n";
	echo '<meta property="og:image:width" content="1200">' . "\n";
	echo '<meta property="og:image:height" content="630">' . "\n";
	echo '<meta property="og:image:alt" content="' . esc_attr( $alt ) . '">' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
}, 11 );

/** Route mapped pages to the standalone shell (bypasses header.php/footer.php). */
add_filter( 'template_include', function ( $template ) {
	if ( instawp_is_homebuild_page() ) {
		$tpl = get_template_directory() . '/template-homebuild.php';
		if ( file_exists( $tpl ) ) {
			return $tpl;
		}
	}
	return $template;
} );

/** Enqueue a single home-build asset (css/js) straight from source; return its handle. */
function instawp_hb_asset( $rel, $deps = array(), $in_footer = true ) {
	$f      = INSTAWP_HB_DIR . 'assets/' . $rel;
	$handle = 'instawp-hb-' . sanitize_key( $rel );
	$ver    = file_exists( $f ) ? filemtime( $f ) : '1';
	if ( preg_match( '/\.css$/i', $rel ) ) {
		wp_enqueue_style( $handle, INSTAWP_HB_URL . 'assets/' . $rel, $deps, $ver );
	} else {
		wp_enqueue_script( $handle, INSTAWP_HB_URL . 'assets/' . $rel, $deps, $ver, $in_footer );
	}
	return $handle;
}

/** Google fonts used across the home-build skin. */
function instawp_hb_fonts() {
	wp_enqueue_style(
		'instawp-hb-fonts',
		'https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap',
		array(), null
	);
}

/**
 * Load the Google Fonts stylesheet without blocking first paint: swap it in via
 * media="print" + onload, with a <noscript> fallback for JS-off clients. Fonts
 * still swap in fast (they carry &display=swap), but the request no longer sits
 * on the critical render path (~450ms off FCP per Lighthouse).
 */
add_filter( 'style_loader_tag', function ( $tag, $handle ) {
	if ( 'instawp-hb-fonts' !== $handle ) {
		return $tag;
	}
	$onload = " media='print' onload=\"this.media='all';this.onload=null;\"";
	$async  = str_replace( " media='all'", $onload, $tag );
	if ( $async === $tag ) { // no media attr in the tag -> inject the print swap directly
		$async = preg_replace( '/\s*\/?>\s*$/', $onload . ' />', $tag, 1 );
	}
	return $async . '<noscript>' . $tag . '</noscript>' . "\n";
}, 10, 2 );

/** Preconnect to the Google Fonts hosts so the async font swap resolves fast. */
add_filter( 'wp_resource_hints', function ( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = 'https://fonts.googleapis.com';
		$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $hints;
}, 10, 2 );

/**
 * Offset our fixed nav (and progress bar / mobile drawer) below the WordPress
 * admin bar when it's showing. The bar is 32px wide-screen, 46px <=782px, and
 * position:fixed elements ignore the html margin WP adds, so they need this.
 * WP-only chrome — kept out of the shared home-build CSS (no admin bar there).
 */
function instawp_hb_admin_bar_css() {
	return 'body.admin-bar .nav-header,body.admin-bar .progress,body.admin-bar .drawer{top:32px}'
		. '@media screen and (max-width:782px){body.admin-bar .nav-header,body.admin-bar .progress,body.admin-bar .drawer{top:46px}}';
}

/**
 * chrome.js builds nav/footer with links to home-build .html files; rewrite those
 * (under /variations/home-build/) to their WP routes by basename, after injection.
 * NOTE: this client-side rewrite is the known interim "tape" — the clean fix is to
 * render nav/footer server-side from nav.html/footer.html. Shared by all pages.
 */
function instawp_hb_chrome_linkfix( $chrome_handle ) {
	if ( ! wp_script_is( $chrome_handle, 'enqueued' ) ) {
		return;
	}
	$js = 'var IWP_LM=' . wp_json_encode( instawp_homebuild_linkmap() ) . ';'
		. '(function(){function f(){var B="/variations/home-build/";'
		. 'document.querySelectorAll(\'a[href*="\'+B+\'"]\').forEach(function(a){'
		. 'var h=a.getAttribute("href"),c=h.split(/[?#]/)[0],p=c.substring(c.indexOf(B)+B.length),b=c.substring(c.lastIndexOf("/")+1),'
		. 'g=h.indexOf("#")>=0?h.slice(h.indexOf("#")):"",r=IWP_LM[p]||IWP_LM[b];if(r)a.setAttribute("href",r+g);});}'
		. 'f();document.addEventListener("DOMContentLoaded",f);window.addEventListener("load",f);'
		. 'if(window.MutationObserver){var o=new MutationObserver(f);o.observe(document.documentElement,{childList:true,subtree:true});setTimeout(function(){o.disconnect();},5000);}})();';
	wp_add_inline_script( $chrome_handle, $js, 'after' );
}

/** Enqueue assets for marketing pages (per-page scan) and blog pages (skin + detail). */
/**
 * UTM carry-forward (replaces the prod url-appender plugin). Loaded site-wide on the
 * front end — marketing, blog, AND the directory/template/agency plugin pages — so
 * campaign params from the landing URL ride along on outgoing *.instawp.io links.
 */
add_action( 'wp_enqueue_scripts', function () {
	$path = get_theme_file_path( 'js/utm-forward.js' );
	$ver  = file_exists( $path ) ? filemtime( $path ) : '1.0.0';
	wp_enqueue_script( 'instawp-utm-forward', get_theme_file_uri( 'js/utm-forward.js' ), array(), $ver, true );
} );

add_action( 'wp_enqueue_scripts', function () {
	$slug    = instawp_homebuild_slug();
	$is_blog = instawp_is_blog_context();
	if ( ! $slug && ! $is_blog ) {
		return;
	}
	instawp_hb_fonts();
	wp_add_inline_style( 'instawp-hb-fonts', instawp_hb_admin_bar_css() );

	if ( $slug ) {
		// Marketing page: enqueue exactly the CSS + JS the source file references.
		$content = instawp_homebuild_html( $slug );
		if ( preg_match_all( '#(?:href|src)=["\'](?:\.\./)*assets/([a-z0-9_/-]+\.(?:css|js))["\']#i', $content, $mm ) ) {
			foreach ( array_unique( $mm[1] ) as $asset ) {
				instawp_hb_asset( $asset );
			}
		}
	} else {
		// Blog: shared green skin everywhere; single-post design only on posts.
		instawp_hb_asset( 'style.css' );
		instawp_hb_asset( 'kit.css' );
		if ( is_singular( 'post' ) ) {
			instawp_hb_asset( 'blog.css' );
			instawp_hb_asset( 'post.js' );
		}
	}

	// Nav/footer/CTA come from chrome.js on every home-build page (marketing + blog).
	instawp_hb_chrome_linkfix( instawp_hb_asset( 'chrome.js' ) );
} );

/** Minimal theme bootstrap. */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'caption', 'style', 'script' ) );
} );

/* ===========================================================================
   Dynamic blog — WordPress posts rendered through the home-build blog design,
   100% server-side. The post-card markup is the SINGLE SOURCE
   assets/partials/card.html (shared with feed.js); only the data differs here.
   Templates: home.php (index) · single.php · archive.php · search.php · 404.php.
   =========================================================================== */

/** Is the current request a blog view (index / post / archive / search / 404)? */
function instawp_is_blog_context() {
	return is_home() || is_singular( 'post' ) || is_category() || is_tag()
		|| is_author() || is_date() || is_search() || is_404();
}

/** Category slug => display meta mirroring feed.js (label, pill class, thumb gradient, icon key). */
function instawp_blog_cats() {
	// Curated pill row + per-category card/archive styling. Chosen from the real
	// imported categories by volume + reader intent (+ AI for brand). Categories not
	// listed here fall back to a neutral style (see instawp_blog_cat_meta).
	return array(
		'wordpress'           => array( 'label' => 'WordPress', 'pill' => '',   'thumb' => 'th-a',  'icon' => 'wp' ),
		'wordpress-plugins'   => array( 'label' => 'Plugins',   'pill' => '',   'thumb' => 'th-c',  'icon' => 'plugin' ),
		'wordpress-themes'    => array( 'label' => 'Themes',    'pill' => '',   'thumb' => 'th-c',  'icon' => 'theme' ),
		'develop'             => array( 'label' => 'Develop',   'pill' => '',   'thumb' => 'th-b',  'icon' => 'code' ),
		'hosting'             => array( 'label' => 'Hosting',   'pill' => '',   'thumb' => 'th-a',  'icon' => 'server' ),
		'wordpress-tutorials' => array( 'label' => 'Tutorials', 'pill' => '',   'thumb' => 'th-b',  'icon' => 'terminal' ),
		'news'                => array( 'label' => 'News',      'pill' => '',   'thumb' => 'th-c',  'icon' => 'news' ),
		'business'            => array( 'label' => 'Business',  'pill' => '',   'thumb' => 'th-c',  'icon' => 'building' ),
		'agencies'            => array( 'label' => 'Agencies',  'pill' => '',   'thumb' => 'th-c',  'icon' => 'building' ),
		'ai'                  => array( 'label' => 'AI',        'pill' => 'ai', 'thumb' => 'th-ai', 'icon' => 'chip' ),
	);
}

/** Decorative card icons (mirror feed.js ICONS). */
function instawp_blog_icons() {
	return array(
		'terminal' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M13 15h4"/></svg>',
		'chip'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 8V4M9 4h6M9 14h.01M15 14h.01"/></svg>',
		'building' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg>',
		'refresh'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v6l4 2M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v4h-4"/></svg>',
		'wp'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M4 12h16M12 4c2.5 2.5 2.5 13 0 16M12 4c-2.5 2.5-2.5 13 0 16"/></svg>',
		'plugin'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
		'theme'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/></svg>',
		'code'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 6-6 6 6 6M16 6l6 6-6 6"/></svg>',
		'server'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01M7 17h.01"/></svg>',
		'news'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="14" height="16" rx="1.5"/><path d="M17 8h3v10a2 2 0 0 1-2 2M7 8h6M7 12h6M7 16h4"/></svg>',
	);
}

/** Initials for an avatar chip, e.g. "Vikas Singhal" => "VS". */
function instawp_blog_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$a = isset( $parts[0][0] ) ? $parts[0][0] : '';
	$b = ( count( $parts ) > 1 && isset( $parts[ count( $parts ) - 1 ][0] ) ) ? $parts[ count( $parts ) - 1 ][0] : '';
	return strtoupper( $a . $b );
}

/** Stable avatar tint class ('', 'b', 'c') from an author id (matches feed.js avc values). */
function instawp_blog_av_class( $author_id ) {
	$opts = array( '', 'b', 'c' );
	return $opts[ (int) $author_id % 3 ];
}

/** Estimated reading time for a post, e.g. "7 min" (~200 wpm). */
function instawp_blog_read_time( $post = null ) {
	$post  = get_post( $post );
	$words = str_word_count( wp_strip_all_tags( $post ? $post->post_content : '' ) );
	return max( 1, (int) round( $words / 200 ) ) . ' min';
}

/** Primary category term for a post (first non-Uncategorized, else first, else null). */
function instawp_blog_primary_cat( $post_id ) {
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	foreach ( $terms as $t ) {
		if ( 'uncategorized' !== $t->slug ) {
			return $t;
		}
	}
	return $terms[0];
}

/** Display meta for a post's primary category, falling back gracefully. */
function instawp_blog_cat_meta( $post_id ) {
	$cats = instawp_blog_cats();
	$term = instawp_blog_primary_cat( $post_id );
	$slug = $term ? $term->slug : '';
	if ( isset( $cats[ $slug ] ) ) {
		return array_merge( $cats[ $slug ], array( 'term' => $term ) );
	}
	return array(
		'label' => $term ? $term->name : 'Article',
		'pill'  => '', 'thumb' => 'th-a', 'icon' => 'wp', 'term' => $term,
	);
}

/** The single-source card template (assets/partials/card.html), comment stripped, cached. */
function instawp_blog_card_template() {
	static $tpl = null;
	if ( null !== $tpl ) {
		return $tpl;
	}
	$f   = INSTAWP_HB_DIR . 'assets/partials/card.html';
	$tpl = file_exists( $f ) ? (string) file_get_contents( $f ) : '';
	$tpl = preg_replace( '/<!--.*?-->/s', '', $tpl, 1 );
	$tpl = trim( $tpl );
	return $tpl;
}

/** Fill the shared card template with a {{token}} => value map. */
function instawp_blog_fill_card( $map ) {
	return preg_replace_callback(
		'/\{\{(\w+)\}\}/',
		function ( $m ) use ( $map ) {
			return isset( $map[ $m[1] ] ) ? $map[ $m[1] ] : '';
		},
		instawp_blog_card_template()
	);
}

/**
 * The post's card thumbnail URL: the WP featured image if set, else the first
 * in-content <img> (cached in _iwp_card_img so we only scan the content once;
 * '0' records "no image" so posts without one keep falling back to the icon block).
 */
function instawp_blog_card_img( $post ) {
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
function instawp_blog_card( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$ic   = instawp_blog_icons();
	$meta = instawp_blog_cat_meta( $post->ID );
	$auth = (int) $post->post_author;
	$name = get_the_author_meta( 'display_name', $auth );
	$img  = instawp_blog_card_img( $post );
	return instawp_blog_fill_card( array(
		'href'       => esc_url( get_permalink( $post ) ),
		'thumbClass' => esc_attr( $meta['thumb'] ),
		'icon'       => isset( $ic[ $meta['icon'] ] ) ? $ic[ $meta['icon'] ] : '',
		'thumbImg'   => $img ? '<img class="pc-thumb-img" src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $post ) ) . '" loading="lazy">' : '',
		'pillClass'  => 'cat-pill' . ( $meta['pill'] ? ' ' . esc_attr( $meta['pill'] ) : '' ),
		'catLabel'   => esc_html( $meta['label'] ),
		'title'      => esc_html( get_the_title( $post ) ),
		'excerpt'    => esc_html( wp_trim_words( get_the_excerpt( $post ), 26 ) ),
		'avClass'    => instawp_blog_av_class( $auth ),
		'avInitials' => esc_html( instawp_blog_initials( $name ) ),
		'author'     => esc_html( $name ),
		'date'       => esc_html( get_the_date( 'j M Y', $post ) ),
		'read'       => esc_html( instawp_blog_read_time( $post ) ),
	) );
}

/**
 * Add id + data-toc to every <h2> in post content and collect them, so single.php
 * can build the sticky table of contents and post.js scroll-spy can track it.
 * Returns array( html, items[] ) where each item is array( id, text ).
 */
function instawp_blog_toc( $html ) {
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

/** Posts related to a post (same primary category), excluding it. */
function instawp_blog_related( $post_id, $limit = 3 ) {
	$term = instawp_blog_primary_cat( $post_id );
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
		// top up with recent posts if the category is thin
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

/**
 * Posts are authored as hand-crafted HTML (code blocks, callouts, tables), so
 * skip wpautop on single posts — it would mangle the markup. Content is written
 * with explicit <p>/<h2> tags. (texturize still runs and skips <pre>/<code>.)
 */
add_action( 'wp', function () {
	if ( is_singular( 'post' ) ) {
		remove_filter( 'the_content', 'wpautop' );
	}
} );

/**
 * Dynamic [year] token. Many imported blog posts (production parity) use a
 * [year] shortcode that resolves to the current year, in both titles and body
 * copy (e.g. "10 Best WordPress SSL Plugins in [year]"). Our lightweight theme
 * never registered it, so it was rendering literally.
 *
 * Registering the shortcode handles post CONTENT (single.php runs the
 * `the_content` filter, which includes do_shortcode). WordPress does NOT run
 * shortcodes in titles or excerpts, so we also swap the token there via a
 * cheap strpos-guarded str_replace. The RankMath hooks (harmless no-ops if the
 * plugin is inactive) keep the token out of the SEO <title>/description.
 */
function instawp_current_year() {
	return function_exists( 'wp_date' ) ? wp_date( 'Y' ) : date_i18n( 'Y' );
}

add_shortcode( 'year', 'instawp_current_year' );

function instawp_replace_year_token( $text ) {
	if ( is_string( $text ) && false !== strpos( $text, '[year]' ) ) {
		$text = str_replace( '[year]', instawp_current_year(), $text );
	}
	return $text;
}
add_filter( 'the_title', 'instawp_replace_year_token' );
add_filter( 'single_post_title', 'instawp_replace_year_token' );
add_filter( 'get_the_excerpt', 'instawp_replace_year_token' );
add_filter( 'rank_math/frontend/title', 'instawp_replace_year_token' );
add_filter( 'rank_math/frontend/description', 'instawp_replace_year_token' );
