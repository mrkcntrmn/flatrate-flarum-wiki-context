# Admin ghost preview (WIKI-001P)

Settings (independent):

```text
flatrate-wiki.admin_ghost_preview_enabled=false
flatrate-wiki.public_rollout_enabled=false
```

Required pre-public state:

```text
ADMIN_GHOST_PREVIEW=true
PUBLIC_ROLLOUT=false
```

## Same components

Preview must use the same browse/directory/breadcrumb/filter/feed-row/context UI as public mode.
Mode changes authorization/audience/mutation capability — not a parallel fake renderer.

## Audience simulation

Profiles: `guest`, `standard_member` (default).
`ADMIN_ELEVATED_VISIBILITY_FOR_USER_PREVIEW=false`

## Read-only in preview

Discussion create, context/relevance writes, projection activation, moderation mutation: false.
Dry-run validation endpoint allowed.

## Acceptance receipt

Public rollout requires a fresh PASS receipt bound to active graph, build, contract, and display-policy digest.


## Source completion split

WIKI-001P1 is deliberately split so secure preview entry is qualified before acceptance persistence.

### P1A — admin authorization + status boundary

```text
SERVER_PREVIEW_AUTH=ADMIN_ONLY
STATUS_API=GET /api/flatrate-wiki/preview/status
CACHE_CONTROL=no-store
X_ROBOTS_TAG=noindex,nofollow
PUBLIC_BROWSE_BYPASS=false
READ_ONLY=true
ACCEPTANCE_RECEIPT_CREATION=false
```

The status API is an operational control surface only. It does not render a parallel
preview implementation, activate a projection, expose ordinary /browse routes, or
create a PASS receipt.

### P1B — required before production preview acceptance

Still required:

```text
guest + standard_member audience-simulated real query execution
required fixture runner
dry-run context validation
fixture results digest
real acceptance receipt persistence
active graph/build/contract/display-policy binding
freshness/staleness enforcement against persisted receipt
public rollout rejection when receipt is missing/stale
```

Public rollout remains unauthorized until P1B and production ghost-preview
acceptance both pass.
