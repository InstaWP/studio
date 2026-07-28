# Bug report — `instawp` CLI resolves the wrong docroot on chroot sandbox nodes

> ✅ **RESOLVED (mostly) — verified 2026-07-28.** The CLI now resolves the correct
> `/web/<site>/public_html` chroot path. On a fresh sandbox: **`instawp sync push` works**
> (targets `/web/…`, no more `/home/<user>/…`) and **`instawp wp` (default SSH transport)
> works** (`core version` → `7.0.2`). InstaStudio's `publish.sh` + `wp.sh` now succeed via
> the plain CLI; their direct-SSH fallbacks are retained as a harmless safety net and no
> longer trigger.
> **One remaining case:** `instawp wp --api …` still uses the old `/home/<user>/tmp/…`
> path and fails with `sudo: unable to execute /home/<user>/tmp/command_temp_<n>:
> Permission denied`. Prefer the default (non-`--api`) transport, which is fixed. The
> original report is kept below for reference.

---

**To:** InstaWP CLI team
**From:** InstaStudio (dogfooding on fresh `--temporary` sandboxes)
**Date:** 2026-07-28
**CLI:** `@instawp/cli` (via nvm node v22)
**Severity:** ~~High~~ → **Mostly fixed** (see resolution banner). `--api` transport still affected.

## Summary

On some newly-provisioned InstaWP nodes, the CLI computes an SSH remote path under
**`/home/<user>/web/<site>/public_html/…`**, but the site's SSH account lands in a
**chroot whose real docroot is `/web/<site>/public_html/…`** (no `/home/<user>` prefix).
Every CLI operation that shells in with the computed path therefore fails.

## Impact

- **`instawp sync push`** fails immediately:
  ```
  rsync: [Receiver] mkdir "/home/<user>/web/<site>.instawp.site/public_html/wp-content/plugins/<x>" failed: No such file or directory (2)
  rsync error: error in file IO (code 11) at main.c(791) [Receiver=3.2.7]
  ✗ rsync exited with code 11
  ```
- **`instawp wp <site> -- <cmd>`** (default SSH transport) fails:
  ```
  -bash: line 1: cd: /home/<user>/web/<site>.instawp.site/public_html: No such file or directory
  ```
- **`instawp wp --api <site> -- <cmd>`** fails differently (the API runner drops a temp
  script under the same bad `/home/<user>` path):
  ```
  sudo: unable to execute /home/<user>/tmp/command_temp_<n>: Permission denied
  ```

Net effect: on an affected node you cannot deploy files or run WP-CLI through the CLI at
all. `instawp sites list/create/delete` and `instawp sites creds` still work (control-plane).

## Reproduction

1. `instawp create --name <x> --temporary` (observed on nodes with host IPs
   `64.23.251.152` and `147.182.203.159`; note NOT every node is affected — some resolve
   the docroot correctly).
2. `instawp sync push <x> --path ./somedir/ --remote-path wp-content/plugins/foo --refresh`
   → rsync `mkdir … No such file or directory`.
3. SSH in with the CLI key and confirm the real layout:
   ```bash
   ssh -i ~/.instawp/cli_key <user>@<host> 'pwd; ls -la; find / -maxdepth 6 -name wp-config.php 2>/dev/null'
   ```
   The session lands at `/` in a chroot; `wp-config.php` is at
   **`/web/<site>.instawp.site/public_html/wp-config.php`**, and
   `/home/<user>/…` **does not exist** inside the jail.

## Root cause (hypothesis)

The CLI builds the remote path as `"/home/<user>/web/<site>/public_html"` (the account's
real path as seen from *outside* the chroot / by the control plane), but SSH for these
accounts is confined to a chroot rooted such that the same directory is addressed as
`"/web/<site>/public_html"`. The `/home/<user>` prefix is valid out-of-jail but invalid
in the SSH session the CLI actually uses. So the mapping is:

```
CLI computes:  /home/<user>/web/<site>.instawp.site/public_html/<rest>
SSH sees:                  /web/<site>.instawp.site/public_html/<rest>
```

## Suggested fixes (CLI side)

1. **Resolve the docroot at runtime instead of assuming the prefix.** After opening the
   SSH connection, derive the docroot from the session (e.g. locate `wp-config.php`, or
   read it from the platform site record) rather than string-building `/home/<user>/…`.
2. **Or** detect the chroot: if the computed `/home/<user>/…` path fails a remote
   `test -d`, retry with the `/home/<user>` prefix stripped to `/web/…`.
3. Apply the same resolution to **all three** transports: `sync push` (rsync target), the
   default `wp` SSH transport (the `cd` target), and `wp --api` (the temp-script path).

## Workaround in the meantime

InstaStudio ships two wrappers that try the CLI first and fall back to direct SSH with the
corrected path (using the CLI's own key `~/.instawp/cli_key`):

- `scripts/publish.sh` — `sync push` with an rsync fallback to `<user>@<host>:/web/<site>/public_html/…`.
- `scripts/wp.sh` — WP-CLI with an SSH fallback that harvests the real docroot from a
  `sync push --dry-run` probe line, then runs `cd /web/<site>/public_html && wp …`.

Manual equivalent:
```bash
KEY=~/.instawp/cli_key
DR=/web/<site>.instawp.site/public_html
rsync -az -e "ssh -i $KEY" ./localdir/ <user>@<host>:$DR/wp-content/plugins/foo/
ssh  -i $KEY <user>@<host> "cd $DR && wp plugin list"
```

These are stopgaps; the real fix belongs in the CLI's docroot resolution.
