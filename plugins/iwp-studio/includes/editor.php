<?php
/**
 * Home-build in-place editor  —  LOCAL authoring tool.
 *
 * Lets a logged-in admin, ON LOCALHOST ONLY, click text / images on a rendered
 * marketing page and edit them in place; saves write straight back into the
 * SOURCE file under variations/home-build/. The source stays the single source
 * of truth — this is just a faster way to edit it than opening the .html.
 *
 * How it stays safe + minimal-diff:
 *  - Addressing is by POSITION, not text search: a deterministic scan numbers
 *    every editable element (data-iwp-id = "the Nth editable in document order").
 *    The exact same scan runs on the rendered body (to inject ids) and on the
 *    pristine source file (to locate bytes), so id N == the same element both
 *    sides. Asset/link rewriting only changes attribute *values*, never the set
 *    or order of elements, so the mapping holds.
 *  - TEXT edits sync per text-node (positional), so we never rewrite a single
 *    byte of inline markup or a href/src — only the words between tags change.
 *    A changed node count (added/removed structure) is REJECTED, not guessed.
 *  - IMAGE / BG edits splice just the one attribute value in the open tag.
 *  - Optimistic concurrency: each patch carries its old value; if the source
 *    no longer matches (file edited underneath), that patch is skipped.
 *  - Guarded: localhost only (never *.instawp.site / .com / .io), capability +
 *    REST nonce, slug resolved through instawp_homebuild_pages() (no raw paths),
 *    every write backed up under .iwp-edit-backups/ and written atomically.
 *
 * Scope is deliberately "edit what's there" (text, links, images, backgrounds).
 * It does NOT add / remove / reorder structure — that would be a page builder,
 * which this project rejects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
   GUARD — local only, never the cloud mirror or production
   ===================================================================== */

/** True only on a local dev host (overridable with the INSTAWP_HB_EDITOR constant). */
function instawp_hb_editor_allowed() {
	if ( defined( 'INSTAWP_HB_EDITOR' ) ) {
		return (bool) INSTAWP_HB_EDITOR;
	}
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
	$host = preg_replace( '/:\d+$/', '', $host ); // strip port
	if ( '' === $host ) {
		return false;
	}
	// Hard block: the sandbox mirror, production, and any InstaWP cloud host.
	if ( preg_match( '/instawp\.(site|com|io|xyz)$/', $host ) ) {
		return false;
	}
	// Allow the usual local hosts (LocalWP serves localhost:PORT or *.local).
	if ( 'localhost' === $host || '127.0.0.1' === $host || '::1' === $host ) {
		return true;
	}
	if ( '.local' === substr( $host, -6 ) || '.localhost' === substr( $host, -10 ) || '.test' === substr( $host, -5 ) ) {
		return true;
	}
	return false;
}

/** Should the editor toolbar + id annotation load on this request? */
function instawp_hb_editor_active() {
	return instawp_hb_editor_allowed()
		&& is_user_logged_in()
		&& current_user_can( 'edit_theme_options' )
		&& function_exists( 'instawp_homebuild_slug' )
		&& '' !== instawp_homebuild_slug();
}

/* =====================================================================
   SCANNER — the deterministic editable walk (shared by annotate + save)
   ===================================================================== */

/** Tag classification used by the scanner. */
function instawp_hb_tagsets() {
	static $s = null;
	if ( null !== $s ) {
		return $s;
	}
	$flip = function ( $list ) {
		return array_fill_keys( explode( ' ', $list ), true );
	};
	// Elements that can BE a text edit-unit (block-ish text holders + standalone inline).
	$text = $flip( 'h1 h2 h3 h4 h5 h6 p li blockquote figcaption dt dd td th caption summary legend div address a span strong em b i small code mark cite q time abbr sub sup u s del ins kbd samp var dfn label button output data' );
	// Elements whose PRESENCE as a descendant disqualifies an ancestor from being one
	// text unit (i.e. real structure). Note: many text tags are also block tags.
	$block = $flip( 'h1 h2 h3 h4 h5 h6 p li blockquote figcaption dt dd td th caption summary legend div address section article header footer main aside nav ul ol dl table thead tbody tfoot tr colgroup figure form fieldset pre picture video audio iframe canvas object details menu hr' );
	$void  = $flip( 'area base br col embed hr img input link meta param source track wbr' );
	$raw   = $flip( 'script style textarea title noscript template' );
	// Opening one of these implicitly closes a like sibling already open.
	$implicit = array(
		'li'     => $flip( 'li' ),
		'dt'     => $flip( 'dt dd' ),
		'dd'     => $flip( 'dt dd' ),
		'td'     => $flip( 'td th' ),
		'th'     => $flip( 'td th' ),
		'tr'     => $flip( 'tr td th' ),
		'option' => $flip( 'option' ),
		'p'      => $flip( 'p' ),
	);
	$s = compact( 'text', 'block', 'void', 'raw', 'implicit' );
	return $s;
}

