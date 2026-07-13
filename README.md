# InstaStudio

**HTML-first WordPress.** Your pages are plain HTML files; a plugin renders them
live as real WordPress pages, no page builder, no block editor, no build step, no
DB-stored content. Author with an AI agent, edit in place, collect client feedback,
resolve it, and ship. The category line: **source-rendered WordPress** — your files
*are* the pages; WordPress serves, routes, and edits them.

> ⚠️ Early / internal, being dogfooded on instawp.com. Expect churn. That said, the
> full clone → deploy → AI-build → render loop is validated end-to-end on throwaway
> InstaWP sandboxes.

## The flow

InstaStudio is a **workflow layer on top of [InstaWP](https://instawp.com)**, not a
separate product. It rides InstaWP's connection layer (InstaMCP + the `instawp` CLI)
rather than reinventing transport.

1. **Build** — an AI agent writes plain HTML.
2. **Edit** — "Edit in Place" edits the source `.html` visually.
3. **Review** — reviewers pin feedback on the live site.
4. **Resolve** — an agent works the feedback export and fixes the same source files.
5. **Ship** — push to InstaWP (sandbox → production).

## How a page works

A page is one HTML file in `site/`. The `iwp-studio` plugin reads it live and serves
it as a real WordPress page:

```html
<!-- site/pricing.html  ->  /pricing/ -->
<head>
  <title>Pricing</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div id="site-nav"></div>          <!-- markers: your body is what's between them -->
    <section class="hero"> … your content … </section>
  <div id="site-footer"></div>
  <script src="assets/chrome.js"></script>   <!-- shared nav/footer, single source -->
</body>
```

`wp instastudio pages` registers the WordPress pages, and `/pricing/` is live. No
build, no import, no drift, the file is the source of truth. The engine is a plugin,
so it works with **any** theme.

## Requirements

- A WordPress site to render into (an **InstaWP** sandbox is easiest, disposable and
  promotable to production; local WP works too).
- An AI coding agent (e.g. **Claude**) and a way to reach the site: **InstaMCP** or the
  **`instawp` CLI** (`npm i -g @instawp/cli`).

## Quick start

Clone to an AI-built page in ~10 minutes, full walk-through in
[`docs/GETTING-STARTED.md`](docs/GETTING-STARTED.md):

1. **Assemble** into a WordPress install: `bash scripts/bootstrap.sh /path/to/wordpress`
   (or copy `plugins/iwp-studio` + `plugins/iwp-feedback` into `wp-content/plugins/`,
   `themes/iwp-studio` into `wp-content/themes/`, and `site/` to the webroot). Activate
   the two plugins + the companion theme.
2. **Create the pages** — `wp instastudio pages` publishes a WordPress page for every
   `.html` in `site/` (slug = filename) and sets the front page.
3. **Connect** your agent via InstaMCP or the `instawp` CLI and point it at
   [`CLAUDE.md`](CLAUDE.md).
4. **Build → Review → Resolve → Ship** — ask it to "add a page" (the `build-page` skill),
   collect feedback with the widget, resolve with the `resolve-feedback` skill, and
   ship with `scripts/publish.sh`.

## What's here

**The engine**

| Path | What |
|---|---|
| `plugins/iwp-studio/` | **The source-rendered engine (a plugin).** Renders source HTML live as pages, includes **Edit in Place** (click text/images to edit → writes back to the source) and `wp instastudio pages`. Works with any theme (takes over only mapped pages via `template_include`). Clean/generic, see its [README](plugins/iwp-studio/README.md) for what's intentionally left out. |
| `themes/iwp-studio/` | **Minimal companion theme.** Satisfies WordPress + renders any non-source request. Bring your own theme instead if you like. |

**Your site**

| Path | What |
|---|---|
| `site/` | **Starter source site** — HTML pages (`index.html`, `about.html`) + shared `assets/chrome.js` (nav/footer) + `assets/style.css` (design tokens) + `DESIGN.md`. The source of truth. Replace with your own. |
| `CLAUDE.md` / `AGENTS.md` | **Agent instructions** — the model, page conventions, hard rules, and workflow. Point your AI agent here first. |

**Review + agent playbooks**

| Path | What |
|---|---|
| `plugins/iwp-feedback/` | **InstaWP Feedback** — a floating widget to drop pin comments on any element; threaded replies; triage in **wp-admin → Feedback**. Built for an agent loop: **export → resolve → re-import by `id`** (idempotent JSON). Full `wp iwpfb` CLI. Team-gated (logged-in) by default. |
| `skills/build-page/` | **Build playbook** — how an agent authors a page in the design system. |
| `skills/resolve-feedback/` | **Resolve playbook** — read a feedback export, map each item to its source `.html`, apply safe in-design-system fixes, re-import. Installable as a Claude Code skill; canonical form is an InstaMCP site-skill (travels with the site, any MCP agent). |

**Setup + ops**

| Path | What |
|---|---|
| `docs/` | `GETTING-STARTED.md` (setup + workflow) · `BLUEPRINT.md` (packaging later). |
| `scripts/` | `bootstrap.sh` (assemble into a WP install) · `publish.sh` (ship to an InstaWP sandbox). |

### iwp-feedback CLI

```bash
wp iwpfb list [--unresolved | --status=<s>] [--type --page --format=table|json|csv|ids|count]
wp iwpfb get <id> [--format=json]
wp iwpfb create --message="…" [--type --page --name --status]
wp iwpfb update <id> [--status --resolution --message]
wp iwpfb reply  <id> --text="…" [--team]
wp iwpfb export [--unresolved] [--format=json|md] [--file=<p>]   # no --file -> STDOUT
wp iwpfb import <file> [--dry-run]                               # apply status + resolution by id
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
