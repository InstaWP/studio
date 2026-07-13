<?php
/**
 * The source-rendered engine. Maps a WordPress page slug to an .html file under
 * INSTAWP_HB_DIR and renders that file's body as the page, live from disk.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Page map: WP slug => file relative to INSTAWP_HB_DIR.
 * 1) an explicit <source>/pages.json wins; 2) else auto-scan every .html
 * (slug = path minus ".html"; root index.html => "home"). Filterable.
 */
function instawp_homebuild_pages() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$dir  = rtrim( INSTAWP_HB_DIR, '/' );
	$json = $dir . '/pages.json';
	if ( is_readable( $json ) ) {
		$decoded = json_decode( (string) file_get_contents( $json ), true );
		if ( is_array( $decoded ) && $decoded ) {
			return $map = apply_filters( 'instawp_homebuild_pages', $decoded );
		}
	}
	$map = array();
	if ( is_dir( $dir ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( ! $file->isFile() || 0 !== strcasecmp( $file->getExtension(), 'html' ) ) {
				continue;
			}
			$rel = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) ) ), '/' );
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

/** file (relative) => WP route, for rewriting internal .html links to real URLs. */
function instawp_homebuild_linkmap() {
	$paths = array();
	foreach ( instawp_homebuild_pages() as $slug => $file ) {
		$route = ( 'home' === $slug || 'index.html' === basename( $file ) ) ? home_url( '/' ) : home_url( '/' . $slug . '/' );
		$paths[ $file ] = $route;
	}
	$map = $paths;
	foreach ( $paths as $file => $route ) {
		$base = basename( $file );
		if ( ! isset( $map[ $base ] ) ) {
			$map[ $base ] = $route;
		}
	}
	return apply_filters( 'instawp_homebuild_linkmap', $map );
}

/** The mapped slug for the current request ('' if not a source-rendered page). */
function instawp_homebuild_slug() {
	if ( ! is_page() && ! is_front_page() ) {
		return '';
	}
	$obj  = get_queried_object();
	$slug = ( $obj && isset( $obj->post_name ) ) ? $obj->post_name : '';
	if ( is_front_page() && isset( instawp_homebuild_pages()['home'] ) ) {
		$slug = 'home';
	}
	$map = instawp_homebuild_pages();
	return isset( $map[ $slug ] ) ? $slug : '';
}

function instawp_is_homebuild_page() {
	return instawp_homebuild_slug() !== '';
}

/** Read the source HTML for a slug; '' if unknown/missing. */
function instawp_homebuild_html( $slug ) {
	$map = instawp_homebuild_pages();
	if ( ! isset( $map[ $slug ] ) ) {
		return '';
	}
	$file = INSTAWP_HB_DIR . $map[ $slug ];
	return file_exists( $file ) ? file_get_contents( $file ) : '';
}

/** The <head> portion of a source doc (for asset + style discovery). */
function instawp_homebuild_head( $html ) {
	$end = strpos( $html, '</head>' );
	return $end !== false ? substr( $html, 0, $end ) : $html;
}

/** The source file's <title> (for the document title). '' if none. */
function instawp_homebuild_title( $slug ) {
	$html = instawp_homebuild_html( $slug );
	if ( $html !== '' && preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
		return trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
	}
	return '';
}

/** The source file's meta description. '' if none. */
function instawp_homebuild_description( $slug ) {
	$html = instawp_homebuild_html( $slug );
	if ( $html !== '' && preg_match( '/<meta[^>]+name=["\']description["\'][^>]*>/i', $html, $m )
		&& preg_match( '/content=["\']([^"\']*)["\']/i', $m[0], $c ) ) {
		return trim( html_entity_decode( $c[1], ENT_QUOTES, 'UTF-8' ) );
	}
	return '';
}

/**
 * Render a page BODY: the <head> <style> blocks + the markup between #site-nav
 * and #site-footer, carrying the page's own inline scripts, with assets/ refs
 * rewritten to the source URL and internal .html links rewritten to WP routes.
 */
