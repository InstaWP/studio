#!/usr/bin/env bash
# Assemble InstaStudio into an existing WordPress install (local dev). Symlinks the
# theme + plugin and copies the starter site/ so the theme can render it.
#
#   bash scripts/bootstrap.sh /path/to/wordpress
set -euo pipefail
WP="${1:-}"; ROOT="$(cd "$(dirname "$0")/.." && pwd)"
[ -d "$WP/wp-content" ] || { echo "Usage: bootstrap.sh /path/to/wordpress (dir containing wp-content)"; exit 1; }
ln -sfn "$ROOT/plugins/iwp-studio"    "$WP/wp-content/plugins/iwp-studio"
ln -sfn "$ROOT/themes/iwp-studio"     "$WP/wp-content/themes/iwp-studio"
ln -sfn "$ROOT/plugins/iwp-feedback"  "$WP/wp-content/plugins/iwp-feedback"
[ -d "$WP/site" ] || cp -a "$ROOT/site" "$WP/site"   # source HTML at the webroot (INSTAWP_HB_DIR default)
echo "Linked theme + plugin; seeded $WP/site."
echo "Next: activate the 'iwp-studio' + 'iwp-feedback' plugins and the 'iwp-studio' theme,"
echo "then run: wp instastudio pages   (creates WP pages + front page). See docs/GETTING-STARTED.md."
