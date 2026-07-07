/* InstaWP Feedback — front-end widget. Vanilla JS, no deps.
 * Floating launcher -> "placing" mode -> click any spot -> compose note tied to that
 * element. Existing notes render as numbered pins (click to read). Gated server-side;
 * this just renders when enqueued. */
(function () {
	'use strict';

	var C = window.IWPFB;
	if (!C || window.__iwpfb_init) { return; }
	window.__iwpfb_init = true;

	var T = C.i18n || {};
	var NAME_KEY = 'iwpfb_name';

	/* ----------------------------------------------------------- utilities */

	function normPath(p) {
		p = '/' + String(p || '').replace(/^\/+/, '');
		p = p.replace(/\/+$/, '');
		return p === '' ? '/' : p;
	}
	var PATH = normPath(location.pathname);
	var PAGE_URL = location.origin + location.pathname + location.search;

	// Stable per-browser author token, so a person can recognise their own notes
	// (and so logged-out teammates have an identity without logging in).
	var MYUID = (function () {
		var u = '';
		try { u = localStorage.getItem('iwpfb_uid') || ''; } catch (e) {}
		if (!u) {
			u = 'u' + Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
			try { localStorage.setItem('iwpfb_uid', u); } catch (e) {}
		}
		return u;
	})();

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}
	function clamp01(n) { n = +n || 0; return n < 0 ? 0 : (n > 1 ? 1 : n); }
	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (html != null) { n.innerHTML = html; }
		return n;
	}
	function cssEsc(s) {
		return (window.CSS && CSS.escape) ? CSS.escape(s) : String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
	}
	function isUniq(sel) { try { return document.querySelectorAll(sel).length === 1; } catch (e) { return false; } }
	function safeQuery(sel) { try { return sel ? document.querySelector(sel) : null; } catch (e) { return null; } }

	/* Send the REST nonce only when logged in. A logged-out visitor (esp. on a
	   full-page-cached page) may carry a stale nonce that WP would 403; with no
	   nonce sent, WP skips the check and our open permission_callback allows it. */
	function authHeaders(extra) {
		var h = extra || {};
		if (C.loggedIn && C.nonce) { h['X-WP-Nonce'] = C.nonce; }
		return h;
	}

	/* Build a reasonably stable CSS path (id shortcut + tag:nth-of-type chain). */
	function cssPath(node) {
		if (!(node instanceof Element)) { return ''; }
		var parts = [], depth = 0;
		while (node && node.nodeType === 1 && node !== document.documentElement && depth < 8) {
			if (node.id && isUniq('#' + cssEsc(node.id))) {
				parts.unshift('#' + cssEsc(node.id));
				return parts.join(' > ');
			}
			var sel = node.nodeName.toLowerCase();
			var p = node.parentNode;
			if (p && p.children) {
				var same = [];
				for (var i = 0; i < p.children.length; i++) {
					if (p.children[i].nodeName === node.nodeName) { same.push(p.children[i]); }
				}
				if (same.length > 1) { sel += ':nth-of-type(' + (same.indexOf(node) + 1) + ')'; }
			}
			parts.unshift(sel);
			node = node.parentNode;
			depth++;
		}
		return parts.join(' > ');
	}
	function elementHint(node) {
		if (!node || node.nodeType !== 1) { return ''; }
		var tag = node.nodeName.toLowerCase();
		var txt = (node.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 44);
		return txt ? tag + ' · "' + txt + '"' : tag;
	}

	/* page-coordinate position for a (saved or temp) note */
	function coordsFor(d) {
		var node = d.selector ? safeQuery(d.selector) : null;
		if (node) {
			var r = node.getBoundingClientRect();
			return {
				x: r.left + window.scrollX + (+d.rel_x || 0) * r.width,
				y: r.top + window.scrollY + (+d.rel_y || 0) * r.height
			};
		}
		return { x: +d.page_x || 0, y: +d.page_y || 0 };
	}

	/* ------------------------------------------------------------- state */

	var pins = [];           // saved notes for this page
	var pinsVisible = true;
	var mineOnly = false;    // filter pins to just the current author's
	var mode = 'idle';       // idle | placing
	var activePop = null;    // open popover element
	var activeTemp = null;   // temp pin element during compose
	var banner = null;

	/* ----------------------------------------------------------- DOM base */

	var pinLayer = el('div'); pinLayer.id = 'iwpfb-pins';
	document.body.appendChild(pinLayer);

	var CHAT_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
	var CLOSE_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';

	var root = el('div'); root.id = 'iwpfb-root';
	root.innerHTML =
		'<button class="iwpfb-launch" type="button" aria-label="' + esc(T.launch || 'Feedback') + '" title="' + esc(T.launch || 'Feedback') + '">' +
			'<span class="iwpfb-ic iwpfb-ic-chat">' + CHAT_SVG + '</span>' +
			'<span class="iwpfb-ic iwpfb-ic-close">' + CLOSE_SVG + '</span>' +
			'<span class="iwpfb-count"></span></button>' +
		'<div class="iwpfb-menu">' +
			'<button type="button" class="iwpfb-primary" data-act="place">＋ ' + esc(T.place || 'Leave feedback') + '</button>' +
			'<button type="button" data-act="togglepins"><span class="iwpfb-pinlabel">' + esc(T.hidepins || 'Hide pins') + '</span></button>' +
			'<button type="button" data-act="togglemine"><span class="iwpfb-minelabel">' + esc(T.mineonly || 'Show only mine') + '</span></button>' +
			'<hr>' +
			'<div class="iwpfb-mini" style="padding:6px 10px"><span class="iwpfb-menucount">0</span> ' + esc('notes on this page') + '</div>' +
			'<button type="button" data-act="hideme">' + esc(T.hideme || 'Hide widget for me') + '</button>' +
		'</div>';
	document.body.appendChild(root);

	var launchBtn = root.querySelector('.iwpfb-launch');
	var menu = root.querySelector('.iwpfb-menu');
	var countEl = root.querySelector('.iwpfb-count');
	var menuCountEl = root.querySelector('.iwpfb-menucount');
	var pinLabelEl = root.querySelector('.iwpfb-pinlabel');
	var mineLabelEl = root.querySelector('.iwpfb-minelabel');

	function mineCount() {
		return pins.filter(function (d) { return d.mine; }).length;
	}
	function updateCount() {
		var total = pins.length, mc = mineCount();
		countEl.textContent = total ? String(total) : '';
		menuCountEl.textContent = String(total);
		pinLabelEl.textContent = pinsVisible ? (T.hidepins || 'Hide pins') : (T.showpins || 'Show pins');
		mineLabelEl.textContent = (mineOnly ? (T.showall || 'Show all notes') : (T.mineonly || 'Show only mine'))
			+ (mc ? ' (' + mc + ')' : '');
	}

	/* -------------------------------------------------------- pin render */

	function renderPins() {
		pinLayer.innerHTML = '';
		pins.forEach(function (d) { d._el = null; });
		if (pinsVisible) {
			var list = mineOnly ? pins.filter(function (d) { return d.mine; }) : pins;
			list.forEach(function (d, idx) {
				var c = coordsFor(d);
				var cls = 'iwpfb-pin'
					+ ((d.status === 'resolved' || d.status === 'wontfix') ? ' iwpfb-resolved' : '')
					+ (d.mine ? ' iwpfb-mine' : '');
				var b = el('button', cls, '<i><span>' + (idx + 1) + '</span></i>');
				b.type = 'button';
				b.style.left = c.x + 'px';
				b.style.top = c.y + 'px';
				b.setAttribute('aria-label', (d.mine ? (T.you || 'You') + ' · ' : d.name + ': ') + d.message);
				b.addEventListener('mouseenter', function () {
					cancelHoverClose();
					// don't replace an open compose box on hover (would lose typed text)
					if (activePop && !activePop.classList.contains('iwpfb-read')) { return; }
					openRead(d, b);
				});
				b.addEventListener('mouseleave', scheduleHoverClose);
				b.addEventListener('click', function (e) { e.stopPropagation(); openRead(d, b); }); // touch / keyboard
				pinLayer.appendChild(b);
				d._el = b;
			});
		}
		updateCount();
	}
	function repositionPins() {
		pins.forEach(function (d) {
			if (d._el) { var c = coordsFor(d); d._el.style.left = c.x + 'px'; d._el.style.top = c.y + 'px'; }
		});
	}

	/* ---------------------------------------------------------- placing */

	// Sticky "feedback mode": it stays ON so you can drop several pins in a row.
	// While a compose box is open we DISARM the page-click capture (so you can use
	// the box) and RE-ARM it when the box closes. Full exit is only via Done / Esc /
	// the launcher — never automatically after one note.
	function armPlacing() {
		document.body.classList.add('iwpfb-placing-mode');
		document.removeEventListener('click', onPlaceClick, true); // avoid duplicates
		document.addEventListener('click', onPlaceClick, true);
	}
	function disarmPlacing() {
		document.body.classList.remove('iwpfb-placing-mode');
		document.removeEventListener('click', onPlaceClick, true);
	}
	function startPlacing() {
		closeMenu();
		closePop();
		mode = 'placing';
		root.classList.add('iwpfb-placing');
		launchBtn.setAttribute('aria-label', T.done || 'Done');
		launchBtn.setAttribute('title', T.done || 'Done');
		if (!banner) {
			banner = el('div', 'iwpfb-banner',
				'<span>' + esc(T.hint || '') + '</span><button type="button" class="iwpfb-cancel">' + esc(T.done || 'Done') + '</button>');
			document.body.appendChild(banner);
			banner.querySelector('.iwpfb-cancel').addEventListener('click', stopPlacing);
		}
		armPlacing();
	}
	function stopPlacing() {
		mode = 'idle';
		root.classList.remove('iwpfb-placing');
		launchBtn.setAttribute('aria-label', T.launch || 'Feedback');
		launchBtn.setAttribute('title', T.launch || 'Feedback');
		if (banner) { banner.remove(); banner = null; }
		disarmPlacing();
		closePop();
	}
	// Close a compose box but STAY in feedback mode (re-arm for the next note).
	function reArmPlacing() {
		if (mode === 'placing') { armPlacing(); }
	}
	function closeCompose() {
		closePop();
		reArmPlacing();
	}
	function onPlaceClick(e) {
		var t = e.target;
		if (t && t.closest && t.closest('#iwpfb-root,#iwpfb-pins,.iwpfb-banner,.iwpfb-pop,.iwpfb-toast')) {
			return; // never let our own UI count as the target
		}
		e.preventDefault();
		e.stopPropagation();
		if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

		var node = document.elementFromPoint(e.clientX, e.clientY);
		if (node && node.closest && node.closest('#iwpfb-root,#iwpfb-pins,.iwpfb-banner,.iwpfb-pop')) { node = null; }
		var rect = node ? node.getBoundingClientRect() : null;
		var cap = {
			selector: node ? cssPath(node) : '',
			element: node ? elementHint(node) : '',
			rel_x: rect && rect.width ? clamp01((e.clientX - rect.left) / rect.width) : 0,
			rel_y: rect && rect.height ? clamp01((e.clientY - rect.top) / rect.height) : 0,
			page_x: Math.round(e.clientX + window.scrollX),
			page_y: Math.round(e.clientY + window.scrollY),
			doc_w: Math.round(document.documentElement.scrollWidth),
			doc_h: Math.round(document.documentElement.scrollHeight),
			viewport: window.innerWidth + 'x' + window.innerHeight
		};
		disarmPlacing(); // suspend (stay in feedback mode); re-armed when the box closes
		openCompose(cap, e.clientX, e.clientY);
	}

	/* -------------------------------------------------------- popovers */

	function placePopover(pop, clientX, clientY) {
		pop.style.position = 'fixed';
		pop.style.visibility = 'hidden';
		document.body.appendChild(pop);

		var rect = pop.getBoundingClientRect();
		var w = rect.width || pop.offsetWidth || 304;
		var h = rect.height || pop.offsetHeight || 360;
		var vw = window.innerWidth, vh = window.innerHeight, m = 12;

		// Horizontal: prefer to the right of the point, flip left if it would overflow,
		// then HARD-clamp inside the viewport so it can never render off-screen.
		var left = clientX + 16;
		if (left + w + m > vw) { left = clientX - w - 16; }
		left = Math.max(m, Math.min(left, vw - w - m));

		// Vertical: prefer below, flip above, clamp; pin to top if taller than viewport
		// (the body scrolls internally — see .iwpfb-pop-body in the CSS).
		var top = clientY + 14;
		if (top + h + m > vh) { top = clientY - h - 14; }
		top = Math.max(m, Math.min(top, vh - h - m));
		if (h >= vh - 2 * m) { top = m; }

		pop.style.left = left + 'px';
		pop.style.top = top + 'px';
		pop.style.visibility = 'visible';
	}

	function closePop() {
		if (activePop) { activePop.remove(); activePop = null; }
		if (activeTemp) { activeTemp.remove(); activeTemp = null; }
	}

	// Hover-to-show for pins: open a note's popover on pin hover; close it shortly after
	// the pointer leaves BOTH the pin and the popover (the delay lets you move onto the
	// popover to reach links). Only auto-closes read popovers — never a compose box.
	var hoverTimer = null;
	function cancelHoverClose() { if (hoverTimer) { clearTimeout(hoverTimer); hoverTimer = null; } }
	function scheduleHoverClose() {
		cancelHoverClose();
		hoverTimer = setTimeout(function () {
			if (activePop && activePop.classList.contains('iwpfb-read')) {
				if (activePop.contains(document.activeElement)) { return; } // typing a reply — keep open
				closePop();
			}
		}, 260);
	}

	function openCompose(cap, clientX, clientY) {
		closePop();

		// temp marker where they clicked
		activeTemp = el('button', 'iwpfb-pin iwpfb-temp', '<i><span>+</span></i>');
		activeTemp.type = 'button';
		var c = coordsFor(cap);
		activeTemp.style.left = c.x + 'px';
		activeTemp.style.top = c.y + 'px';
		pinLayer.appendChild(activeTemp);

		var opts = '';
		Object.keys(C.types || {}).forEach(function (k) {
			opts += '<option value="' + esc(k) + '">' + esc(C.types[k]) + '</option>';
		});
		var savedName = '';
		try { savedName = localStorage.getItem(NAME_KEY) || ''; } catch (e) {}
		var name = savedName || C.user || '';
		var ctx = esc(PATH) + (cap.element ? ' · <code>' + esc(cap.element) + '</code>' : '');

		// Once we know who you are, don't ask again — show "Posting as X · change".
		var nameRow = name
			? '<div class="iwpfb-row iwpfb-asrow">' +
					'<span class="iwpfb-asline">' + esc(T.postingas || 'Posting as') + ' <strong>' + esc(name) + '</strong> ' +
					'<button type="button" class="iwpfb-change">' + esc(T.change || 'change') + '</button></span>' +
					'<input type="text" class="iwpfb-name" value="' + esc(name) + '" autocomplete="name" style="display:none">' +
				'</div>'
			: '<div class="iwpfb-row"><label>' + esc(T.name || 'Your name') + '</label>' +
					'<input type="text" class="iwpfb-name" value="" autocomplete="name"></div>';

		var pop = el('div', 'iwpfb-pop',
			'<div class="iwpfb-pop-head"><strong>' + esc(T.place || 'Leave feedback') + '</strong>' +
				'<button type="button" class="iwpfb-x" aria-label="Close">×</button></div>' +
			'<div class="iwpfb-pop-body">' +
				'<p class="iwpfb-context">' + ctx + '</p>' +
				nameRow +
				'<div class="iwpfb-row"><label>Type</label><select class="iwpfb-type">' + opts + '</select></div>' +
				'<div class="iwpfb-row"><label>Note</label>' +
					'<textarea class="iwpfb-msg" placeholder="' + esc(T.message || '') + '"></textarea></div>' +
				'<div class="iwpfb-actions">' +
					'<button type="button" class="iwpfb-send">' + esc(T.send || 'Send') + '</button>' +
					'<button type="button" class="iwpfb-ghost">' + esc(T.cancel || 'Cancel') + '</button></div>' +
				'<p class="iwpfb-err"></p>' +
			'</div>');

		placePopover(pop, clientX, clientY);
		activePop = pop;

		var nameI = pop.querySelector('.iwpfb-name');
		var typeI = pop.querySelector('.iwpfb-type');
		var msgI = pop.querySelector('.iwpfb-msg');
		var sendB = pop.querySelector('.iwpfb-send');
		var errEl = pop.querySelector('.iwpfb-err');

		pop.querySelector('.iwpfb-x').addEventListener('click', closeCompose);
		pop.querySelector('.iwpfb-ghost').addEventListener('click', closeCompose);
		var changeBtn = pop.querySelector('.iwpfb-change');
		if (changeBtn) {
			changeBtn.addEventListener('click', function () {
				var asline = pop.querySelector('.iwpfb-asline');
				if (asline) { asline.style.display = 'none'; }
				nameI.style.display = 'block';
				nameI.focus();
				nameI.select();
			});
		}
		(name ? msgI : nameI).focus();

		sendB.addEventListener('click', function () {
			var nm = (nameI.value || '').trim();
			var ms = (msgI.value || '').trim();
			errEl.textContent = '';
			if (!nm || !ms) { errEl.textContent = T.needname || 'Add your name and a note first.'; return; }

			sendB.disabled = true;
			sendB.textContent = T.sending || 'Sending…';

			var payload = {
				name: nm, message: ms, type: typeI.value, uid: MYUID,
				url: PAGE_URL, path: PATH, page_title: document.title,
				selector: cap.selector, element: cap.element,
				rel_x: cap.rel_x, rel_y: cap.rel_y,
				page_x: cap.page_x, page_y: cap.page_y,
				doc_w: cap.doc_w, doc_h: cap.doc_h, viewport: cap.viewport
			};

			fetch(C.rest + '/submit', {
				method: 'POST',
				credentials: 'same-origin',
				headers: authHeaders({ 'Content-Type': 'application/json' }),
				body: JSON.stringify(payload)
			}).then(function (r) {
				return r.json().then(function (j) { return { ok: r.ok, j: j }; });
			}).then(function (res) {
				if (!res.ok || !res.j || !res.j.ok) {
					throw new Error((res.j && res.j.message) || T.error);
				}
				try { localStorage.setItem(NAME_KEY, nm); } catch (e) {}
				closePop();
				if (res.j.item) { pins.push(res.j.item); }
				renderPins();
				toast(T.thanks || 'Thanks! Feedback sent.');
				reArmPlacing(); // stay in feedback mode, ready for the next note
			}).catch(function (err) {
				sendB.disabled = false;
				sendB.textContent = T.send || 'Send';
				errEl.textContent = (err && err.message) || T.error || 'Could not send. Try again.';
			});
		});

		msgI.addEventListener('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { sendB.click(); }
		});
	}

	// One reply in the thread. Admin/team replies get a badge.
	function iwpfbReplyHtml(rp) {
		return '<div class="iwpfb-rep">' +
			'<div class="iwpfb-rep-meta">' +
				'<span class="iwpfb-rep-who">' + esc(rp.name) + '</span>' +
				(rp.admin ? '<span class="iwpfb-tag iwpfb-tag-team">' + esc(T.team || 'Team') + '</span>' : '') +
				'<span class="iwpfb-rep-when">' + esc(rp.ago || rp.date || '') + '</span>' +
			'</div>' +
			'<div class="iwpfb-rep-text">' + esc(rp.text) + '</div>' +
		'</div>';
	}

	function openRead(d, pinEl) {
		closePop();
		var r = pinEl.getBoundingClientRect();
		var typeLabel = (C.types && C.types[d.type]) || d.type;
		var stLabel = (C.statuses && C.statuses[d.status]) || d.status;
		var adminLink = (C.isAdmin && C.adminPost)
			? '<a href="' + esc(C.adminPost + '?post=' + d.id + '&action=edit') + '" target="_blank" rel="noopener">' + esc(T.openadmin || 'Open in admin') + ' ↗</a>'
			: '';

		var youTag = d.mine ? '<span class="iwpfb-tag iwpfb-tag-you">' + esc(T.you || 'You') + '</span>' : '';

		// You can delete your OWN note; admins can delete any.
		var canDelete = d.mine || C.isAdmin;
		var delBtn = canDelete ? '<button type="button" class="iwpfb-del">' + esc(T['delete'] || 'Delete') + '</button>' : '<span></span>';
		var foot = (canDelete || adminLink) ? '<div class="iwpfb-foot">' + delBtn + (adminLink || '<span></span>') + '</div>' : '';

		var repliesHtml = '<div class="iwpfb-replies">' + (d.replies || []).map(iwpfbReplyHtml).join('') + '</div>';
		var rkName = '';
		try { rkName = localStorage.getItem(NAME_KEY) || ''; } catch (e) {}
		if (!rkName) { rkName = C.user || ''; }
		var replyBox = '<div class="iwpfb-reply-box">' +
			(rkName ? '' : '<input type="text" class="iwpfb-rep-name" placeholder="' + esc(T.name || 'Your name') + '" autocomplete="name">') +
			'<div class="iwpfb-reply-row">' +
				'<textarea class="iwpfb-rep-input" rows="1" placeholder="' + esc(T.replyph || 'Write a reply…') + '"></textarea>' +
				'<button type="button" class="iwpfb-rep-send">' + esc(T.reply || 'Reply') + '</button>' +
			'</div>' +
		'</div>';

		var pop = el('div', 'iwpfb-pop iwpfb-read',
			'<div class="iwpfb-pop-head">' +
				'<strong>' + esc(d.name) + '</strong>' +
				'<button type="button" class="iwpfb-x" aria-label="Close">×</button></div>' +
			'<div class="iwpfb-pop-body">' +
				'<div class="iwpfb-meta-line">' +
					youTag +
					'<span class="iwpfb-tag iwpfb-tag-type">' + esc(typeLabel) + '</span>' +
					'<span class="iwpfb-tag iwpfb-tag-st-' + esc(d.status) + '">' + esc(stLabel) + '</span>' +
					'<span>' + esc(d.ago || d.date || '') + '</span>' +
				'</div>' +
				'<div class="iwpfb-text">' + esc(d.message) + '</div>' +
				(d.element ? '<p class="iwpfb-context" style="margin-top:10px;margin-bottom:0">On <code>' + esc(d.element) + '</code></p>' : '') +
				repliesHtml +
				replyBox +
				foot +
			'</div>');

		placePopover(pop, r.left + r.width / 2, r.top + r.height);
		activePop = pop;
		cancelHoverClose();
		pop.querySelector('.iwpfb-x').addEventListener('click', closePop);
		// keep open while the pointer is over the popover; close when it leaves
		pop.addEventListener('mouseenter', cancelHoverClose);
		pop.addEventListener('mouseleave', scheduleHoverClose);
		// resume hover-close once focus leaves the popover (so a half-typed reply isn't lost)
		pop.addEventListener('focusout', function () {
			setTimeout(function () {
				if (activePop === pop && !pop.contains(document.activeElement)) { scheduleHoverClose(); }
			}, 0);
		});

		// --- reply thread ---
		var repList = pop.querySelector('.iwpfb-replies');
		var repInput = pop.querySelector('.iwpfb-rep-input');
		var repName = pop.querySelector('.iwpfb-rep-name');
		var repSend = pop.querySelector('.iwpfb-rep-send');
		if (repSend) {
			var sendReply = function () {
				var text = (repInput.value || '').trim();
				if (!text) { repInput.focus(); return; }
				var nm = repName ? (repName.value || '').trim() : rkName;
				if (repName && !nm) { repName.focus(); return; }
				repSend.disabled = true;
				repSend.textContent = T.replying || 'Replying…';
				fetch(C.rest + '/reply', {
					method: 'POST',
					credentials: 'same-origin',
					headers: authHeaders({ 'Content-Type': 'application/json' }),
					body: JSON.stringify({ id: d.id, text: text, name: nm, uid: MYUID })
				}).then(function (r) {
					return r.json().then(function (j) { return { ok: r.ok, j: j }; });
				}).then(function (res) {
					if (!res.ok || !res.j || !res.j.ok) { throw new Error((res.j && res.j.message) || T.error); }
					if (nm) { try { localStorage.setItem(NAME_KEY, nm); } catch (e) {} }
					d.replies = d.replies || [];
					d.replies.push(res.j.reply);
					repList.insertAdjacentHTML('beforeend', iwpfbReplyHtml(res.j.reply));
					repInput.value = '';
					if (repName) { repName.remove(); repName = null; }
					repSend.disabled = false;
					repSend.textContent = T.reply || 'Reply';
					repInput.focus();
				}).catch(function (err) {
					repSend.disabled = false;
					repSend.textContent = T.reply || 'Reply';
					toast((err && err.message) || T.error);
				});
			};
			repSend.addEventListener('click', sendReply);
			repInput.addEventListener('keydown', function (e) {
				if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); sendReply(); }
			});
		}

		var delEl = pop.querySelector('.iwpfb-del');
		if (delEl) {
			var confirming = false, ct = null;
			delEl.addEventListener('click', function (e) {
				e.stopPropagation();
				if (!confirming) { // first click: arm an inline confirm that auto-reverts
					confirming = true;
					delEl.textContent = T.confirmdel || 'Confirm delete';
					delEl.classList.add('iwpfb-confirm');
					ct = setTimeout(function () {
						confirming = false;
						delEl.textContent = T['delete'] || 'Delete';
						delEl.classList.remove('iwpfb-confirm');
					}, 2800);
					return;
				}
				clearTimeout(ct);
				delEl.disabled = true;
				delEl.textContent = T.deleting || 'Deleting…';
				fetch(C.rest + '/delete', {
					method: 'POST',
					credentials: 'same-origin',
					headers: authHeaders({ 'Content-Type': 'application/json' }),
					body: JSON.stringify({ id: d.id, uid: MYUID })
				}).then(function (r) {
					return r.json().then(function (j) { return { ok: r.ok, j: j }; });
				}).then(function (res) {
					if (!res.ok || !res.j || !res.j.ok) {
						throw new Error((res.j && res.j.message) || T.error);
					}
					pins = pins.filter(function (p) { return p.id !== d.id; });
					closePop();
					renderPins();
					toast(T.deleted || 'Feedback deleted.');
				}).catch(function (err) {
					confirming = false;
					delEl.disabled = false;
					delEl.classList.remove('iwpfb-confirm');
					delEl.textContent = T['delete'] || 'Delete';
					toast((err && err.message) || T.error || 'Could not delete.');
				});
			});
		}
	}

	/* ----------------------------------------------------------- toast */

	var toastEl = null, toastTimer = null;
	function toast(msg) {
		if (!toastEl) { toastEl = el('div', 'iwpfb-toast'); document.body.appendChild(toastEl); }
		toastEl.textContent = msg;
		requestAnimationFrame(function () { toastEl.classList.add('iwpfb-show'); });
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.classList.remove('iwpfb-show'); }, 2600);
	}

	/* ------------------------------------------------------------- menu */

	function openMenu() { menu.classList.add('iwpfb-open'); }
	function closeMenu() { menu.classList.remove('iwpfb-open'); }

	launchBtn.addEventListener('click', function (e) {
		e.stopPropagation();
		if (mode === 'placing') { stopPlacing(); return; }
		if (menu.classList.contains('iwpfb-open')) { closeMenu(); } else { openMenu(); }
	});

	menu.addEventListener('click', function (e) {
		var b = e.target.closest('[data-act]');
		if (!b) { return; }
		var act = b.getAttribute('data-act');
		if (act === 'place') { startPlacing(); }
		else if (act === 'togglepins') { pinsVisible = !pinsVisible; renderPins(); }
		else if (act === 'togglemine') { mineOnly = !mineOnly; if (!pinsVisible) { pinsVisible = true; } renderPins(); }
		else if (act === 'hideme') {
			var u = new URL(location.href); u.searchParams.set('feedback', 'off'); location.href = u.toString();
		}
	});

	document.addEventListener('click', function (e) {
		if (!root.contains(e.target)) { closeMenu(); }
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		// Esc closes an open box first (and stays in feedback mode); a second Esc
		// with no box open exits feedback mode entirely.
		if (activePop) { closeCompose(); }
		else if (mode === 'placing') { stopPlacing(); }
		else if (menu.classList.contains('iwpfb-open')) { closeMenu(); }
	});

	/* --------------------------------------------------------- lifecycle */

	var rzTimer = null;
	window.addEventListener('resize', function () {
		clearTimeout(rzTimer);
		rzTimer = setTimeout(repositionPins, 120);
	});
	window.addEventListener('load', function () {
		repositionPins();
		setTimeout(repositionPins, 600);
		setTimeout(repositionPins, 1600);
	});

	function loadPins() {
		fetch(C.rest + '/list?path=' + encodeURIComponent(PATH) + '&uid=' + encodeURIComponent(MYUID), {
			credentials: 'same-origin',
			headers: authHeaders()
		}).then(function (r) { return r.ok ? r.json() : { items: [] }; })
		  .then(function (d) { pins = (d && d.items) || []; renderPins(); })
		  .catch(function () {});
	}

	updateCount();
	loadPins();
})();
