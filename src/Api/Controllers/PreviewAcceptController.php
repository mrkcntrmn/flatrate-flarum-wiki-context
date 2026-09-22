<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Preview\PreviewAcceptanceService;
use FlatRate\WikiContext\Preview\PreviewAuthorization;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * POST /api/flatrate-wiki/preview/accept
 *
 * Admin-only acceptance surface. Runs the shared PreviewAcceptanceService.
 * Does not open public browse routes or mutate projection/graph state beyond
 * persisting one PASS receipt after total fixture success.
 */
final class PreviewAcceptController implements RequestHandlerInterface
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private PreviewAuthorization $auth,
        private PreviewAcceptanceService $acceptance
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

        try {
            $result = $this->acceptance->accept($actor);
        } catch (PermissionDeniedException $e) {
            return new JsonResponse([
                'errors' => [['status' => '403', 'code' => 'permission_denied']],
            ], 403, $headers);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = $code === 'wiki_preview_closed' ? 404 : 422;

            return new JsonResponse([
                'errors' => [['status' => (string) $status, 'code' => $code]],
            ], $status, $headers);
        }

        return new JsonResponse($result, 200, $headers);
    }

    private function enabled(string $key): bool
    {
        return filter_var(
            $this->settings->get($key, false),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
