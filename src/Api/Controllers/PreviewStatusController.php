<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Preview\PreviewAuthorization;
use FlatRate\WikiContext\Support\FeatureGates;
use FlatRate\WikiContext\Support\GhostPreviewPolicy;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/flatrate-wiki/preview/status
 *
 * Admin-only, read-only status surface for WIKI-001P. This endpoint does not
 * render browse content, mutate projection state, create acceptance receipts,
 * or make ordinary /browse routes public.
 */
final class PreviewStatusController implements RequestHandlerInterface
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private PreviewAuthorization $auth
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $headers = [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        $actor = RequestUtil::getActor($request);

        try {
            $this->auth->assertAdmin($actor);
        } catch (PermissionDeniedException $e) {
            return new JsonResponse([
                'errors' => [['status' => '403', 'code' => 'permission_denied']],
            ], 403, $headers);
        }

        if (!$this->enabled(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED)) {
            return new JsonResponse([
                'errors' => [['status' => '404', 'code' => 'wiki_preview_closed']],
            ], 404, $headers);
        }

        return new JsonResponse([
            'ok' => true,
            'mode' => 'admin_ghost_preview',
            'read_only' => true,
            'audience_profiles' => GhostPreviewPolicy::audienceProfiles(),
            'default_audience' => GhostPreviewPolicy::DEFAULT_AUDIENCE,
            'admin_elevated_visibility_for_user_preview' => false,
            'public_rollout_enabled' => $this->enabled(FeatureGates::PUBLIC_ROLLOUT_ENABLED),
            'browse_routes_enabled' => $this->enabled(FeatureGates::BROWSE_ROUTES_ENABLED),
            'mutations' => [
                'discussion_create' => false,
                'context_write' => false,
                'relevance_write' => false,
                'projection_activation' => false,
                'moderation_mutation' => false,
            ],
            'acceptance_receipt' => [
                'state' => 'not_evaluated_in_p1a',
                'creation_supported' => false,
            ],
            'production_mutation' => false,
        ], 200, $headers);
    }

    private function enabled(string $key): bool
    {
        return filter_var(
            $this->settings->get($key, false),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
