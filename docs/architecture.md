# Architecture — flatrate/flarum-wiki-context

**THIS PACKAGE IS NOT YET AUTHORIZED FOR PRODUCTION INSTALLATION.**

## Source-of-truth split

```text
FLATRATE APPLICATION
  canonical semantic graph
  canonical scope identities
  hierarchy / graph versions
  domain relationships

FLARUM COMMUNITY
  discussion bodies
  replies
  visibility
  moderation
  latest activity
  primary Flarum board
  canonical discussion -> primary semantic scope
  canonical discussion -> explicit relevance scopes

FLARUM WIKI PROJECTION (this extension)
  rebuildable scope catalog
  ancestor closure
  active graph-version pointer
  board/scope mapping
  public labels
```

This extension is **not** a second canonical graph authority.
It does not copy discussion bodies into a new application database.
Flarum tag IDs are not universal WIKI IDs.

## Package identity

- Composer: `flatrate/flarum-wiki-context`
- Namespace: `FlatRate\WikiContext`
- Target: Flarum 1.8.19 / PHP ^8.1

## Feature gates

All meaningful behavior defaults **false**:

```text
flatrate-wiki.projection_sync_enabled
flatrate-wiki.browse_routes_enabled
flatrate-wiki.context_writes_enabled
flatrate-wiki.derived_feeds_enabled
flatrate-wiki.brand_root_derived_feeds_enabled
flatrate-wiki.admin_ghost_preview_enabled
flatrate-wiki.public_rollout_enabled
```

Hard invariant: `PACKAGE_INSTALL != FEATURE_ENABLEMENT`

## Control repository authority

Contracts are frozen in `mrkcntrmn/flatrate-wiki` (WIKI-001B/C/P).
Do not casually redesign semantics in this repository.
