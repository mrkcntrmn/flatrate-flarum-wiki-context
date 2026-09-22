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

P1B source qualification proves **server/query semantics** only.
`SAME_USER_COMPONENT_PATH` / browser rendering acceptance remain production ghost-preview acceptance.

## Audience simulation

Profiles: `guest`, `standard_member` (default).
`ADMIN_ELEVATED_VISIBILITY_FOR_USER_PREVIEW=false`

## Read-only in preview

Discussion create, context/relevance writes, projection activation, moderation mutation: false.
Dry-run validation endpoint allowed.

## Acceptance receipt

Public rollout requires a fresh PASS receipt bound to:

```text
active_graph_version_uuid
extension_build_id          (WikiBuild::BUILD_ID)
wiki_contract_version       (WikiContract::VERSION)
directory_display_policy_digest
audience_profiles_verified
fixture_results_digest
```

`PublicRolloutPolicy::assertFreshAcceptance()` rejects missing / non-PASS / stale bindings.
There is no public-rollout mutation API in P1B; future enablement must call that policy.

## Source completion split

### P1A — admin authorization + status boundary — SOURCE MERGED

```text
SERVER_PREVIEW_AUTH=ADMIN_ONLY
STATUS_API=GET /api/flatrate-wiki/preview/status
CACHE_CONTROL=no-store
X_ROBOTS_TAG=noindex,nofollow
PUBLIC_BROWSE_BYPASS=false
READ_ONLY=true
ACCEPTANCE_RECEIPT_CREATION=false
```

### P1B — fixtures + receipt + freshness policy — THIS TRANCHE

```text
ACCEPT_API=POST /api/flatrate-wiki/preview/accept
CLI=flatrate:wiki:preview-accept --accepted-by=<ADMIN_USER_ID>
SHARED_SERVICE=PreviewAcceptanceService
FIXTURE_RUNNER=guest+standard_member
RECEIPT_PERSISTENCE=PASS_ONLY_AFTER_TOTAL_SUCCESS
PUBLIC_ROLLOUT_POLICY=PublicRolloutPolicy::assertFreshAcceptance
SAME_USER_COMPONENT_PATH_CLAIMED=false
PRODUCTION_MUTATION=false
PRODUCTION_GHOST_PREVIEW_ACCEPTANCE=false
```

Both API and CLI call one shared `PreviewAcceptanceService`.
CLI is not a logged-in browser actor; it requires an explicit verified admin user ID.
