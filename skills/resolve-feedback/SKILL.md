---
name: resolve-feedback
description: >-
  Work a batch of front-end feedback exported by the iwp-feedback plugin (a
  feedback-*.json file, grouped by page). Reads each item, maps its page to the
  matching source .html file, applies safe in-design-system fixes honoring the
  project's hard rules, sets each item's status and a one-line resolution IN PLACE
  (keeping id unchanged), flags judgment calls for the owner, and optionally
  publishes. Use whenever the user shares or points to a feedback export JSON or
  says "check / work / triage / resolve the feedback".
---

# Resolve feedback batch

Turn a feedback export from the **iwp-feedback** plugin into applied fixes in the
site's HTML source, then write the outcome back into the same JSON so it can be
re-imported to close the loop. **Export → work → re-import** is the agent loop the
plugin was built for. This is the **Resolve** leg of InstaStudio's
Build → Edit → **Review** (pins) → **Resolve** (this) → Ship.

> **Project config.** The source directory, the page→file map, the hard rules, and
> the publish target are project-specific. This playbook references them
> generically — read them from the project's own theme + `CLAUDE.md`/config, not
> from here. Names below (`instawp_homebuild_pages()`, source dir) follow the
> source-rendered theme shipped in this repo.

## Inputs

A JSON file (often at `~/Downloads/feedback-*.json`) shaped:

```
{ "count": N, "pages": [ { "path": "/pricing", "items": [
  { "id": 21770, "status": "new", "resolution": "", "type": "copy",
    "from": "Neha", "comment": "<what to change>",
    "location": { "element": "p · \"quoted text…\"", "selector": "<rendered CSS>", "pin": "x% / y%" },
    "replies": [ … ] } ] } ] }
```

On re-import only `status` + `resolution` are applied, matched by `id` (idempotent,
unknown ids skipped, partial files safe). **Never change `id`.** `replies` is
read-only context.

## The loop

1. **Read** the JSON. Triage every item up front: safe-fix · judgment-call · junk.

2. **Map page → source file.** The `path` is the WordPress slug. Resolve it via the
   theme's page map (`instawp_homebuild_pages()` in the source-rendered theme — slug
   → `.html` under the project's source dir). **Read that map live; do not hardcode
   it** (it drifts).

3. **Locate the element by its QUOTED TEXT, not the CSS selector.** The `selector`
   and `pin` come from the *rendered* site, where the shared `chrome.js` injects the
   nav/footer/CTA and WordPress wraps content in `<section>` tags — so selectors do
   not match the source 1:1. Match on the text in the `element` field (a prefix of
   the real element's text). Nav/footer/CTA issues live in the shared chrome / CSS,
   not in the page file.

4. **Apply the fix.** Minimal, surgical, matching the surrounding markup/classes.
   Read the page's own `<style>`/CSS before changing layout. Follow the project's
   hard rules (below).

5. **Set status + a one-line resolution** (before → after, briefly). Statuses:
   `resolved` (done) · `in_progress` (triaged, needs an owner decision/asset) ·
   `wontfix` (junk / no action) · `new` (couldn't locate or act). Edit these two
   fields IN PLACE; leave `id`, `type`, `from`, `comment`, `location`, `date` alone.

6. **Validate** the JSON parses and the item count is unchanged:
   `python3 -c "import json,sys;d=json.load(open(sys.argv[1]));print(sum(len(p['items']) for p in d['pages']))" <file>`

7. **Report** per-page: what changed, plus items left `in_progress` and why. Tell the
   user to re-import the same file at **wp-admin → Feedback → Export/Import** (or
   `wp iwpfb import <file>`) to mark resolved items done. **Publishing the edited
   pages is a separate, opt-in step** — only when the user asks.

## Triage rules

- **Junk / owner test pins** (e.g. "sss", placeholder text) → `wontfix`. Act per-`id`
  only; never blanket-clear feedback.
- **Safe fixes** (clear copy edits, factual corrections the team stated, in-design-
  system CSS/alignment fixes, dead-link fixes to a known real URL) → apply → `resolved`.
- **Judgment calls → `in_progress`, do NOT guess or fabricate.** Anything needing a
  real asset (logos, photos), an unverifiable product/pricing fact, a large redesign,
  deleting a whole section, or a positioning decision. Write a crisp recommendation in
  `resolution` and surface it. For a missing real value, leave a `VERIFY` placeholder
  rather than inventing one.

## Hard rules

Load the project's hard rules from its `CLAUDE.md`/config. For InstaStudio sites these
typically include:

- **No fabricated proof** — never invent logos, testimonials, customers, numbers, or
  quotes; flag unverified ones with an inline `VERIFY` comment.
- **Edit the source of truth**, not a build artifact — the theme renders the `.html`
  directly, no build step.
- **Stay lightweight / in the design system** — plain CSS, vanilla JS, reuse existing
  classes; read the project's `DESIGN.md`.
- **Never publish to production.** Publish only to the project's opt-in staging target,
  and only when explicitly asked. (This repo's origin project also enforces "no
  em-dashes in copy" — replace contextually, never a blind swap.)

## Scaling a large batch

For a big batch across many pages, fan out one **editor agent per page** (no two agents
touch the same file, so no conflicts), each given that page's items + the hard rules and
returning structured `{id, status, resolution, applied, needs_owner}`; optionally follow
with a per-page verify pass that re-reads the file and confirms each fix landed (watch
for broken markup / fabricated values). Collect results and edit the JSON in the main
loop so you control the final file. For a handful of items, just do it inline.

## Portability

This is a portable playbook. Installed as a **Claude Code skill** (this `SKILL.md`) it
gives Claude the loop directly. The canonical, agent-agnostic form is an **InstaMCP
site-skill** (`skill_save`/`skill_get`) so it travels *with the site* and any MCP-capable
agent can run it with zero agent-side install. Keep the two in sync; the CC skill is the
thin Claude-native front door over the same CLI/MCP surface.
