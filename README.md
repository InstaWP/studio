# InstaStudio

**Source-rendered WordPress** — build, edit, review, and ship real WordPress
marketing sites without a page builder or the block editor. Your HTML files
*are* the pages; WordPress serves, routes, and edits them (no blocks, no
DB-stored content, no import step).

> ⚠️ Early / internal. This repo is being assembled while the workflow is
> dogfooded on instawp.com. Expect churn; nothing here is a stable API yet.

## The flow

InstaStudio is a **workflow layer on top of [InstaWP](https://instawp.com)**, not
a separate product:

1. **Build** — an AI writes plain HTML.
2. **Edit** — "Edit in Place" edits the source `.html` visually (local).
3. **Review** — clients pin feedback on the live site, no login required.
4. **Resolve** — an agent works the feedback export and applies fixes to the
   same source files.
5. **Ship** — push to InstaWP (sandbox → production).

The connection layer (InstaMCP + the InstaWP CLI) already exists; InstaStudio
rides it rather than reinventing transport.

## What's here

| Path | What |
|---|---|
| `themes/instawp/` | **The engine** — a lightweight classic theme that renders source HTML files live (`instawp_render_homebuild()` + `template-homebuild.php`; no blocks, no DB content, no import). Includes **Edit in Place** (`inc/homebuild-editor.php` + `js/hb-editor.js` + `css/hb-editor.css`) — a local-only visual editor that writes changes back to the source `.html`. ⚠️ Currently bespoke to instawp.com — see [`themes/instawp/GENERALIZE.md`](themes/instawp/GENERALIZE.md) for what to strip/config-drive for a reusable starter. |
| `plugins/iwp-feedback/` | **InstaWP Feedback** — a lightweight front-end feedback plugin. A floating widget lets reviewers drop a pin on any element and leave a note; threaded replies; triage in **wp-admin → Feedback**. Built for an agent loop: **export → resolve → re-import by `id`** (JSON, idempotent). Full `wp iwpfb` CLI. Team-gated (logged-in only) by default. |
| `skills/resolve-feedback/` | **The Resolve playbook** — closes the loop with `iwp-feedback`: read a feedback export, map each item to its source `.html`, apply safe in-design-system fixes, set status + resolution in place, re-import. Installable as a Claude Code skill; the canonical form is an InstaMCP site-skill (travels with the site, any MCP agent). Agent-agnostic; project specifics (paths, hard rules, publish target) are read from the project config. |

### iwp-feedback quick reference

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
