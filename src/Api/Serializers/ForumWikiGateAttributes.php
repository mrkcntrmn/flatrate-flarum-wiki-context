<?php

namespace FlatRate\WikiContext\Api\Serializers;

use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Browser-safe gate facts only. Never expose projection secrets.
 */
class ForumWikiGateAttributes
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(ForumSerializer $serializer, $model, array $attributes): array
    {
        // Prefer Settings::serializeToForum in extend.php; this helper remains
        // available for explicit attribute composition with safe bool parsing.
        $attributes['flatRateWikiBrowseRoutesEnabled'] = $this->enabled(FeatureGates::BROWSE_ROUTES_ENABLED);
        $attributes['flatRateWikiContextWritesEnabled'] = $this->enabled(FeatureGates::CONTEXT_WRITES_ENABLED);
        $attributes['flatRateWikiDerivedFeedsEnabled'] = $this->enabled(FeatureGates::DERIVED_FEEDS_ENABLED);
        $attributes['flatRateWikiBrandRootDerivedFeedsEnabled'] = $this->enabled(FeatureGates::BRAND_ROOT_DERIVED_FEEDS_ENABLED);
        $attributes['flatRateWikiAdminGhostPreviewEnabled'] = $this->enabled(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED);
        $attributes['flatRateWikiPublicRolloutEnabled'] = $this->enabled(FeatureGates::PUBLIC_ROLLOUT_ENABLED);

        return $attributes;
    }

    private function enabled(string $key): bool
    {
        $value = $this->settings->get($key, '0');

        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
    }
}
