# Projection protocol v2

Frozen authority: `flatrate.wiki.projection_sync_protocol.v2`

## Flow

```text
stage → chunk → validate → activate
```

No single unbounded snapshot upload.
No remote rollback API (CLI only: `flatrate:wiki:projection-rollback`).

## Auth

- HMAC-SHA256
- Headers: `X-FlatRate-Timestamp`, `X-FlatRate-Nonce`, `X-FlatRate-Signature`
- `MAX_CLOCK_SKEW_SECONDS=60`
- `NONCE_TTL_SECONDS=120`
- Signature binds: timestamp, nonce, HTTP method, path, SHA-256(raw body)
- Secret never enters frontend JS
- CSRF exemption is path-specific to the four projection routes only

## Routes

```text
POST /api/flatrate-wiki/projection/stage
POST /api/flatrate-wiki/projection/chunk
POST /api/flatrate-wiki/projection/validate
POST /api/flatrate-wiki/projection/activate
```

Activation requires `parent_graph_version_uuid == current active` (compare-and-switch).
Concurrent stale activation → REJECT. Prior active graph unchanged on failure.
