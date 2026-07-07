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
| `plugins/iwp-feedback/` | **InstaWP Feedback** — a lightweight front-end feedback plugin. A floating widget lets reviewers drop a pin on any element and leave a note; threaded replies; triage in **wp-admin → Feedback**. Built for an agent loop: **export → resolve → re-import by `id`** (JSON, idempotent). Full `wp iwpfb` CLI. Team-gated (logged-in only) by default. |

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
