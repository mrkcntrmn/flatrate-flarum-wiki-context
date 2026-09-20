<?php

namespace FlatRate\WikiContext\Api\Serializers;

use Flarum\Api\Serializer\BasicDiscussionSerializer;
use Flarum\Discussion\Discussion;

/**
 * Public-safe discussion context attributes only.
 * Never expose private source IDs, VIN, email, phone, Garage, audit, secrets.
 */
class DiscussionWikiContextAttributes
{
    public function __invoke(BasicDiscussionSerializer $serializer, Discussion $discussion, array $attributes): array
    {
        // Skeleton: real hydration deferred until projection + context writes land.
        $attributes['flatRateWikiContext'] = null;
        $attributes['flatRateWikiRelevance'] = [];

        return $attributes;
    }
}
