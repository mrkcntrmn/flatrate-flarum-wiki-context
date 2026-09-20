# R1 implementation status

Work order: `WIKI-001C-FLARUM-WIKI-CONTEXT-SKELETON-R1`

## IMPLEMENTED_AND_TESTED

- Composer package identity `flatrate/flarum-wiki-context`
- Fail-closed feature gate defaults (including admin ghost preview + public rollout)
- Prefix-aware migration definitions (9 migrations)
- Projection HMAC/nonce/route contract helpers
- Projection stage/chunk/validate/activate service skeleton + API routes
- Wiki-scope filter registration (`filter[wiki-scope]`)
- WIKI-001D primary context + bounded relevance persistence
- WIKI-001D optimistic revision / 409 correction path
- WIKI-001D board-drift guard + audit/provenance
- WIKI-001E public feed dual-gate fail-closed enforcement
- WIKI-001E active-projection semantic membership query
- Preview acceptance schema + freshness helper
- Directory display + catch-all policy constants
- CLI command skeletons (status/reconcile/rollback/backfill/preview-*)
- Forum JS shell + preview banner/audience contracts
- CI lanes: PHP, JS, Flarum 1.8.19 smoke, MariaDB dual-prefix
- Disposable harness scripts

## SKELETON_ONLY

- Full projection chunk row materialization into scope/ancestor tables
- Full validate integrity (board bindings, catch-all invariants, reconstruct digest)
- Activation reconciliation reporting
- Operator rollback transaction
- Root backfill apply path
- Preview-accept fixtures/validation
- Public serializers hydration from DB
- Browse forum routes (`/browse/<scope-uuid>`)
- WIKI-001E exact-head CI/disposable acceptance
- Full WIKI-001E native Flarum Latest/Top/pagination qualification
- Ghost-preview audience-simulated query execution

## DEFERRED

- PRODUCTION_INSTALL / PRODUCTION_SCHEMA_APPLY / PRODUCTION_EXTENSION_ENABLE
- PRODUCTION_PROJECTION_SYNC / PUBLIC_BROWSE_ENABLE / MEMBER_CONTEXT_WRITES
- PUBLIC_DERIVED_FEEDS / BRAND_FEED_CUTOVER / PUBLIC_ROLLOUT
- LABOR_LAW_PUBLIC_ROLLOUT / PACKAGIST_STABLE_RELEASE
- Production WIKI-001E feed enablement / public cutover
- Admin ghost-preview fixture execution as guest/standard_member