function instawp_render_homebuild( $slug ) {
	$html = instawp_homebuild_html( $slug );
	if ( $html === '' ) {
		return '<!-- source missing for "' . esc_html( $slug ) . '" -->';
	}
	$head   = instawp_homebuild_head( $html );
	$styles = preg_match_all( '/<style[^>]*>.*?<\/style>/s', $head, $m ) ? implode( "\n", $m[0] ) : '';

	$nav  = '<div id="site-nav"></div>';
	$foot = '<div id="site-footer"></div>';
	$i    = strpos( $html, $nav );
	$j    = strpos( $html, $foot );
	$body = ( $i !== false && $j !== false ) ? substr( $html, $i + strlen( $nav ), $j - $i - strlen( $nav ) ) : '';

	// Carry the page's OWN inline scripts (skip external <script src>, enqueued separately).
	$no_src       = '/<script\b(?![^>]*\ssrc=)[^>]*>.*?<\/script>/is';
	$head_scripts = preg_match_all( $no_src, $head, $hs ) ? implode( "\n", $hs[0] ) : '';
	$tail         = ( $j !== false ) ? substr( $html, $j + strlen( $foot ) ) : '';
	$tail_scripts = preg_match_all( $no_src, $tail, $ts ) ? implode( "\n", $ts[0] ) : '';

	// Rewrite relative asset refs ("assets/", "../assets/", …) to the source URL.
	$assets_re    = '#([("\'])(?:\.\./)*assets/#';
	$repl         = '$1' . INSTAWP_HB_URL . 'assets/';
	$styles       = preg_replace( $assets_re, $repl, $styles );
	$body         = preg_replace( $assets_re, $repl, $body );
	$head_scripts = preg_replace( $assets_re, $repl, $head_scripts );
	$tail_scripts = preg_replace( $assets_re, $repl, $tail_scripts );

	// Rewrite internal page links (relative *.html) to their WP routes — body only.
	$lm   = instawp_homebuild_linkmap();
	$body = preg_replace_callback(
		'#href=(["\'])([^"\']+?\.html)((?:\#[^"\']*)?)\1#i',
		function ( $m ) use ( $lm ) {
			$path  = preg_replace( '#^(?:\./|\.\./)+#', '', $m[2] );
			$route = isset( $lm[ $path ] ) ? $lm[ $path ] : ( isset( $lm[ basename( $m[2] ) ] ) ? $lm[ basename( $m[2] ) ] : '' );
			return $route ? 'href=' . $m[1] . $route . $m[3] . $m[1] : $m[0];
		},
		$body
	);

	// Filter point: Edit in Place annotates the body with data-iwp-id here. No-op otherwise.
	$body = apply_filters( 'instawp_render_body', $body, $slug );

	return $head_scripts . "\n" . $styles . "\n" . $body . "\n" . $tail_scripts;
}

/** Carry the source file's <body class="…"> onto the WP body. */
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

/** Use the source file's crafted <title> as the document title. */
add_filter( 'pre_get_document_title', function ( $title ) {
	$slug = instawp_homebuild_slug();
	if ( ! $slug ) {
		return $title;
	}
	$t = instawp_homebuild_title( $slug );
	return $t !== '' ? $t : $title;
} );

/** Emit the source meta description + canonical (generic; no SEO plugin required). */
add_action( 'wp_head', function () {
	$slug = instawp_homebuild_slug();
	if ( ! $slug ) {
		return;
	}
	$desc = instawp_homebuild_description( $slug );
	if ( $desc !== '' ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<link rel="canonical" href="' . esc_url( get_permalink() ) . '">' . "\n";
}, 1 );

/** Route mapped pages to the plugin's full-page shell (bypasses the theme templates). */
add_filter( 'template_include', function ( $template ) {
	if ( instawp_is_homebuild_page() ) {
		$tpl = IWPS_DIR . 'includes/template.php';
		if ( file_exists( $tpl ) ) {
			return $tpl;
		}
	}
	return $template;
} );

/** Enqueue one source asset (css/js) straight from the source dir; return its handle. */
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

/** After chrome.js injects nav/footer, rewrite its .html links to WP routes by path/basename. */
function instawp_hb_chrome_linkfix( $chrome_handle ) {
	if ( ! wp_script_is( $chrome_handle, 'enqueued' ) ) {
		return;
	}
	$lm  = wp_json_encode( instawp_homebuild_linkmap() );
	$js  = 'var IWP_LM=' . $lm . ';(function(){function f(){'
		. 'document.querySelectorAll(\'a[href$=".html"],a[href*=".html#"]\').forEach(function(a){'
		. 'var h=a.getAttribute("href")||"",c=h.split(/[?#]/)[0],b=c.substring(c.lastIndexOf("/")+1),'
		. 'g=h.indexOf("#")>=0?h.slice(h.indexOf("#")):"",p=c.replace(/^(?:\.\/|\.\.\/)+/,""),'
		. 'r=IWP_LM[p]||IWP_LM[b];if(r)a.setAttribute("href",r+g);});}'
		. 'f();document.addEventListener("DOMContentLoaded",f);window.addEventListener("load",f);'
		. 'if(window.MutationObserver){var o=new MutationObserver(f);o.observe(document.documentElement,{childList:true,subtree:true});setTimeout(function(){o.disconnect();},5000);}})();';
	wp_add_inline_script( $chrome_handle, $js, 'after' );
}

/** On a source-rendered page: enqueue the assets it references + chrome.js. */
add_action( 'wp_enqueue_scripts', function () {
	$slug = instawp_homebuild_slug();
	if ( ! $slug ) {
		return;
	}
	$content = instawp_homebuild_html( $slug );
	if ( preg_match_all( '#(?:href|src)=["\'](?:\.\./)*assets/([a-z0-9_/-]+\.(?:css|js))["\']#i', $content, $mm ) ) {
		foreach ( array_unique( $mm[1] ) as $asset ) {
			instawp_hb_asset( $asset );
		}
	}
	instawp_hb_chrome_linkfix( instawp_hb_asset( 'chrome.js' ) );
} );

/** Theme-independent basics (works with any theme). */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'style', 'script' ) );
} );
