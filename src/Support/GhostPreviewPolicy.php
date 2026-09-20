<?php

namespace FlatRate\WikiContext\Support;

/**
 * WIKI-001P admin ghost-preview contract (skeleton).
 */
final class GhostPreviewPolicy
{
    public const DEFAULT_AUDIENCE = 'standard_member';

    /** @return list<string> */
    public static function audienceProfiles(): array
    {
        return ['guest', 'standard_member'];
    }

    public static function contract(): array
    {
        return [
            'admin_ghost_preview_enabled_default' => false,
            'public_rollout_enabled_default' => false,
            'same_user_facing_components' => true,
            'separate_preview_ui_implementation' => false,
            'audience_profiles' => self::audienceProfiles(),
            'default_audience' => self::DEFAULT_AUDIENCE,
            'admin_elevated_visibility_for_user_preview' => false,
            'read_only' => true,
            'discussion_create' => false,
            'context_write' => false,
            'relevance_write' => false,
            'projection_activation' => false,
            'moderation_mutation' => false,
            'dry_run_context_validation_allowed' => true,
            'banner' => [
                'title' => 'ADMIN GHOST PREVIEW',
                'not_visible_to_members' => true,
                'read_only' => true,
            ],
            'required_fixtures' => [
                'all_discussions_root',
                'toyota_root',
                'deep_toyota_scope',
                'labor_law_texas_scope',
                'labor_law_relevant_to_toyota',
                'catch_all_scope',
                'overflow_more_view_all',
                'hidden_discussion_visibility',
            ],
        ];
    }
}