/** Regex matching one HTML token (comment, declaration, or a start/end tag). */
function instawp_hb_token_re() {
	return '#<!--.*?-->|<[!?][^>]*>|</?[a-zA-Z][\w:-]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>#s';
}

/** Offset just after the matching close tag for $tag opened at/after $from (depth aware). */
function instawp_hb_find_close( $html, $from, $tag ) {
	$open  = '<' . $tag;
	$close = '</' . $tag;
	$len   = strlen( $html );
	$depth = 1;
	$pos   = $from;
	while ( $pos < $len ) {
		$o = stripos( $html, $open, $pos );
		$c = stripos( $html, $close, $pos );
		if ( false === $c ) {
			return $len;
		}
		if ( false !== $o && $o < $c ) {
			$nc  = isset( $html[ $o + strlen( $open ) ] ) ? $html[ $o + strlen( $open ) ] : '>';
			$pos = $o + strlen( $open );
			if ( ' ' === $nc || '>' === $nc || '/' === $nc || "\n" === $nc || "\t" === $nc || "\r" === $nc ) {
				$depth++;
			}
		} else {
			$gt    = strpos( $html, '>', $c );
			$pos   = ( false === $gt ) ? $len : $gt + 1;
			$depth--;
			if ( 0 === $depth ) {
				return $pos;
			}
		}
	}
	return $len;
}

/**
 * Scan a body-HTML string and return the ordered list of editable units.
 * Each unit: kind ('text'|'img'|'bg'), tag, open_start, open_end, name_end,
 * and for text: inner_start, inner_end. Ids are assigned by document order.
 */
