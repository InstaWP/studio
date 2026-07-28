#!/usr/bin/env bash
# One command: from a clone to a live InstaStudio site on InstaWP.
# Creates a sandbox, pushes the engine + feedback + companion theme + starter
# site/, activates them, and registers the pages.
#
#   bash scripts/quickstart.sh <name>             # create a temporary sandbox <name> and set it up
#   PERSIST=1 bash scripts/quickstart.sh <name>   # create a permanent site instead of a --temporary sandbox
#   SITE=<slug> bash scripts/quickstart.sh        # set up an EXISTING site (skip create)
#
# Wraps: instawp create -> scripts/publish.sh (file push) -> scripts/wp.sh
# (activate + instastudio pages). It goes through the fallback-safe wrappers, so
# it works on any node (plain CLI when healthy, direct-SSH fallback if not).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NAME="${1:-}"; SITE="${SITE:-}"
command -v instawp >/dev/null || { echo "instawp CLI not found (npm i -g @instawp/cli && instawp login)"; exit 1; }

if [ -z "$SITE" ]; then
	[ -n "$NAME" ] || { echo "Usage: bash scripts/quickstart.sh <name>   (or SITE=<slug> to use an existing site)"; exit 1; }
	flags=(--name "$NAME"); [ "${PERSIST:-}" = "1" ] || flags+=(--temporary)
	echo "▸ Creating site '$NAME'${PERSIST:+ (persistent)}${PERSIST:-  (temporary)} …"
	instawp create "${flags[@]}"
	SITE="$NAME"
else
	echo "▸ Setting up existing site '$SITE'"
fi

echo ""; echo "▸ Pushing engine + feedback + companion theme + starter site/ …"
SITE="$SITE" bash "$ROOT/scripts/publish.sh"

echo ""; echo "▸ Activating plugins + companion theme …"
SITE="$SITE" bash "$ROOT/scripts/wp.sh" plugin activate iwp-studio iwp-feedback
SITE="$SITE" bash "$ROOT/scripts/wp.sh" theme activate iwp-studio

echo ""; echo "▸ Registering pages …"
SITE="$SITE" bash "$ROOT/scripts/wp.sh" instastudio pages

URL="$(instawp sites list 2>/dev/null | grep -F "$SITE" | grep -oE 'https://[^ │]+' | head -1)"
[ -n "$URL" ] || URL="https://$SITE.instawp.site"
echo ""
echo "✓ InstaStudio is live on '$SITE'."
echo "  $URL"
echo ""
echo "  Next:"
echo "    • Edit or add pages in site/*.html, then re-register:"
echo "        SITE=$SITE bash scripts/wp.sh instastudio pages"
echo "    • Point your AI agent at CLAUDE.md and ask it to add a page."
echo "    • Ship changes later with: SITE=$SITE bash scripts/publish.sh"
