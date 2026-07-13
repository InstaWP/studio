---
name: build-page
description: >-
  Author or edit a page on an HTML-first WordPress (InstaStudio) site. Creates/edits
  an .html file in the site source directory following the render conventions, wires
  it into the design system, and registers the matching WordPress page so it goes
  live. Use whenever the user asks to add, build, draft, or restyle a page ("add a
  pricing page", "build a landing page for X", "make a features section").
---

# Build a page

The **Build** leg of Build → Edit → Review → Resolve → Ship. A page is one HTML file
in the site source dir (`site/` by default); the theme renders it live, no build step.

## Before you write
- Read the project's `CLAUDE.md` (rules) and `site/DESIGN.md` (tokens + classes).
- Look at an existing page (`site/index.html`) and reuse its structure and classes,
  don't invent a new visual language.

## Write the file  →  `site/<slug>.html`
Follow the renderer contract exactly (or the page renders blank/unstyled):

```html
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Page title · Site</title>
<meta name="description" content="…">
<link rel="stylesheet" href="assets/style.css">
<style id="vh">/* page-specific CSS only */</style>
</head><body>
<div id="site-nav"></div>        <!-- marker: body starts here -->
  … your sections (.wrap / .section / .hero / .grid / .card / .btn) …
<div id="site-footer"></div>     <!-- marker: body ends here -->
<script>/* page-only JS, optional (carried by the renderer) */</script>
<script src="assets/chrome.js"></script>
</body></html>
```

Rules that matter:
- Local assets are `assets/…`; inter-page links are `<slug>.html` (theme rewrites both).
- Shared nav/footer changes go in `assets/chrome.js`, NOT the page.
- Use design-system tokens/classes; add a token before a one-off color.
- **No fabricated proof** — real content only; flag unknowns with a `VERIFY` comment.

## Register the WordPress page
The slug is derived from the filename (`about.html` → `about`; `index.html` → the
front page). One command creates a published page for every source file that lacks
one (and sets the front page):
```bash
wp instastudio pages                      # local
instawp wp <site> -- instastudio pages    # cloud sandbox   (--dry-run to preview)
```

## Verify
- Open the page locally / on the sandbox and eyeball it: nav + footer present, styles
  applied, links work, no leftover `{{tokens}}` or `VERIFY` you meant to resolve.
- If it renders blank or unstyled, re-check the `#site-nav` / `#site-footer` markers,
  the `<style id="vh">`, and the `assets/style.css` link.

## Scaling
Building many pages? Author them in parallel (one file each, no shared-file conflicts),
then register the WP pages. Keep every page in the same design system.
