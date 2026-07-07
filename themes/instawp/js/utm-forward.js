/**
 * UTM carry-forward (replaces the prod "url-appender" plugin, minus its needless
 * React settings page + REST endpoint).
 *
 * On landing, capture campaign params from the URL and persist them. Then, when the
 * visitor clicks any outgoing link to the InstaWP app (*.instawp.io), append the
 * stored params so attribution survives the hop into the product.
 *
 * Click-time decoration (capture phase) makes this timing-proof: it works for links
 * injected after load by chrome.js (nav / footer / "Create a site" CTA) without any
 * ordering dependency. Existing params on a link are never overwritten.
 */
(function () {
	var PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'source', 'via', 'ref', 'gclid'];
	var KEY    = 'iwp_utm';
	var TARGET = /(^|\.)instawp\.io$/i; // app.instawp.io and any *.instawp.io

	function readStore() {
		try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
	}
	function writeStore(o) {
		try { localStorage.setItem(KEY, JSON.stringify(o)); } catch (e) {}
	}

	// Capture params present on the current (landing) URL; a fresh landing overwrites.
	function capture() {
		var sp = new URLSearchParams(location.search);
		var store = readStore();
		var got = false;
		PARAMS.forEach(function (p) {
			if (sp.has(p)) { store[p] = sp.get(p); got = true; }
		});
		if (got) { writeStore(store); }
		return store;
	}

	var store = capture();

	function decorate(a) {
		if (!a || !a.getAttribute('href')) { return; }
		var url;
		try { url = new URL(a.href, location.href); } catch (e) { return; }
		if (!TARGET.test(url.hostname)) { return; }
		var changed = false;
		PARAMS.forEach(function (p) {
			if (store[p] && !url.searchParams.has(p)) {
				url.searchParams.set(p, store[p]);
				changed = true;
			}
		});
		if (changed) { a.href = url.toString(); }
	}

	function onInteract(e) {
		var t = e.target;
		var a = (t && t.closest) ? t.closest('a[href]') : null;
		if (a) { decorate(a); }
	}

	// Capture phase so the href is rewritten before navigation; mousedown covers
	// middle-click / open-in-new-tab which never fire a "click".
	document.addEventListener('click', onInteract, true);
	document.addEventListener('mousedown', onInteract, true);
})();
