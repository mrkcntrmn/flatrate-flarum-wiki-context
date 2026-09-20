<?php

namespace FlatRate\WikiContext\Api;

use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;

/**
 * Structured create-only input fields.
 *
 * Values are intentionally not assigned to Discussion model properties.
 * Discussion\Event\Saving consumes the raw JSON:API data and schedules the
 * semantic write inside Flarum's discussion-create transaction.
 */
final class DiscussionWikiContextFields
{
    public function __invoke(): array
    {
        return [
            Schema\Arr::make('flatRateWikiContext')
                ->writableOnCreate()
                ->visible(false)
                ->set(fn (Discussion $discussion, array $value) => null),
            Schema\Arr::make('flatRateWikiRelevance')
                ->writableOnCreate()
                ->visible(false)
                ->set(fn (Discussion $discussion, array $value) => null),
        ];
    }
}
