# flatrate/flarum-wiki-context

**THIS PACKAGE IS NOT YET AUTHORIZED FOR PRODUCTION INSTALLATION.**

Source-only Flarum extension for FlatRate WIKI semantic context projection
(`WIKI-001C/D/E` / `WIKI-001P`). Target runtime: **Flarum 1.8.19**, **PHP ^8.1**.

Current source status:

```text
WIKI-001C=PASS
WIKI-001D=PASS
WIKI-001E=IN_QUALIFICATION
PRODUCTION_INSTALL=false
PUBLIC_ROLLOUT=false
```

## Hard boundary

```text
PRODUCTION_INSTALL=false
PRODUCTION_SCHEMA_APPLY=false
PRODUCTION_EXTENSION_ENABLE=false
PRODUCTION_PROJECTION_SYNC=false
PUBLIC_ROLLOUT=false
PACKAGIST_STABLE_PUBLISH=false
```

Installing or enabling this extension must **not** expose WIKI behavior to members.
`PACKAGE_INSTALL != FEATURE_ENABLEMENT`

## Architecture

See [docs/architecture.md](docs/architecture.md).

This package owns rebuildable Flarum-local projection + discussion context assignment.
It does **not** own the canonical FlatRate semantic graph or discussion bodies.

## Feature gates (default closed)

```text
flatrate-wiki.projection_sync_enabled=false
flatrate-wiki.browse_routes_enabled=false
flatrate-wiki.context_writes_enabled=false
flatrate-wiki.derived_feeds_enabled=false
flatrate-wiki.brand_root_derived_feeds_enabled=false
flatrate-wiki.admin_ghost_preview_enabled=false
flatrate-wiki.public_rollout_enabled=false
```

## Projection flow

See [docs/projection-protocol.md](docs/projection-protocol.md).

```text
stage → chunk → validate → activate
```

HMAC-SHA256 S2S auth. CLI-only rollback. No remote rollback API.

## Admin ghost preview

See [docs/ghost-preview.md](docs/ghost-preview.md).

Required before public rollout: admin ghost preview acceptance receipt bound to
active graph / build / contract / display-policy digest.

## Build

```bash
cd js && npm ci && npm run build
# tracked dist must remain reproducible:
git diff --exit-code js/dist
```

## Tests

```bash
composer validate --strict
composer update
find src tests extend.php migrations -name '*.php' | xargs -n1 php -l
vendor/bin/phpunit
node --test js/tests/*.test.mjs
bash scripts/disposable-flarum-smoke.sh
TABLE_PREFIX= bash scripts/mariadb-migration-harness.sh
TABLE_PREFIX=flarum_ bash scripts/mariadb-migration-harness.sh
```

## Disposable harness

- `scripts/disposable-flarum-smoke.sh` — Flarum 1.8.19 resolve/register/boot/enable/disable
- `scripts/mariadb-migration-harness.sh` — prefix-aware migration up/down/reinstall

## Production boundary

Do not install or enable this package on production from the current source qualification.
The current tranche is WIKI-001E derived semantic-feed qualification — not deployment.
