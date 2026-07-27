#!/usr/bin/env bash
# Run WP-CLI against an InstaWP site — with the same chroot fallback publish.sh uses.
#
#   SITE=my-sandbox bash scripts/wp.sh plugin activate iwp-studio iwp-feedback
#   SITE=my-sandbox bash scripts/wp.sh instastudio pages
#
# Tries `instawp wp <site> -- <args>` first. On the nodes where the CLI resolves
# an SSH path under /home/<user>/... but SSH lands in a /web/... chroot (so the
# platform wp runner 404s / hits a sudo tmp-permission error), it harvests the
# real docroot and runs WP-CLI directly over SSH (~/.instawp/cli_key). When the
# CLI bug is fixed this fallback is simply never taken.
set -euo pipefail
SITE="${SITE:-}"; SSH_KEY="${INSTAWP_SSH_KEY:-$HOME/.instawp/cli_key}"
[ -n "$SITE" ] || { echo "Set SITE=<instawp-site-slug>; args after it are the WP-CLI command"; exit 1; }
[ "$#" -gt 0 ] || { echo "Usage: SITE=<slug> bash scripts/wp.sh <wp-cli args...>"; exit 1; }
command -v instawp >/dev/null || { echo "instawp CLI not found (npm i -g @instawp/cli)"; exit 1; }

# 1) Try the platform path first.
out=""; ec=0
out="$(instawp wp "$SITE" -- "$@" 2>&1)" || ec=$?
if [ "$ec" -eq 0 ] && ! printf '%s\n' "$out" \
	| grep -qiE 'No such file or directory|Permission denied|command_temp|sudo: unable'; then
	printf '%s\n' "$out"; exit 0
fi

# 2) Fallback: harvest host + real docroot from a dry-run sync-push probe.
echo "… platform wp path failed; falling back to direct SSH WP-CLI" >&2
probe="$(mktemp -d)"; : > "$probe/.iwp-probe"
line="$(instawp sync push "$SITE" --path "$probe/" --remote-path . --refresh --dry-run 2>&1 \
	| sed -nE 's/.*-> ([^ ]+:[^ ]+).*/\1/p' | head -1)"
rm -rf "$probe"
[ -n "$line" ] || { echo "✗ could not harvest SSH target for fallback" >&2; printf '%s\n' "$out" >&2; exit 1; }
host="${line%%:*}"; rpath="${line#*:}"
user="$(printf '%s' "$rpath" | sed -nE 's#^/home/([^/]+)/.*#\1#p')"
# docroot = path up to and including /public_html, with /home/<user> corrected to /web
docroot="$(printf '%s' "$rpath" | sed -E 's#(/public_html)/.*#\1#; s#^/home/[^/]+/web/#/web/#')"
[ -n "$user" ] && [ -n "$docroot" ] || { echo "✗ unexpected SSH path: $rpath" >&2; exit 1; }
[ -f "$SSH_KEY" ] || { echo "✗ fallback needs an SSH key at $SSH_KEY (set INSTAWP_SSH_KEY)" >&2; exit 1; }

# Re-quote the WP-CLI args for the remote shell.
rcmd="cd $(printf '%q' "$docroot") && wp"
for a in "$@"; do rcmd+=" $(printf '%q' "$a")"; done
exec ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$user@$host" "$rcmd"