function instawp_hb_scan( $html ) {
	$ts       = instawp_hb_tagsets();
	$TEXT     = $ts['text'];
	$BLOCK    = $ts['block'];
	$VOID     = $ts['void'];
	$RAW      = $ts['raw'];
	$IMPLICIT = $ts['implicit'];

	if ( ! preg_match_all( instawp_hb_token_re(), $html, $mm, PREG_OFFSET_CAPTURE ) ) {
		return array();
	}

	$units    = array();
	$stack     = array(); // open elements
	$sp        = -1;       // top index
	$cursor    = 0;
	$skip_until = 0;

	$finalize = function ( $e, $inner_end ) use ( &$stack, &$sp, &$units, $TEXT, $BLOCK ) {
		$tag = $e['tag'];
		// A text edit-unit: a text tag that directly holds real words and wraps no
		// real structure. Outermost-vs-nested is resolved in a second pass below.
		if ( isset( $TEXT[ $tag ] ) && $e['has_text'] && ! $e['has_block_desc'] ) {
			$units[] = array(
				'kind'        => 'text',
				'tag'         => $tag,
				'open_start'  => $e['open_start'],
				'open_end'    => $e['open_end'],
				'name_end'    => $e['name_end'],
				'inner_start' => $e['inner_start'],
				'inner_end'   => $inner_end,
			);
		}
		// A block descendant disqualifies the parent from being a single text unit.
		if ( $sp >= 0 && ( isset( $BLOCK[ $tag ] ) || $e['has_block_desc'] ) ) {
			$stack[ $sp ]['has_block_desc'] = true;
		}
	};

	foreach ( $mm[0] as $tok ) {
		$str = $tok[0];
		$off = $tok[1];
		$end = $off + strlen( $str );

		if ( $off < $skip_until ) {
			continue; // inside a raw-text / svg region we already jumped past
		}

		// Text between the previous token and this one belongs to the open element.
		if ( $off > $cursor && $sp >= 0 ) {
			$gap = substr( $html, $cursor, $off - $cursor );
			// Require an actual word char (letter/digit) so decorative glyph-only
			// nodes (arrows, checkmarks, bullets) don't become editable units.
			if ( preg_match( '/[\p{L}\p{N}]/u', $gap ) ) {
				$stack[ $sp ]['has_text'] = true;
			}
		}
		$cursor = $end;

		$c0 = isset( $str[1] ) ? $str[1] : '';
		if ( '!' === $c0 || '?' === $c0 ) {
			continue; // comment / declaration
		}

		$is_close = ( '/' === $c0 );
		if ( ! preg_match( '#^</?([a-zA-Z][\w:-]*)#', $str, $nm ) ) {
			continue;
		}
		$tag = strtolower( $nm[1] );

		if ( $is_close ) {
			// Pop down to the matching open (auto-closing intervening elements).
			$k = $sp;
			while ( $k >= 0 && $stack[ $k ]['tag'] !== $tag ) {
				$k--;
			}
			if ( $k < 0 ) {
				continue; // stray close
			}
			for ( $z = $sp; $z >= $k; $z-- ) {
				$e   = $stack[ $z ];
				$sp  = $z - 1; // expose parent before finalizing so propagation lands right
				$finalize( $e, $off );
			}
			continue;
		}

		// --- opening tag ---
		$selfclose = ( '/>' === substr( $str, -2 ) );

		// implicit sibling close
		if ( isset( $IMPLICIT[ $tag ] ) ) {
			while ( $sp >= 0 && isset( $IMPLICIT[ $tag ][ $stack[ $sp ]['tag'] ] ) ) {
				$e  = $stack[ $sp ];
				$sp--;
				$finalize( $e, $off );
			}
		}

		// raw-text element: skip its content wholesale
		if ( isset( $RAW[ $tag ] ) ) {
			$close      = instawp_hb_find_close( $html, $end, $tag );
			$skip_until = $close;
			$cursor     = $close;
			continue;
		}
		// svg (and friends): skip the whole subtree, mark nothing inside editable
		if ( 'svg' === $tag || 'math' === $tag ) {
			$close      = instawp_hb_find_close( $html, $end, $tag );
			$skip_until = $close;
			$cursor     = $close;
			continue;
		}

		$name_end = $off + 1 + strlen( $tag );

		// background-image holder?  (independent of text editing)
		if ( false !== stripos( $str, 'background-image' ) || preg_match( '/\sdata-(vc-)?bg\s*=/i', $str ) ) {
			$units[] = array(
				'kind'       => 'bg',
				'tag'        => $tag,
				'open_start' => $off,
				'open_end'   => $end,
				'name_end'   => $name_end,
			);
		}

		if ( isset( $VOID[ $tag ] ) || $selfclose ) {
			if ( 'img' === $tag ) {
				$units[] = array(
					'kind'       => 'img',
					'tag'        => $tag,
					'open_start' => $off,
					'open_end'   => $end,
					'name_end'   => $name_end,
				);
			}
			continue; // void / self-closing: nothing to push
		}

		$sp++;
		$stack[ $sp ] = array(
			'tag'            => $tag,
			'open_start'     => $off,
			'open_end'       => $end,
			'name_end'       => $name_end,
			'inner_start'    => $end,
			'has_text'       => false,
			'has_block_desc' => false,
		);
	}

	// Finalize anything left open (unclosed at EOF).
	$tail = strlen( $html );
	for ( $z = $sp; $z >= 0; $z-- ) {
		$e  = $stack[ $z ];
		$sp = $z - 1;
		$finalize( $e, $tail );
	}

	// Resolve text-vs-text nesting: keep only the OUTERMOST text unit, so a heading
	// or paragraph with inline children (<span>, <a>, <b>) is edited as ONE unit
	// (its inner text nodes) instead of being split. Images/backgrounds always kept.
	$texts = array();
	$other = array();
	foreach ( $units as $u ) {
		if ( 'text' === $u['kind'] ) {
			$texts[] = $u;
		} else {
			$other[] = $u;
		}
	}
	usort(
		$texts,
		function ( $a, $b ) {
			return $a['open_start'] <=> $b['open_start'];
		}
	);
	$kept     = array();
	$last_end = -1;
	foreach ( $texts as $u ) {
		if ( $u['open_start'] < $last_end ) {
			continue; // nested inside a kept text unit
		}
		$kept[]   = $u;
		$last_end = $u['inner_end'];
	}

	// Combine, order by document position, assign stable ids.
	$units = array_merge( $kept, $other );
	usort(
		$units,
		function ( $a, $b ) {
			return $a['open_start'] <=> $b['open_start'];
		}
	);
	foreach ( $units as $i => &$u ) {
		$u['id'] = $i;
	}
	unset( $u );
	return $units;
}

