<?php

namespace FlatRate\WikiContext\Api\Serializers;

use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Repository\DiscussionContextRepository;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Api\Serializer\BasicDiscussionSerializer;
use Flarum\Discussion\Discussion;

/**
 * Public-safe discussion context attributes only.
 * Never expose provenance internals, audit, VIN, email, phone, Garage, secrets.
 */
class DiscussionWikiContextAttributes
{
    public function __construct(
        private DiscussionContextRepository $contexts,
        private SettingsReader $settings
    ) {
    }

    public function __invoke(BasicDiscussionSerializer $serializer, Discussion $discussion, array $attributes): array
    {
        if (!$this->readPathEnabled()) {
            $attributes['flatRateWikiContext'] = null;
            $attributes['flatRateWikiRelevance'] = [];

            return $attributes;
        }

        $context = $this->contexts->find((int) $discussion->id);

        if ($context === null) {
            $attributes['flatRateWikiContext'] = null;
            $attributes['flatRateWikiRelevance'] = [];

            return $attributes;
        }

        $scopeIds = array_values(array_map(
            fn ($row) => strtolower((string) $row->relevant_scope_uuid),
            $this->contexts->activeRelevance((int) $discussion->id)
        ));
        sort($scopeIds);

        $attributes['flatRateWikiContext'] = [
            'primaryScopeId' => strtolower((string) $context->primary_scope_uuid),
            'contextRevision' => (int) $context->context_revision,
        ];
        $attributes['flatRateWikiRelevance'] = [
            'scopeId' => $scopeIds,
        ];

        return $attributes;
    }

    private function readPathEnabled(): bool
    {
        foreach ([
            FeatureGates::CONTEXT_WRITES_ENABLED,
            FeatureGates::BROWSE_ROUTES_ENABLED,
            FeatureGates::DERIVED_FEEDS_ENABLED,
            FeatureGates::BRAND_ROOT_DERIVED_FEEDS_ENABLED,
            FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED,
            FeatureGates::PUBLIC_ROLLOUT_ENABLED,
        ] as $gate) {
            if ($this->settings->bool($gate)) {
                return true;
            }
        }

        return false;
    }
}
