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

final class ScopeChildrenController implements RequestHandlerInterface
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

        if ($scopeUuid === null || $this->scopes->findActive($scopeUuid) === null) {
            return $this->notFound('wiki_scope_not_found');
        }

        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) && is_array($queryParams['page'])
            ? $queryParams['page']
            : [];

        $offset = isset($page['offset']) && is_numeric($page['offset'])
            ? max(0, (int) $page['offset'])
            : 0;

        $limit = isset($page['limit']) && is_numeric($page['limit'])
            ? (int) $page['limit']
            : DirectoryDisplayPolicy::DESKTOP_INLINE_MAX;

        $query = isset($queryParams['q']) && is_scalar($queryParams['q'])
            ? (string) $queryParams['q']
            : null;

        $result = $this->scopes->normalChildren($scopeUuid, $offset, $limit, $query);
        $catchAll = $this->scopes->catchAllChild($scopeUuid);

        $data = [];
        foreach ($result['items'] as $scope) {
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
                'directoryDisplayPolicy' => DirectoryDisplayPolicy::contract(),
                'directChildrenOnly' => true,
                'totalNormal' => $result['total'],
                'totalWithCatchAll' => $result['total'] + ($catchAll === null ? 0 : 1),
                'catchAll' => $catchAll,
                'offset' => $result['offset'],
                'limit' => $result['limit'],
                'hasMore' => $result['has_more'],
                'query' => $result['query'],
                'searchable' => true,
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