/* =====================================================================
   ANNOTATE — inject data-iwp-id into the rendered body (render time)
   ===================================================================== */

/** Add data-iwp-id / data-iwp-kind to every editable element's open tag. */
function instawp_hb_annotate( $body ) {
	$units = instawp_hb_scan( $body );
	if ( ! $units ) {
		return $body;
	}
	// Build (offset, insert) pairs and apply from the end so offsets stay valid.
	$ins = array();
	foreach ( $units as $u ) {
		$ins[] = array( $u['name_end'], ' data-iwp-id="' . $u['id'] . '" data-iwp-kind="' . $u['kind'] . '"' );
	}
	usort(
		$ins,
		function ( $a, $b ) {
			return $b[0] <=> $a[0];
		}
	);
	foreach ( $ins as $pair ) {
		$body = substr( $body, 0, $pair[0] ) . $pair[1] . substr( $body, $pair[0] );
	}
	return $body;
}

// Hook the renderer's body filter (added in functions.php) — only when active.
add_filter(
	'instawp_render_body',
	function ( $body ) {
		return instawp_hb_editor_active() ? instawp_hb_annotate( $body ) : $body;
	}
);

/* =====================================================================
   APPLY — turn patches into a minimal surgical splice of the source
   ===================================================================== */

/** Text nodes (gaps between tags) within [start,end) of $html, as start/end offsets. */
function instawp_hb_text_segments( $html, $start, $end ) {
	$inner = substr( $html, $start, $end - $start );
	$segs  = array();
	$cur   = 0;
	if ( preg_match_all( instawp_hb_token_re(), $inner, $mm, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $mm[0] as $tok ) {
			$o = $tok[1];
			if ( $o > $cur ) {
				$segs[] = array( 'start' => $start + $cur, 'end' => $start + $o );
			}
			$cur = $o + strlen( $tok[0] );
		}
	}
	if ( $cur < strlen( $inner ) ) {
		$segs[] = array( 'start' => $start + $cur, 'end' => $start + strlen( $inner ) );
	}
	return $segs;
}

/** Minimal text encode for content (keeps source valid; leaves quotes alone). */
function instawp_hb_enc_text( $s ) {
	return htmlspecialchars( $s, ENT_NOQUOTES, 'UTF-8' );
}

/** Set (or insert) an attribute in a single open-tag string. */
function instawp_hb_set_attr( $tag, $name, $val ) {
	$esc = htmlspecialchars( $val, ENT_QUOTES, 'UTF-8' );
	$re  = '/(\s' . preg_quote( $name, '/' ) . '\s*=\s*)("[^"]*"|\'[^\']*\')/i';
	if ( preg_match( $re, $tag ) ) {
		return preg_replace_callback(
			$re,
			function ( $m ) use ( $esc ) {
				return $m[1] . '"' . $esc . '"';
			},
			$tag,
			1
		);
	}
	// insert before the closing > (or />)
	if ( '/>' === substr( $tag, -2 ) ) {
		return substr( $tag, 0, -2 ) . ' ' . $name . '="' . $esc . '" />';
	}
	return substr( $tag, 0, -1 ) . ' ' . $name . '="' . $esc . '">';
}

