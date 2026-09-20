<?php

namespace FlatRate\WikiContext\Moderation;

/**
 * Skeleton: moderator context corrections use optimistic concurrency.
 * No silent last-write-wins.
 */
final class ContextModerationPolicy
{
    public const CONFLICT_HTTP_STATUS = 409;

    public static function allowsSilentLastWriteWins(): bool
    {
        return false;
    }
}
