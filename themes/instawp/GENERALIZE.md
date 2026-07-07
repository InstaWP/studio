# Generalize ledger — themes/instawp

This theme is the **source-rendered engine** for InstaStudio, but it is currently
**bespoke to instawp.com**. It works and is dogfoodable as-is; this file is the
ledger of what must be generalized to make it a reusable starter (the doc's
"bespoke vs generalizable" ledger).

## Keep — the engine (generic, this is InstaStudio's core)
- `template-homebuild.php` — the render template.
- `functions.php` render pipeline: `instawp_render_homebuild()`, `instawp_homebuild_html()`,
  `instawp_homebuild_head()`, `instawp_homebuild_linkmap()`, `instawp_homebuild_slug()`,
  `instawp_is_homebuild_page()`, `instawp_hb_asset()`, `instawp_hb_chrome_linkfix()`.
- **Edit in Place**: `inc/homebuild-editor.php` + `js/hb-editor.js` + `css/hb-editor.css`.
- Shell: `index.php`, `style.css`, `header.php`, `footer.php`, `template-parts/`,
  `assets/nav.js`, `assets/reveal.js`, `assets/static-shared.css`.

## Generalize / make config-driven
- **`instawp_homebuild_pages()`** — hardcoded slug→file map of ~30 instawp.com pages.
  Replace with directory-scan or a config file (`pages.json`) so the map isn't source-baked.
- **SEO (RankMath-coupled)** — `instawp_homebuild_seo()`, `instawp_homebuild_meta_tag()`,
  and the `rank_math/frontend/{title,description}` filters. Make the SEO adapter pluggable
  (RankMath / Yoast / none).
- **og-image** — `instawp_homebuild_og_image_url()` + og meta injection assume instawp.com's
  branded cards + a generator. Make optional.
- **Brand** — `instawp_hb_fonts()` (Hanken Grotesk + IBM Plex Mono), colors, and
  `instawp_hb_admin_bar_css()` offsets are instawp.com brand. Move to a theme config / tokens.
- **`js/utm-forward.js`** — decorates outgoing `*.instawp.io` links; instawp.com-specific.
- **`[year]` token** (`instawp_current_year`) — harmless, keep or move to a helper.

## Optional module — the SSR blog (instawp.com-specific)
Everything blog is a separate concern from the render engine and can be extracted into an
optional module:
- Templates: `home.php`, `single.php`, `archive.php`, `search.php`, `404.php`.
- Functions: `instawp_is_blog_context()`, `instawp_blog_*` (cats, icons, initials, av_class,
  read_time, primary_cat, cat_meta, card, card_template, fill_card, card_img, toc, related).
- Assets referenced by those templates (blog card partial + blog CSS/JS live in the site's
  `variations/home-build/`, not the theme).

## Known gate to relax when hosting (doc §9 hard part #2)
- **Edit in Place is hard-gated to localhost** (blocks `*.instawp.site/.com/.io`). For a hosted
  InstaStudio sandbox, relax to "this sandbox host only" (still block prod + the mirror).