/** Replace the url in an element's background-image (style attr or data-bg). */
function instawp_hb_set_bg( $tag, $url ) {
	if ( preg_match( '/background-image\s*:\s*url\(/i', $tag ) ) {
		return preg_replace_callback(
			'/(background-image\s*:\s*url\(\s*)(["\']?)([^"\')]*)(["\']?\s*\))/i',
			function ( $m ) use ( $url ) {
				return $m[1] . $m[2] . $url . $m[2] . ')';
			},
			$tag,
			1
		);
	}
	foreach ( array( 'data-vc-bg', 'data-bg' ) as $a ) {
		if ( stripos( $tag, $a ) !== false ) {
			return instawp_hb_set_attr( $tag, $a, $url );
		}
	}
	return $tag;
}

/** Normalise an image URL: strip our own origin to a root-relative path (portable). */
function instawp_hb_norm_url( $url ) {
	$url  = trim( (string) $url );
	$home = home_url();
	$base = preg_replace( '#^https?://#', '', $home );
	if ( preg_match( '#^https?://' . preg_quote( $base, '#' ) . '(/.*)$#', $url, $m ) ) {
		return $m[1];
	}
	return $url;
}

/**
 * Pure core: given the FULL source string + patches, return array(newHtml, results).
 * Does not touch disk. results[] = array(id, kind, status). status: saved|unchanged|
 * conflict|structure|no-target|bad.
 */
function instawp_hb_apply_to_string( $full, $patches ) {
	$results = array();
	$nav  = '<div id="site-nav"></div>';
	$foot = '<div id="site-footer"></div>';
	$i    = strpos( $full, $nav );
	$j    = strpos( $full, $foot );
	if ( false === $i || false === $j || $j < $i ) {
		return array( $full, array( array( 'status' => 'bad', 'reason' => 'no body markers' ) ) );
	}
	$body_start = $i + strlen( $nav );
	$slice      = substr( $full, $body_start, $j - $body_start );

	$units = instawp_hb_scan( $slice );
	$by_id = array();
	foreach ( $units as $u ) {
		$by_id[ $u['id'] ] = $u;
	}

	$splices = array(); // each: array(abs_start, abs_end, replacement)

	foreach ( (array) $patches as $p ) {
		$id   = isset( $p['id'] ) ? (int) $p['id'] : -1;
		$kind = isset( $p['kind'] ) ? $p['kind'] : '';
		if ( ! isset( $by_id[ $id ] ) ) {
			$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'no-target' );
			continue;
		}
		$u = $by_id[ $id ];

		if ( 'text' === $kind && 'text' === $u['kind'] ) {
			$segs  = instawp_hb_text_segments( $slice, $u['inner_start'], $u['inner_end'] );
			$texts = isset( $p['texts'] ) && is_array( $p['texts'] ) ? array_values( $p['texts'] ) : array();
			$olds  = isset( $p['oldTexts'] ) && is_array( $p['oldTexts'] ) ? array_values( $p['oldTexts'] ) : array();
			if ( count( $segs ) !== count( $texts ) ) {
				$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'structure' );
				continue;
			}
			$local    = array();
			$conflict = false;
			$changed  = false;
			foreach ( $segs as $k => $seg ) {
				$src_raw = substr( $slice, $seg['start'], $seg['end'] - $seg['start'] );
				$src_dec = html_entity_decode( $src_raw, ENT_QUOTES, 'UTF-8' );
				$new     = (string) $texts[ $k ];
				$old     = array_key_exists( $k, $olds ) ? (string) $olds[ $k ] : null;
				if ( null !== $old && trim( $src_dec ) !== trim( $old ) ) {
					$conflict = true;
					break;
				}
				if ( trim( $src_dec ) === trim( $new ) ) {
					continue; // this node unchanged
				}
				preg_match( '/^\s*/', $src_raw, $lw );
				preg_match( '/\s*$/', $src_raw, $tw );
				$repl    = $lw[0] . instawp_hb_enc_text( trim( $new ) ) . $tw[0];
				$local[] = array( $body_start + $seg['start'], $body_start + $seg['end'], $repl );
				$changed = true;
			}
			if ( $conflict ) {
				$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'conflict' );
				continue;
			}
			if ( ! $changed ) {
				$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'unchanged' );
				continue;
			}
			foreach ( $local as $s ) {
				$splices[] = $s;
			}
			$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'saved' );
			continue;
		}

		if ( 'img' === $kind && 'img' === $u['kind'] ) {
			$open    = substr( $slice, $u['open_start'], $u['open_end'] - $u['open_start'] );
			$new_tag = instawp_hb_set_attr( $open, 'src', instawp_hb_norm_url( isset( $p['src'] ) ? $p['src'] : '' ) );
			if ( isset( $p['alt'] ) ) {
				$new_tag = instawp_hb_set_attr( $new_tag, 'alt', (string) $p['alt'] );
			}
			if ( $new_tag === $open ) {
				$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'unchanged' );
				continue;
			}
			$splices[] = array( $body_start + $u['open_start'], $body_start + $u['open_end'], $new_tag );
			$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'saved' );
			continue;
		}

		if ( 'bg' === $kind && 'bg' === $u['kind'] ) {
			$open    = substr( $slice, $u['open_start'], $u['open_end'] - $u['open_start'] );
			$new_tag = instawp_hb_set_bg( $open, instawp_hb_norm_url( isset( $p['url'] ) ? $p['url'] : '' ) );
			if ( $new_tag === $open ) {
				$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'unchanged' );
				continue;
			}
			$splices[] = array( $body_start + $u['open_start'], $body_start + $u['open_end'], $new_tag );
			$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'saved' );
			continue;
		}

		$results[] = array( 'id' => $id, 'kind' => $kind, 'status' => 'no-target' );
	}

	// Apply splices high-offset first so earlier offsets stay valid. Reject overlaps.
	usort(
		$splices,
		function ( $a, $b ) {
			return $b[0] <=> $a[0];
		}
	);
	$last_start = PHP_INT_MAX;
	foreach ( $splices as $sp ) {
		list( $s, $e, $r ) = $sp;
		if ( $e > $last_start ) {
			continue; // overlapping range — skip (shouldn't happen with disjoint units)
		}
		$full       = substr( $full, 0, $s ) . $r . substr( $full, $e );
		$last_start = $s;
	}

	return array( $full, $results );
}

