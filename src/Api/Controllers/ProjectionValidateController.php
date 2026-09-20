<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Projection\ProjectionService;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/flatrate-wiki/projection/validate — validate only; never activates.
 */
final class ProjectionValidateController implements RequestHandlerInterface
{
    public function __construct(
        private ProjectionService $projection,
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->settings->get(FeatureGates::PROJECTION_SYNC_ENABLED)) {
            return new JsonResponse([
                'errors' => [['status' => '403', 'code' => 'projection_sync_disabled']],
            ], 403);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        try {
            $result = $this->projection->validate($body);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'errors' => [['status' => '403', 'code' => $e->getMessage()]],
            ], 403);
        }

        $status = Arr::get($result, 'status') === 'reject' ? 409 : 200;

        return new JsonResponse($result, $status);
    }
}
