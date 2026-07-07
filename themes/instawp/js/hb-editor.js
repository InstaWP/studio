/**
 * Home-build in-place editor (client). LOCAL admin tooling — paired with
 * inc/homebuild-editor.php. Click text / images on the rendered marketing page,
 * edit in place, Save writes straight back to the variations/home-build source.
 *
 * Text is sent as its ordered text-nodes (+ the originals for concurrency), so
 * the server can swap only the changed words and never touches inline markup or
 * hrefs. Images/backgrounds send the chosen Media Library URL.
 */
( function () {
	'use strict';

	var CFG = window.IWP_EDIT;
	if ( ! CFG || ! CFG.rest ) {
		return;
	}

	var editing  = false;
	var dirty    = {};               // id -> element
	var origText = new WeakMap();     // text element -> [original node strings]
	var frame    = null;             // wp.media frame (lazy)
	var bar, btnSave, btnExit, statusEl;            // the editing HUD (shown while editing)
	var abNode = null, abLabel = null, fallbackBtn = null; // entry points

	/* ----------------------------------------------------------- helpers */

	function textNodes( el ) {
		el.normalize();
		var w = document.createTreeWalker( el, NodeFilter.SHOW_TEXT, null ), out = [], n;
		while ( ( n = w.nextNode() ) ) {
			out.push( n.nodeValue );
		}
		return out;
	}
	function sameList( a, b ) {
		if ( a.length !== b.length ) {
			return false;
		}
		for ( var i = 0; i < a.length; i++ ) {
			if ( a[ i ] !== b[ i ] ) {
				return false;
			}
		}
		return true;
	}
	function count() {
		return Object.keys( dirty ).length;
	}
	function units() {
		return document.querySelectorAll( '[data-iwp-id]' );
	}
	function setStatus( txt, kind ) {
		statusEl.textContent = txt || '';
		statusEl.className = 'iwp-status' + ( kind ? ' ' + kind : '' );
	}

	/* -------------------------------------------------------- the toolbar */

	function buildBar() {
		bar = document.createElement( 'div' );
		bar.id = 'iwp-editbar';
		bar.hidden = true; // the HUD is shown only while editing
		bar.innerHTML =
			'<span class="iwp-brand">Editing</span>' +
			'<button type="button" class="iwp-btn iwp-primary" data-act="save">Save</button>' +
			'<button type="button" class="iwp-btn iwp-ghost" data-act="exit">Exit</button>' +
			'<span class="iwp-status"></span>';
		document.body.appendChild( bar );
		btnSave  = bar.querySelector( '[data-act="save"]' );
		btnExit  = bar.querySelector( '[data-act="exit"]' );
		statusEl = bar.querySelector( '.iwp-status' );

		btnSave.addEventListener( 'click', save );
		btnExit.addEventListener( 'click', function () {
			if ( ! count() || window.confirm( 'Discard ' + count() + ' unsaved change(s)?' ) ) {
				exit( false );
			}
		} );
	}

	function refreshBar() {
		bar.hidden = ! editing;
		btnSave.textContent = count() ? 'Save ' + count() : 'Save';
		btnSave.disabled = ! count();
	}

	/** Sync the entry point (admin-bar node / fallback button) with edit state. */
	function updateEntry() {
		if ( abLabel ) {
			abLabel.textContent = editing ? 'Editing, click to stop' : 'Edit in Place';
		}
		if ( abNode ) {
			abNode.classList.toggle( 'iwp-ab-on', editing );
		}
		if ( fallbackBtn ) {
			fallbackBtn.hidden = editing; // HUD takes over while editing
		}
	}

	/* ---------------------------------------------------- enter / exit mode */

	function wire( el, kind, on ) {
		var m = on ? 'addEventListener' : 'removeEventListener';
		if ( 'text' === kind ) {
			el[ m ]( 'input', onInput );
			el[ m ]( 'keydown', onKey );
			el[ m ]( 'paste', onPaste );
		} else {
			el[ m ]( 'click', onMediaClick );
		}
	}

	function enter() {
		editing = true;
		try { sessionStorage.setItem( 'iwpEditing', CFG.slug ); } catch ( e ) {}
		document.body.classList.add( 'iwp-editing' );
		var els = units();
		for ( var i = 0; i < els.length; i++ ) {
			var el = els[ i ], kind = el.getAttribute( 'data-iwp-kind' );
			if ( 'text' === kind ) {
				origText.set( el, textNodes( el ) );
				el.setAttribute( 'contenteditable', 'true' );
				el.spellcheck = false;
			}
			wire( el, kind, true );
		}
		document.addEventListener( 'click', blockNav, true );
		setStatus( 'Click any text or image to edit.' );
		refreshBar();
		updateEntry();
	}

	function exit( reload ) {
		editing = false;
		try { sessionStorage.removeItem( 'iwpEditing' ); } catch ( e ) {}
		document.removeEventListener( 'click', blockNav, true );
		var els = units();
		for ( var i = 0; i < els.length; i++ ) {
			var el = els[ i ], kind = el.getAttribute( 'data-iwp-kind' );
			if ( 'text' === kind ) {
				el.removeAttribute( 'contenteditable' );
			}
			el.classList.remove( 'iwp-dirty' );
			wire( el, kind, false );
		}
		document.body.classList.remove( 'iwp-editing' );
		dirty = {};
		updateEntry();
		if ( reload ) {
			location.reload();
		} else {
			setStatus( '' );
			refreshBar();
		}
	}

	/* ----------------------------------------------------------- text edits */

	function onInput( e ) {
		mark( e.currentTarget );
	}
	function onKey( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault(); // keep the element's structure single — no new nodes
		}
	}
	function onPaste( e ) {
		e.preventDefault();
		var t = ( e.clipboardData || window.clipboardData ).getData( 'text' ) || '';
		document.execCommand( 'insertText', false, t.replace( /\s*\n\s*/g, ' ' ) );
	}

	function mark( el ) {
		var id = el.getAttribute( 'data-iwp-id' );
		if ( 'text' === el.getAttribute( 'data-iwp-kind' ) ) {
			var o = origText.get( el ) || [];
			if ( sameList( textNodes( el ), o ) ) {
				delete dirty[ id ];
				el.classList.remove( 'iwp-dirty' );
				refreshBar();
				return;
			}
		}
		dirty[ id ] = el;
		el.classList.add( 'iwp-dirty' );
		refreshBar();
	}

	/* ----------------------------------------------- images + backgrounds */

	function onMediaClick( e ) {
		if ( ! editing ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var el = e.currentTarget;
		if ( ! window.wp || ! window.wp.media ) {
			setStatus( 'Media library unavailable.', 'err' );
			return;
		}
		if ( ! frame ) {
			frame = window.wp.media( {
				title: 'Choose an image',
				button: { text: 'Use this image' },
				multiple: false,
				library: { type: 'image' }
			} );
		}
		frame.off( 'select' );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			var url = a.url, alt = a.alt || '';
			if ( 'img' === el.getAttribute( 'data-iwp-kind' ) ) {
				el.setAttribute( 'src', url );
				if ( alt ) {
					el.setAttribute( 'alt', alt );
				}
				el.setAttribute( 'data-iwp-src', url );
				el.setAttribute( 'data-iwp-alt', alt );
			} else {
				el.style.backgroundImage = "url('" + url + "')";
				el.setAttribute( 'data-iwp-bg', url );
			}
			mark( el );
		} );
		frame.open();
	}

	/* ------------------------------------------ no navigation while editing */

	function blockNav( e ) {
		if ( ! editing ) {
			return;
		}
		var t = e.target;
		if ( t && t.closest && t.closest( 'a' ) ) {
			e.preventDefault(); // editing link text must not navigate
		}
	}

	/* ---------------------------------------------------------------- save */

	function buildPatches() {
		var P = [];
		Object.keys( dirty ).forEach( function ( id ) {
			var el = dirty[ id ], kind = el.getAttribute( 'data-iwp-kind' );
			if ( 'text' === kind ) {
				P.push( { id: + id, kind: 'text', texts: textNodes( el ), oldTexts: origText.get( el ) || [] } );
			} else if ( 'img' === kind ) {
				P.push( { id: + id, kind: 'img', src: el.getAttribute( 'data-iwp-src' ) || el.getAttribute( 'src' ) || '', alt: el.getAttribute( 'data-iwp-alt' ) || el.getAttribute( 'alt' ) || '' } );
			} else if ( 'bg' === kind ) {
				P.push( { id: + id, kind: 'bg', url: el.getAttribute( 'data-iwp-bg' ) || '' } );
			}
		} );
		return P;
	}

	function save() {
		if ( ! count() ) {
			return;
		}
		var patches = buildPatches();
		setStatus( 'Saving ' + patches.length + '…' );
		btnSave.disabled = true;
		fetch( CFG.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce },
			body: JSON.stringify( { slug: CFG.slug, patches: patches } )
		} ).then( function ( r ) {
			return r.json().then( function ( j ) { return { ok: r.ok, j: j }; } );
		} ).then( function ( resp ) {
			if ( ! resp.ok || ! resp.j || ! resp.j.ok ) {
				setStatus( 'Save failed: ' + ( ( resp.j && resp.j.message ) || 'error' ), 'err' );
				btnSave.disabled = false;
				return;
			}
			var saved = resp.j.saved || 0;
			var skipped = ( resp.j.results || [] ).filter( function ( x ) {
				return 'conflict' === x.status || 'structure' === x.status;
			} );
			if ( skipped.length ) {
				setStatus( 'Saved ' + saved + ', skipped ' + skipped.length + ' (changed underneath / structure). Reloading…', 'warn' );
			} else {
				setStatus( 'Saved ' + saved + ' to ' + CFG.file + '. Reloading…', 'ok' );
			}
			setTimeout( function () { exit( true ); }, 1000 );
		} ).catch( function ( err ) {
			setStatus( 'Save error: ' + err, 'err' );
			btnSave.disabled = false;
		} );
	}

	/* ---------------------------------------------------------------- boot */

	function boot() {
		buildBar();

		// Entry point: prefer the WP admin-bar node; otherwise a small floating button.
		abNode  = document.getElementById( 'wp-admin-bar-iwp-edit' );
		abLabel = document.getElementById( 'iwp-ab-label' );
		if ( abNode ) {
			var a = abNode.querySelector( 'a' );
			if ( a ) {
				a.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					editing ? exit( false ) : enter();
				} );
			}
		} else {
			fallbackBtn = document.createElement( 'button' );
			fallbackBtn.id = 'iwp-editfab';
			fallbackBtn.type = 'button';
			fallbackBtn.textContent = 'Edit in Place';
			fallbackBtn.addEventListener( 'click', function () {
				editing ? exit( false ) : enter();
			} );
			document.body.appendChild( fallbackBtn );
		}

		refreshBar();
		updateEntry();

		// Arriving with #iwp-edit (e.g. the admin "Edit in Place" jump) opens edit mode.
		var wantHash = false;
		try { wantHash = '#iwp-edit' === location.hash; } catch ( e ) {}
		if ( wantHash && window.history && history.replaceState ) {
			history.replaceState( null, '', location.pathname + location.search );
		}
		var resume = false;
		try { resume = sessionStorage.getItem( 'iwpEditing' ) === CFG.slug; } catch ( e ) {}
		if ( resume || wantHash ) {
			enter();
		}
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