/* =====================================================================
   REST — receive patches, write the source file (backup + atomic)
   ===================================================================== */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'iwp-edit/v1',
			'/save',
			array(
				'methods'             => 'POST',
				'callback'            => 'instawp_hb_rest_save',
				'permission_callback' => function () {
					return instawp_hb_editor_allowed() && is_user_logged_in() && current_user_can( 'edit_theme_options' );
				},
			)
		);
	}
);

function instawp_hb_rest_save( $req ) {
	$slug    = sanitize_key( (string) $req->get_param( 'slug' ) );
	$patches = $req->get_param( 'patches' );
	if ( '' === $slug || ! is_array( $patches ) ) {
		return new WP_Error( 'iwp_bad', 'Bad request.', array( 'status' => 400 ) );
	}
	if ( count( $patches ) > 500 ) {
		return new WP_Error( 'iwp_bad', 'Too many patches.', array( 'status' => 400 ) );
	}
	$map = instawp_homebuild_pages();
	if ( ! isset( $map[ $slug ] ) ) {
		return new WP_Error( 'iwp_bad', 'Unknown page.', array( 'status' => 400 ) );
	}

	$rel  = $map[ $slug ];
	$file = INSTAWP_HB_DIR . $rel;
	$real = realpath( $file );
	$root = realpath( INSTAWP_HB_DIR );
	if ( ! $real || ! $root || 0 !== strpos( $real, $root ) || ! is_writable( $real ) ) {
		return new WP_Error( 'iwp_io', 'Source file not writable.', array( 'status' => 500 ) );
	}

	$full = file_get_contents( $real );
	if ( false === $full ) {
		return new WP_Error( 'iwp_io', 'Could not read source.', array( 'status' => 500 ) );
	}

	list( $new, $results ) = instawp_hb_apply_to_string( $full, $patches );

	$saved = 0;
	foreach ( $results as $r ) {
		if ( isset( $r['status'] ) && 'saved' === $r['status'] ) {
			$saved++;
		}
	}

	if ( $saved > 0 && $new !== $full ) {
		instawp_hb_backup( $real, $rel, $full );
		$tmp = $real . '.iwp-tmp-' . wp_generate_password( 6, false );
		if ( false === file_put_contents( $tmp, $new ) || ! rename( $tmp, $real ) ) {
			@unlink( $tmp );
			return new WP_Error( 'iwp_io', 'Write failed.', array( 'status' => 500 ) );
		}
	}

	return rest_ensure_response(
		array(
			'ok'      => true,
			'slug'    => $slug,
			'file'    => $rel,
			'saved'   => $saved,
			'results' => $results,
		)
	);
}

