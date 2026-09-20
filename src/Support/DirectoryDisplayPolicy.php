<?php

namespace FlatRate\WikiContext\Support;

/**
 * Consumes accepted directory display policy (WIKI-001B / control repo).
 * Direct children only; no recursive tree render.
 */
final class DirectoryDisplayPolicy
{
    public const DESKTOP_INITIAL = 8;
    public const DESKTOP_INLINE_MAX = 24;
    public const MOBILE_INITIAL = 6;
    public const MOBILE_INLINE_MAX = 18;

    public const CATCH_ALL_LABEL = 'Other / Misc';
    public const MAX_ACTIVE_CATCH_ALL_PER_PARENT = 1;

    public static function contract(): array
    {
        return [
            'direct_children_only' => true,
            'desktop' => [
                'initial' => self::DESKTOP_INITIAL,
                'inline_max' => self::DESKTOP_INLINE_MAX,
                'overflow' => ['more', 'view_all'],
            ],
            'mobile' => [
                'initial' => self::MOBILE_INITIAL,
                'inline_max' => self::MOBILE_INLINE_MAX,
                'overflow' => ['more', 'view_all'],
            ],
            'catch_all' => [
                'label' => self::CATCH_ALL_LABEL,
                'max_active_per_parent' => self::MAX_ACTIVE_CATCH_ALL_PER_PARENT,
                'same_parent_context' => true,
                'same_owning_board' => true,
                'terminal' => true,
                'child_scopes' => false,
                'always_last' => true,
                'overflow_does_not_use_misc' => true,
            ],
        ];
    }
}
