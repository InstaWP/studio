#!/usr/bin/env bash
# Publish the site/ source to an InstaWP site (the Ship leg). Sandbox/staging by
# default; refuses obvious production targets. Requires the `instawp` CLI.
#
#   SITE=my-sandbox bash scripts/publish.sh              # push site/ + theme + plugins
#   SITE=my-sandbox DRY_RUN=1 bash scripts/publish.sh    # show what would change
set -euo pipefail
SITE="${SITE:-}"; ROOT="$(cd "$(dirname "$0")/.." && pwd)"
[ -n "$SITE" ] || { echo "Set SITE=<instawp-site-slug>"; exit 1; }
case "$SITE" in *prod*|*production*|*-live*) echo "REFUSING: '$SITE' looks like production."; exit 1;; esac
command -v instawp >/dev/null || { echo "instawp CLI not found (npm i -g @instawp/cli)"; exit 1; }
FLAGS=(); [ "${DRY_RUN:-}" = "1" ] && FLAGS+=(--dry-run)
echo "Publishing to $SITE …"
# source HTML -> webroot (theme reads it from there; adjust --remote-path to your INSTAWP_HB_DIR)
instawp sync push "$SITE" --path "$ROOT/site/"    --remote-path site --webroot "${FLAGS[@]}"
# theme + plugin -> wp-content
instawp sync push "$SITE" --path "$ROOT/themes/"  --remote-path wp-content/themes  "${FLAGS[@]}"
instawp sync push "$SITE" --path "$ROOT/plugins/" --remote-path wp-content/plugins "${FLAGS[@]}"
echo "Done. (Purge the CDN if your host caches: instawp cache purge $SITE)"
