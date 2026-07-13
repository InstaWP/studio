# Packaging as an InstaWP blueprint (later)

The real unit of delivery is an **InstaWP blueprint**: a sandbox that already carries the
capabilities **and** the agent loop, so "clone → the site is InstaStudio-ready."

A blueprint bundles:
1. **Capabilities** — the `iwp-studio` + `iwp-feedback` plugins + the `iwp-studio` companion theme, pre-activated.
2. **Playbooks** — the skills (`build-page`, `resolve-feedback`) promoted to **InstaMCP
   site-skills** (`skill_save`) so they travel with the site and any MCP agent gets them.
3. **Seed + config** — a starter `site/`, `CLAUDE.md`, and `DESIGN.md`.
4. **Hosting + publish** — the sandbox itself + one-click promote to production.

Status: **deferred** (dogfood the loop first). This repo is the source the blueprint is
built from. See the InstaStudio strategy notes for the crawl → walk → run sequence.
