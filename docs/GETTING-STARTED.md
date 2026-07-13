# Getting started

Go from clone to an AI-built, HTML-first WordPress page. ~10 minutes.

## What you need
- A WordPress site to render into. Easiest: an **InstaWP** sandbox
  (<https://instawp.com>, disposable, promotable to production later). Local WP also works.
- An AI coding agent (e.g. **Claude**) with a way to reach the site (below).
- The `instawp` CLI for cloud sites: `npm i -g @instawp/cli && instawp login`.

## 1. Assemble the pieces
The repo is a set of components (theme + plugin + starter `site/` + skills). Put them in a WP install:

**Local:** `bash scripts/bootstrap.sh /path/to/wordpress` (symlinks the theme + plugin, seeds `site/`).

**Manual / cloud:** copy `themes/instawp` → `wp-content/themes/`, `plugins/iwp-feedback`
→ `wp-content/plugins/`, and the `site/` folder to the webroot (or anywhere, then point
the theme at it — see step 3).

Then: activate the **instawp** theme and the **iwp-feedback** plugin.

## 2. Point WordPress at your pages
- The theme renders HTML from a **source directory**. Default: `<wp-root>/site/`.
  Override in `wp-config.php` if it lives elsewhere:
  ```php
  define('INSTAWP_HB_DIR', ABSPATH . 'site/');            // filesystem path
  define('INSTAWP_HB_URL', home_url('/site/'));           // matching URL
  ```
- Every `.html` in `site/` becomes a page; the slug is the filename
  (`about.html` → `about`, `index.html` → the front page).
- **Create the WordPress pages with one command** — it makes a published page for
  every source file that lacks one and sets `home` as the front page:
  ```bash
  wp instastudio pages                       # local
  instawp wp <site> -- instastudio pages     # cloud sandbox
  wp instastudio pages --dry-run             # preview first
  ```
  Re-run it any time you add source files.

## 3. Connect your agent to the site
So Claude (or any agent) can build/edit/register pages:
- **InstaMCP** — site-level WordPress tools over MCP (`create_content`, `update_content`,
  `plugin/theme_files`, …). Connect it and the agent can drive the site directly.
- **`instawp` CLI** — `instawp wp <site> -- <wp-cli>` runs WP-CLI on a cloud site;
  `instawp sync` / `instawp db` move files + data. Great for scripted steps.
- **Plain `wp`** — on local/SSH boxes.

Point the agent at `CLAUDE.md` (this repo's root) so it knows the rules and conventions.

## 4. Build a page
Ask your agent: *"add a pricing page"*. It uses the **build-page** skill: writes
`site/pricing.html` in the design system, then registers the WP page. Refresh, it's live.

## 5. Edit, review, resolve
- **Edit in Place** — as a logged-in admin, click text/images on the page to edit; saves
  back to `site/`. Local works out of the box; on a sandbox add
  `define('INSTAWP_HB_EDITOR', true);` to `wp-config.php` (never on production).
- **Review** — click the round feedback button (bottom-right) to drop pin comments.
- **Resolve** — export the feedback (wp-admin → Feedback → Export, or `wp iwpfb export`),
  hand it to your agent with the **resolve-feedback** skill, re-import to close the loop.

## 6. Ship
`SITE=<your-sandbox> bash scripts/publish.sh` pushes `site/` + theme + plugin. When the
sandbox is ready, promote it to production from the InstaWP dashboard.

## Notes
- The starter theme is currently derived from a real site, see `themes/instawp/GENERALIZE.md`
  for what's still bespoke (SEO adapter, brand tokens, the optional blog module).
- Hard rules live in `CLAUDE.md`; keep the agent honest (no fabricated proof; edit `site/`;
  never publish to prod unasked).
