# Design system — this site

The starter skin lives in `assets/style.css`. Change the tokens at the top and the
whole site follows. Keep it lightweight: plain CSS, vanilla JS, reuse these classes.

## Tokens (`:root` in `assets/style.css`)
| Token | Default | Use |
|---|---|---|
| `--brand` / `--brand-ink` | `#3b5bdb` / `#233ea8` | primary color + hover. **Swap these first.** |
| `--ink` / `--body` / `--muted` | headings / text / secondary |
| `--bg` / `--surface` / `--border` | page / alt bands / hairlines |
| `--font` / `--mono` | Hanken Grotesk / IBM Plex Mono (loaded by the theme) |
| `--radius` / `--wrap` / `--sp` | corner radius / max content width / section padding |

## Building blocks (classes in `style.css`)
- Layout: `.wrap` (centered max-width), `.section` / `.section.alt` (vertical bands), `.grid`.
- Text: `.eyebrow` (mono kicker), `.lead` (intro paragraph).
- Components: `.btn` / `.btn.ghost`, `.card`, `.hero`.
- Chrome: `.nav` / `.foot` (filled by `assets/chrome.js`).

## Page conventions (required by the renderer)
1. `<head>` has a `<title>`, a `<meta name="description">`, `<link rel="stylesheet" href="assets/style.css">`, and page-specific CSS in an inline `<style id="vh">`.
2. The body starts with `<div id="site-nav"></div>` and ends with `<div id="site-footer"></div>` (markers the renderer uses).
3. Local files are referenced as `assets/…`; links between pages use `name.html`.
4. Put shared nav/footer in `assets/chrome.js` (single source), page-only scripts inline.

## Rules
- No fabricated proof, flag unverified numbers/logos/quotes with a `VERIFY` comment.
- Stay in this design system; add tokens rather than one-off colors.
