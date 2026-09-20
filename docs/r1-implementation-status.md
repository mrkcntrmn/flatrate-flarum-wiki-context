# R1 implementation status

Work order: `WIKI-001F-DISCUSSION-FIRST-BROWSE-CONTEXT-UI-COMPLETION-R1`

## Source status

```text
WIKI-001C=PASS
WIKI-001D=PASS
WIKI-001E=PASS
WIKI-001F=PASS_SOURCE_QUALIFIED
PRODUCTION_INSTALL=false
PUBLIC_ROLLOUT=false
```

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
- WIKI-001F discussion-first browse (`/browse/{uuid}` + cosmetic slug)
- WIKI-001F native `DiscussionListState` + `filter[wiki-scope]` feed path
- WIKI-001F direct-child directory budgets (desktop 8/24, mobile 6/18)
- WIKI-001F Misc last / budget-exempt / not-overflow
- WIKI-001F public browse dual gate + noindex middleware
- WIKI-001F `meta.activeGraphVersionId` public-safe scope meta
- WIKI-001F contextual Start Discussion → native composer semantic preload
- WIKI-001F bounded Relevant-to picker + `/scopes/search`
- WIKI-001F discussion page/feed context presentation + `/scopes/resolve`
- WIKI-001F browse a11y / resize / stale-search protection

## SKELETON_ONLY

- Full projection chunk row materialization into scope/ancestor tables
- Full validate integrity (board bindings, catch-all invariants, reconstruct digest)
- Activation reconciliation reporting
- Operator rollback transaction
- Root backfill apply path
- Preview-accept fixtures/validation
- Ghost-preview audience-simulated query execution

## DEFERRED

- PRODUCTION_INSTALL / PRODUCTION_SCHEMA_APPLY / PRODUCTION_EXTENSION_ENABLE
- PRODUCTION_PROJECTION_SYNC / PUBLIC_BROWSE_ENABLE / MEMBER_CONTEXT_WRITES
- PUBLIC_DERIVED_FEEDS / BRAND_FEED_CUTOVER / PUBLIC_ROLLOUT
- LABOR_LAW_PUBLIC_ROLLOUT / PACKAGIST_STABLE_RELEASE
- Production WIKI-001E feed enablement / public cutover
- Admin ghost-preview fixture execution as guest/standard_member (WIKI-001P)
- Browse indexability / sitemap inclusion (WIKI-001I)
