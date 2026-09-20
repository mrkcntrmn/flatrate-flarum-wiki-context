<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Repository\ScopeReadRepository;
use FlatRate\WikiContext\Support\BrowseAccessGate;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bounded batch active-scope metadata resolver for discussion context presentation.
 */
final class ScopeResolveController implements RequestHandlerInterface
{
    public const MAX_IDS = 50;

    public function __construct(
        private BrowseAccessGate $browseAccess,
        private ScopeReadRepository $scopes
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->browseAccess->publicBrowseEnabled()) {
            return $this->notFound('wiki_browse_closed');
        }

        $queryParams = $request->getQueryParams();
        $rawIds = $queryParams['ids'] ?? [];

        if (is_string($rawIds)) {
            $rawIds = array_filter(array_map('trim', explode(',', $rawIds)), static fn ($v) => $v !== '');
        }

        if (!is_array($rawIds)) {
            $rawIds = [];
        }

        $requestedCount = count($rawIds);
        $items = $this->scopes->resolveActive(array_values($rawIds), self::MAX_IDS);

        $data = [];
        foreach ($items as $scope) {
            $attributes = $scope;
            unset($attributes['id']);

            $data[] = [
                'type' => 'flatrate-wiki-scopes',
                'id' => $scope['id'],
                'attributes' => $attributes,
            ];
        }

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'requestedCount' => $requestedCount,
                'returnedCount' => count($data),
                'maxIds' => self::MAX_IDS,
                'activeProjectionOnly' => true,
                'publicSafeFieldsOnly' => true,
                'indexable' => false,
                'inSitemap' => false,
            ],
        ]);
    }

    private function notFound(string $code): ResponseInterface
    {
        return new JsonResponse([
            'errors' => [['status' => '404', 'code' => $code]],
        ], 404);
    }
}
