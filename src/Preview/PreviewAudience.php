<?php

namespace FlatRate\WikiContext\Preview;

/**
 * Simulated ordinary audience for ghost-preview query semantics.
 *
 * Admin elevated visibility is never used as the preview principal.
 */
final class PreviewAudience
{
    public function __construct(
        public readonly string $profile,
        public readonly bool $elevatedVisibility = false
    ) {
    }

    public function canSeeHiddenDiscussions(): bool
    {
        return false;
    }
}
