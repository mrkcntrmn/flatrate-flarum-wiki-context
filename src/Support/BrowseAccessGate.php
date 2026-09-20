<?php

namespace FlatRate\WikiContext\Support;

use FlatRate\WikiContext\Projection\SettingsReader;

/**
 * Ordinary public browse access.
 *
 * WIKI-001F keeps public browse and WIKI-001P ghost preview as separate
 * authorization paths. Admin preview must never make the ordinary public
 * scope APIs reachable while public rollout is closed.
 */
final class BrowseAccessGate
{
    public function __construct(private SettingsReader $settings)
    {
    }

    public function publicBrowseEnabled(): bool
    {
        return $this->settings->bool(FeatureGates::BROWSE_ROUTES_ENABLED)
            && $this->settings->bool(FeatureGates::PUBLIC_ROLLOUT_ENABLED);
    }
}
