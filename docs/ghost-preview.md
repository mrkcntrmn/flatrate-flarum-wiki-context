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
