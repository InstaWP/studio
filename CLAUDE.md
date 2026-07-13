# Project instructions (InstaStudio site)

This is an **HTML-first WordPress site**. The pages are plain HTML files in `site/`;
a lightweight theme renders them live as real WordPress pages. **No page builder, no
block editor, no build step, no DB-stored content.** You (the AI agent) author and
edit the HTML; WordPress serves, routes, and lets a human edit it.

> New here? Read `docs/GETTING-STARTED.md` once. The design system is `site/DESIGN.md`.

## The model
- **Source of truth is `site/`.** Edit the `.html` there and refresh, no build, no sync.
- Each `.html` in `site/` is a page. The theme's page map (`instawp_homebuild_pages()`)
  auto-derives the slug from the filename (`index.html` → home, `about.html` → `about`).
- Shared nav/footer live once in `site/assets/chrome.js`. Styles/tokens in
  `site/assets/style.css` (see `site/DESIGN.md`).

## Adding or editing a page  (skill: `build-page`)
1. Create/edit `site/<name>.html` following the **page conventions** below.
2. Create a WordPress page whose slug matches (`wp post create --post_type=page
   --post_status=publish --post_name=<name>`), or use your MCP/CLI equivalent. The
   home page maps to the slug set as the front page.
3. Refresh the WP page — no build.

### Page conventions (the renderer contract, non-negotiable)
- `<head>`: a `<title>`, `<meta name="description">`, `<link rel="stylesheet"
  href="assets/style.css">`, and any page-specific CSS in an inline `<style id="vh">`.
- Body starts with `<div id="site-nav"></div>` and ends with `<div id="site-footer"></div>`
  — the renderer keeps only what's between them (the theme injects the real nav/footer).
- Reference local files as `assets/…`; link between pages as `name.html` (the theme
  rewrites both to live URLs).
- Put shared chrome in `assets/chrome.js`; keep page-only scripts inline (the renderer
  carries inline scripts + your `<body class>` + `<title>`).

## Workflow (Build → Edit → Review → Resolve → Ship)
- **Build** — author HTML in `site/` (skill: `build-page`).
- **Edit in Place** — logged-in admins click text/images on the rendered page to edit;
  it writes back to the `site/` source. Local by default; on a sandbox set
  `define('INSTAWP_HB_EDITOR', true)` in `wp-config.php` (NEVER on production).
- **Review** — the `iwp-feedback` plugin: reviewers drop pins + notes, no login.
- **Resolve** — work a feedback export back into fixes (skill: `resolve-feedback`).
- **Ship** — publish to InstaWP (`scripts/publish.sh`), only when asked.

## Hard rules
- **No fabricated proof.** Never invent logos, testimonials, customers, numbers, or
  quotes. Flag anything unverified with an inline `VERIFY` comment.
- **Edit the source (`site/`), not a build artifact.** There is no build.
- **Stay lightweight + in the design system** (`site/DESIGN.md`): plain CSS, vanilla
  JS, reuse the existing classes/tokens. No page builder, no Tailwind compiler, no framework.
- **Never deploy or sync to PRODUCTION on your own.** Publish only to the staging target,
  and only when explicitly asked. Snapshot before risky changes.

## Stack / how you reach the site
- Theme: `themes/instawp/` (render engine + Edit in Place). Plugin: `plugins/iwp-feedback/`.
- Skills: `skills/build-page/`, `skills/resolve-feedback/`.
- Agent ↔ WordPress bridge: **InstaMCP** (site-level WP tools over MCP) and/or the
  **`instawp` CLI** (`instawp wp <site> -- <wp-cli>`, `sync`, `db`) — or plain `wp` on
  the box. See `docs/GETTING-STARTED.md`.
