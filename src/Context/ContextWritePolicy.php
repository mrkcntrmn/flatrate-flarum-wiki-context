<?php

namespace FlatRate\WikiContext\Context;

final class ContextWritePolicy
{
    // Canonical product policy is frozen at 5 active author-selected relevance edges.
    public const MAX_ACTIVE_RELEVANCE = 5;

    public const AUTHOR_SELECTED = 'AUTHOR_SELECTED';
    public const MODERATOR_ASSIGNED = 'MODERATOR_ASSIGNED';

    public static function provenanceFor(int $discussionOwnerId, int $actorId): string
    {
        return $discussionOwnerId === $actorId
            ? self::AUTHOR_SELECTED
            : self::MODERATOR_ASSIGNED;
    }
}
