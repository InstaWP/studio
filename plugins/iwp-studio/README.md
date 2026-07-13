# InstaStudio (plugin) — the source-rendered engine

Serve plain HTML files from a source directory as real WordPress pages: no page
builder, no block editor, no build step, no DB-stored content. **Works with any
theme** (it takes over rendering only for mapped pages via `template_include`).

## What it does
- **Page map** — `INSTAWP_HB_DIR` (default `<wp-root>/site/`) is scanned: every `.html`
  becomes a page (slug = filename; `index.html` → the front page). Or provide an explicit
  `<source>/pages.json`. Filter: `instawp_homebuild_pages`. Override the dir with
  `define( 'INSTAWP_HB_DIR', … )` + `INSTAWP_HB_URL` in `wp-config.php`.
- **Render** — reads the file live and emits it as the page: keeps the `<head>` `<style>`,
  the body between `#site-nav` / `#site-footer`, and the page's own inline scripts;
  rewrites `assets/…` refs to the source URL and internal `.html` links to WP routes.
  Carries the source `<title>`, `<meta description>`, and `<body class>`.
- **Edit in Place** — logged-in admins click text/images on the page to edit; saves write
  back to the source `.html`. Localhost by default; on a hosted sandbox opt in with
  `define( 'INSTAWP_HB_EDITOR', true );` (never on production).
- **`wp instastudio pages`** — create a published WP page for every source file that
  lacks one (nested slugs get ancestor pages) and set the front page. `--dry-run` supported.

## Install
Drop this folder in `wp-content/plugins/`, activate it, put your HTML in `<wp-root>/site/`
(or point `INSTAWP_HB_DIR` at it), then `wp instastudio pages`. Pair with the minimal
`iwp-studio` companion theme or your own.

## Intentionally NOT included (kept generic)
This is the clean, theme-agnostic engine. The following were specific to the site it was
extracted from and were left out — add your own via the filters/hooks if you need them:
- **SEO** beyond a basic `<title>` + meta-description + canonical (no Rank Math/Yoast coupling).
- **og:image** generation. **Brand fonts** (bring your own in the source CSS). **UTM link
  decoration.** A **server-side blog** (this engine renders static pages; use normal WP
  templates/theme for a blog).