/** Keep a timestamped backup of the pre-edit source; prune to the last 10 per file. */
function instawp_hb_backup( $real, $rel, $contents ) {
	$dir = INSTAWP_HB_DIR . '.iwp-edit-backups';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$key  = str_replace( array( '/', '\\' ), '__', $rel );
	$stamp = gmdate( 'Ymd-His' );
	@file_put_contents( $dir . '/' . $key . '.' . $stamp . '.bak', $contents );
	$glob = glob( $dir . '/' . $key . '.*.bak' );
	if ( $glob && count( $glob ) > 10 ) {
		sort( $glob );
		foreach ( array_slice( $glob, 0, count( $glob ) - 10 ) as $old ) {
			@unlink( $old );
		}
	}
}

/* =====================================================================
   ENQUEUE — toolbar + media frame (local admins, marketing pages only)
   ===================================================================== */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! instawp_hb_editor_active() ) {
			return;
		}
		wp_enqueue_media();

		$css = IWPS_DIR . 'assets/hb-editor.css';
		$js  = IWPS_DIR . 'assets/hb-editor.js';
		wp_enqueue_style( 'instawp-hb-editor', IWPS_URL . 'assets/hb-editor.css', array(), file_exists( $css ) ? filemtime( $css ) : '1' );
		wp_enqueue_script( 'instawp-hb-editor', IWPS_URL . 'assets/hb-editor.js', array( 'media-editor' ), file_exists( $js ) ? filemtime( $js ) : '1', true );

		$slug = instawp_homebuild_slug();
		$map  = instawp_homebuild_pages();
		wp_localize_script(
			'instawp-hb-editor',
			'IWP_EDIT',
			array(
				'rest'  => esc_url_raw( rest_url( 'iwp-edit/v1/save' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'slug'  => $slug,
				'file'  => isset( $map[ $slug ] ) ? $map[ $slug ] : '',
			)
		);
	},
	100
);

/* =====================================================================
   ADMIN BAR — a discoverable "Edit this page" entry point
   ===================================================================== */

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! instawp_hb_editor_allowed() || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		// Distinct icon (Customizer paintbrush), not the default edit pencil.
		$icon = '<span class="ab-icon dashicons dashicons-admin-customizer" style="top:2px"></span>';

		// Front end: on a marketing page, the editor JS binds this node to toggle edit mode.
		if ( ! is_admin() ) {
			if ( '' === instawp_homebuild_slug() ) {
				return;
			}
			$bar->add_node(
				array(
					'id'    => 'iwp-edit',
					'title' => $icon . '<span class="ab-label" id="iwp-ab-label">Edit in Place</span>',
					'href'  => '#iwp-edit',
					'meta'  => array( 'title' => 'Edit in Place' ),
				)
			);
			return;
		}

		// wp-admin: when editing a home-build page in the block editor, offer a one-click
		// jump to the real visual editor on the front end (its content isn't in the DB).
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen  = get_current_screen();
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $screen || 'post' !== $screen->base || ! $post_id ) {
			return;
		}
		$post = get_post( $post_id );
		$map  = instawp_homebuild_pages();
		if ( $post && isset( $map[ $post->post_name ] ) ) {
			$bar->add_node(
				array(
					'id'    => 'iwp-edit',
					'title' => $icon . '<span class="ab-label">Edit in Place</span>',
					'href'  => get_permalink( $post ) . '#iwp-edit',
					'meta'  => array( 'title' => 'Edit in Place on the front end' ),
				)
			);
		}
	},
	90
);
