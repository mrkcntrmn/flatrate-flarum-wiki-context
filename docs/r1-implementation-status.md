# R1 implementation status

Work order: `WIKI-001P1B-GHOST-PREVIEW-FIXTURES-RECEIPT-R1`

## Source status

```text
WIKI-001C=PASS
WIKI-001D=PASS
WIKI-001E=PASS
WIKI-001F=PASS_SOURCE_QUALIFIED
WIKI-001P0=PASS_SOURCE_MERGED
WIKI-001P1A=PASS_SOURCE_MERGED
WIKI-001P1B=IN_PROGRESS_SOURCE
PRODUCTION_INSTALL=false
PUBLIC_ROLLOUT=false
SAME_USER_COMPONENT_PATH_CLAIMED=false
PRODUCTION_GHOST_PREVIEW_ACCEPTANCE=false
```

## IMPLEMENTED_AND_TESTED

- Composer package identity `flatrate/flarum-wiki-context`
- Fail-closed feature gate defaults (including admin ghost preview + public rollout)
- Prefix-aware migration definitions (9 migrations)
- Projection HMAC/nonce/route contract helpers
- Projection stage/chunk/validate/activate with scope+ancestor materialization + integrity validation
- Projection reconcile CLI (read-only) + rollback CLI (default dry-run; `--execute` required)
- Wiki-scope filter registration (`filter[wiki-scope]`)
- WIKI-001D primary context + bounded relevance persistence
- WIKI-001E public feed dual-gate fail-closed enforcement
- WIKI-001E active-projection semantic membership query
- WIKI-001F discussion-first browse + directory budgets + public dual gate
- WIKI-001P1A admin-only preview status API
- WIKI-001P1B explicit `WikiBuild::BUILD_ID` + `WikiContract::VERSION`
- WIKI-001P1B `DirectoryDisplayPolicy::digest()` (canonical JSON SHA-256)
- WIKI-001P1B audience factory (guest/standard_member; never admin elevated)
- WIKI-001P1B deterministic fixture runner + fixture_results_digest
- WIKI-001P1B `PreviewAcceptanceService` + repository PASS-only persistence
- WIKI-001P1B `POST /api/flatrate-wiki/preview/accept` (RequestUtil admin)
- WIKI-001P1B CLI `preview-accept --accepted-by=<admin_user_id>`
- WIKI-001P1B `PublicRolloutPolicy::assertFreshAcceptance()` (no mutation API)
- CI lanes: PHP, JS, Flarum 1.8.19 smoke, MariaDB dual-prefix

## SKELETON_ONLY / DEFERRED

- Root backfill apply path
- Route-alias row materialization
- PRODUCTION_INSTALL / PRODUCTION_SCHEMA_APPLY / PRODUCTION_EXTENSION_ENABLE
- PRODUCTION_PROJECTION_SYNC / PUBLIC_BROWSE_ENABLE / MEMBER_CONTEXT_WRITES
- PUBLIC_DERIVED_FEEDS / BRAND_FEED_CUTOVER / PUBLIC_ROLLOUT
- Browser/render same-component ghost-preview acceptance (production P1)
- Public rollout enablement path that calls `PublicRolloutPolicy` (future)
