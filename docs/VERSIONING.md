# Spec — site-local versioning ("History")

> Status: **spec / not built yet.** This is the agreed design for adding git-style
> history to InstaStudio. Chosen model: **site-local git** (history lives on the server
> with the site), because the editing model is "agent + human both edit directly on the
> hosted site." GitHub-central was considered and set aside (it assumes edits start in a
> repo and would leave the server perpetually ahead of GitHub). See the decision notes at
> the end.

## Feasibility (verified 2026-07-28 on a live InstaWP sandbox)
- `git` **2.43** present on the host; `shell_exec` / `proc_open` / `exec` **enabled**;
  `git init` + `add` + `commit` + `log` work in the docroot.
- A `.git` placed under the web-served `site/` dir returns **HTTP 404** (platform blocks
  dotfiles) — but the spec keeps the git dir **outside the webroot** anyway.
- ⇒ Site-local git is viable on InstaWP hosting. Hosts without `shell_exec` use the
  graceful-degrade path (§3a).

## 1. Model
A real git repo tracks the `site/` source dir **on the server**. Every workflow change —
agent or human — becomes a commit right there. GitHub is an optional one-way mirror.
Nobody leaves the hosted site to get history + rollback. This respects the project's
no-build, edit-on-the-server promise instead of adding a deploy step back in.

## 2. Where the git dir lives (security)
Work-tree is `INSTAWP_HB_DIR` (`site/`), but the **git dir lives OUTSIDE the webroot** so
`.git` is never fetchable:

```
GIT_DIR   = wp-content/uploads/.instastudio-git/   (writable, above the served path)
WORK_TREE = INSTAWP_HB_DIR   (site/)
```
All git calls: `git --git-dir=$GIT_DIR --work-tree=$WORK_TREE …`. Belt-and-suspenders: if
an in-tree `.git` ever appears, drop a `deny from all` `.htaccess` beside it.

## 3. Components — new file `plugins/iwp-studio/includes/history.php`

**a) Git backend** — thin PHP wrapper (`IWPS_Git::run($args)`) over `proc_open`:
- **Availability probe** cached in an option (`IWPS_Git::available()`): git binary? exec
  enabled? writable git dir?
- **Graceful degrade** — if unavailable, versioning silently disables and Edit in Place
  keeps its existing `.iwp-edit-backups/` file snapshots. `status` reports why.

**b) Bootstrap** (on activation / first change): if no repo, `git init` the external git
dir against `site/`, seed `.gitignore` (`.iwp-edit-backups/`, `*.log`), set a default
identity, initial commit `InstaStudio: initial import`.

**c) Commit triggers** — the heart of it:

| Trigger | Mechanism | Author |
|---|---|---|
| **Edit in Place save** | already writes the file in `editor.php` → commit right after | logged-in WP user (`display_name <email>`) |
| **Agent build / resolve** | explicit `wp instastudio commit` after writing **+** auto-sweep net (below) | agent identity, or sweep = `External` |
| **Publish / ship** | `publish.sh` runs `wp instastudio commit -m "ship: …"` (+ optional tag) before pushing | whoever ran it |

**Auto-sweep safety net:** on admin-side load of a Studio page (and before publish), if
`git status` shows the work-tree dirty (someone edited via SSH / MCP / CLI outside a
tracked event), auto-commit it as `external edit: <files>`. Guarantees **no change is
ever lost**, however it was made — the catch-all that makes "both edit on the server" safe.

**d) Concurrency lock** (small): Edit in Place takes a short per-page transient lock; the
agent's `commit`/build checks it and warns rather than clobbering. Even without it,
commit-per-change means every version is recoverable.

## 4. CLI surface — extend `includes/cli.php`
```
wp instastudio status                      # git available? dirty files? repo path
wp instastudio commit [--message=] [--author=] [--page=<slug>]
wp instastudio log    [--page=<slug>] [--limit=20] [--format=table|json]
wp instastudio diff   [<ref>] [--page=<slug>]      # default: working tree vs HEAD
wp instastudio restore <ref> [--page=<slug>]       # restore file/tree -> NEW commit "restore to <ref>"
wp instastudio tag    <name> | tags
```
`restore` is non-destructive: it commits the restored state on top, so the undo is itself
in history. Granularity: **per-page** (`--page`) and **whole-site** (omit it).

## 5. Admin timeline UI (phase 2)
In the Edit-in-Place toolbar, a **"History"** button on any page → a panel listing that
page's commits (message, author, time), each with **view diff** and **restore this
version** (one click → `restore`). Reuses the existing `hb-editor` chrome + gate
(localhost / opt-in only).

## 6. Optional GitHub mirror (phase 2/3)
Config a remote URL + token (**token in a wp-option, never in the repo**). After each
commit, optionally `git push` (one-way backup). PRs/review would be a later layer; the
core loop never depends on GitHub.

## 7. Config surface
```php
define('INSTAWP_HB_HISTORY', true);          // enable versioning (default true if git available)
define('IWPS_HISTORY_GIT_DIR', '…');         // override the external git dir
// options: agent identity, mirror remote + token
```

## 8. Phasing & effort
- **Phase 1 (MVP, medium):** backend + availability/degrade + bootstrap +
  commit-on-Edit-in-Place + auto-sweep + `status/commit/log/diff/restore` + external
  git-dir security. Gives real "see what changed + undo," agent and human, on the server.
- **Phase 2 (small–medium):** History timeline UI, `tag`, GitHub mirror push, per-page
  log in the editor.
- **Phase 3 (optional):** lock polish, agent-identity integration, branches.

## 9. Edge cases handled
- Host without git / `shell_exec` → degrade to file snapshots, reported in `status`.
- Heavy media in `site/assets/` → gitignore large binaries (git-lfs later) to avoid bloat.
- `.git` exposure → external git dir (+ 404-confirmed platform default).
- Non-Studio edits (SSH / MCP) → auto-sweep captures them.

## Open questions (recommended defaults)
1. **Git dir location** — `wp-content/uploads/.instastudio-git/` (outside webroot)? *(rec: yes)*
2. **Agent commits** — explicit `wp instastudio commit` **and** auto-sweep? *(rec: both)*
3. **GitHub mirror** — defer to phase 2? *(rec: yes)*

## Decision notes — why site-local, not GitHub-central
- **GitHub-central** = the repo is the source of truth; agents/devs author in a clone,
  humans review via **pull requests**, merge → deploy to the live site. Great review +
  off-site history, but it assumes edits **start in the repo**. Edit in Place happens on
  the server, so the server would always be ahead of GitHub → needs fragile two-way sync,
  and it re-introduces the build/deploy step InstaStudio deliberately removed.
- **Site-local git** fits "agent + human both edit the hosted files directly": history is
  captured where the edits happen, with zero deploy step. GitHub becomes an optional
  mirror you can switch on for backup (and PRs later) without the core loop depending on it.
- Rule of thumb: *mostly agents+devs, review matters → GitHub-central; mostly humans
  editing in place on the server → site-local.* We're the latter.
