#!/usr/bin/env bash
# Publish the site/ source to an InstaWP site (the Ship leg). Sandbox/staging by
# default; refuses obvious production targets. Requires the `instawp` CLI.
#
#   SITE=my-sandbox bash scripts/publish.sh              # push site/ + theme + plugins
#   SITE=my-sandbox DRY_RUN=1 bash scripts/publish.sh    # show what would change
#
# Uses `instawp sync push`. On some InstaWP nodes the CLI computes an SSH path
# under /home/<user>/... but the sandbox SSH lands in a chroot where the real
# path is /web/... — so `sync push` fails with an rsync "No such file or
# directory" mkdir error. This script detects that and falls back to a direct
# rsync over the CLI's own key (~/.instawp/cli_key), correcting the path. The
# fallback is transparent; when the CLI bug is fixed it is simply never used.
set -euo pipefail
SITE="${SITE:-}"; ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SSH_KEY="${INSTAWP_SSH_KEY:-$HOME/.instawp/cli_key}"
[ -n "$SITE" ] || { echo "Set SITE=<instawp-site-slug>"; exit 1; }
case "$SITE" in *prod*|*production*|*-live*) echo "REFUSING: '$SITE' looks like production."; exit 1;; esac
command -v instawp >/dev/null || { echo "instawp CLI not found (npm i -g @instawp/cli)"; exit 1; }
DRY="${DRY_RUN:-}"

# push <local-dir-with-trailing-slash> <remote-path> [--webroot]
# Try the CLI; if it fails on the /home-vs-/web chroot path, rsync directly.
REFRESH="--refresh"   # first push refreshes SSH access; cleared after
push() {
	local local_dir="$1" remote_path="$2"; shift 2
	local extra=("$@") flags=()
	[ "$DRY" = "1" ] && flags+=(--dry-run)
	local out ec=0
	echo "→ $remote_path"
	out="$(instawp sync push "$SITE" --path "$local_dir" --remote-path "$remote_path" ${REFRESH} "${extra[@]}" "${flags[@]}" 2>&1)" || ec=$?
	REFRESH=""
	if [ "$ec" -eq 0 ]; then
		printf '%s\n' "$out" | grep -qi 'error\|failed' && ec=1 || { echo "  ✓ (cli)"; return 0; }
	fi
	# --- fallback: parse the CLI's intended target and correct the chroot path ---
	# CLI prints:  ℹ Pushing <local> -> <host>:/home/<user>/web/<site>/public_html/<...>
	local dest host rpath user fixed
	dest="$(printf '%s\n' "$out" | sed -nE 's/.*-> ([^ ]+:[^ ]+).*/\1/p' | head -1)"
	if [ -z "$dest" ]; then
		echo "  ✗ CLI push failed and no target could be parsed for fallback:"; printf '%s\n' "$out" | sed 's/^/    /'; return 1
	fi
	host="${dest%%:*}"; rpath="${dest#*:}"
	user="$(printf '%s' "$rpath" | sed -nE 's#^/home/([^/]+)/.*#\1#p')"
	fixed="$(printf '%s' "$rpath" | sed -E 's#^/home/[^/]+/web/#/web/#')"
	if [ -z "$user" ] || [ "$fixed" = "$rpath" ]; then
		echo "  ✗ CLI push failed; path is not the known /home->/web chroot case:"; printf '%s\n' "$out" | sed 's/^/    /'; return 1
	fi
	[ -f "$SSH_KEY" ] || { echo "  ✗ fallback needs an SSH key at $SSH_KEY (set INSTAWP_SSH_KEY)"; return 1; }
	local rflags=(-az); [ "$DRY" = "1" ] && rflags+=(--dry-run -v)
	echo "  … CLI path bug; falling back to direct rsync -> $user@$host:$fixed"
	rsync "${rflags[@]}" -e "ssh -i $SSH_KEY -o StrictHostKeyChecking=no" "$local_dir" "$user@$host:$fixed"
	echo "  ✓ (rsync fallback)"
}

echo "Publishing to $SITE …"
# source HTML -> webroot (theme reads it from there; adjust --remote-path to your INSTAWP_HB_DIR)
push "$ROOT/site/"    site                  --webroot
# theme + plugin -> wp-content
push "$ROOT/themes/"  wp-content/themes
push "$ROOT/plugins/" wp-content/plugins
echo "Done. (Purge the CDN if your host caches: instawp cache purge $SITE)"
echo "Next on a fresh site: instawp wp $SITE -- instastudio pages   (or over SSH if the CLI wp path 404s)"
