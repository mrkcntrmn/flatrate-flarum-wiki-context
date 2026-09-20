<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Public scope metadata skeleton. Fail-closed unless browse/public gates allow.
 */
final class ScopeShowController implements RequestHandlerInterface
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $rollout = (bool) $this->settings->get(FeatureGates::PUBLIC_ROLLOUT_ENABLED);
        $browse = (bool) $this->settings->get(FeatureGates::BROWSE_ROUTES_ENABLED);
        $preview = (bool) $this->settings->get(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED);

        if (!$rollout && !$browse && !$preview) {
            return new JsonResponse([
                'errors' => [['status' => '404', 'code' => 'wiki_browse_closed']],
            ], 404);
        }

        $id = $request->getAttribute('routeParameters')['id']
            ?? $request->getAttribute('id')
            ?? null;

        return new JsonResponse([
            'data' => [
                'type' => 'flatrate-wiki-scopes',
                'id' => $id,
                'attributes' => [
                    'implementation' => 'skeleton',
                    'publicSafeFieldsOnly' => true,
                ],
            ],
        ]);
    }
}
