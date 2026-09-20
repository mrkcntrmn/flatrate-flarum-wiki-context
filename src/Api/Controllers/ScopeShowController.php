<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Repository\ScopeReadRepository;
use FlatRate\WikiContext\Support\BrowseAccessGate;
use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use FlatRate\WikiContext\Support\Uuid;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Public-safe active scope metadata for WIKI-001F browse presentation.
 *
 * Ordinary browse APIs require BOTH browse-routes and public-rollout gates.
 * WIKI-001P ghost preview must use a separately authorized server path.
 */
final class ScopeShowController implements RequestHandlerInterface
{
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

        $rawId = $request->getAttribute('routeParameters')['id']
            ?? $request->getAttribute('id')
            ?? null;
        $scopeUuid = Uuid::normalize(is_string($rawId) ? $rawId : null);

        if ($scopeUuid === null) {
            return $this->notFound('wiki_scope_not_found');
        }

        $scope = $this->scopes->findActive($scopeUuid);
        if ($scope === null) {
            return $this->notFound('wiki_scope_not_found');
        }

        $attributes = $scope;
        unset($attributes['id']);

        return new JsonResponse([
            'data' => [
                'type' => 'flatrate-wiki-scopes',
                'id' => $scope['id'],
                'attributes' => $attributes,
            ],
            'meta' => [
                'breadcrumbs' => $this->scopes->breadcrumbs($scopeUuid),
                'childrenSummary' => [
                    'directActiveCount' => $this->scopes->childCount($scopeUuid),
                ],
                'directoryDisplayPolicy' => DirectoryDisplayPolicy::contract(),
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
