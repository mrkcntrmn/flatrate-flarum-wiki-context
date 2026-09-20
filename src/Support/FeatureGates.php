<?php

namespace FlatRate\WikiContext\Support;

/**
 * Fail-closed feature gates.
 *
 * PACKAGE_INSTALL != FEATURE_ENABLEMENT
 */
final class FeatureGates
{
    public const PROJECTION_SYNC_ENABLED = 'flatrate-wiki.projection_sync_enabled';
    public const BROWSE_ROUTES_ENABLED = 'flatrate-wiki.browse_routes_enabled';
    public const CONTEXT_WRITES_ENABLED = 'flatrate-wiki.context_writes_enabled';
    public const DERIVED_FEEDS_ENABLED = 'flatrate-wiki.derived_feeds_enabled';
    public const BRAND_ROOT_DERIVED_FEEDS_ENABLED = 'flatrate-wiki.brand_root_derived_feeds_enabled';
    public const ADMIN_GHOST_PREVIEW_ENABLED = 'flatrate-wiki.admin_ghost_preview_enabled';
    public const PUBLIC_ROLLOUT_ENABLED = 'flatrate-wiki.public_rollout_enabled';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::PROJECTION_SYNC_ENABLED,
            self::BROWSE_ROUTES_ENABLED,
            self::CONTEXT_WRITES_ENABLED,
            self::DERIVED_FEEDS_ENABLED,
            self::BRAND_ROOT_DERIVED_FEEDS_ENABLED,
            self::ADMIN_GHOST_PREVIEW_ENABLED,
            self::PUBLIC_ROLLOUT_ENABLED,
        ];
    }

    public static function defaultClosedMap(): array
    {
        $map = [];
        foreach (self::all() as $key) {
            $map[$key] = false;
        }

        return $map;
    }
}
