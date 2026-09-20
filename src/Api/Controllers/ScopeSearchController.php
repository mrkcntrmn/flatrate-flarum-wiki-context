<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Context\ContextWritePolicy;
use FlatRate\WikiContext\Repository\ScopeReadRepository;
use FlatRate\WikiContext\Support\BrowseAccessGate;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bounded active-scope search for relevance picking.
 * Blank queries never dump the graph.
 */
final class ScopeSearchController implements RequestHandlerInterface
{
    public const MAX_RESULTS = 20;
    public const MAX_QUERY_LENGTH = 64;

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
        $rawQuery = isset($queryParams['q']) && is_scalar($queryParams['q'])
            ? (string) $queryParams['q']
            : '';

        $query = trim($rawQuery);
        $query = function_exists('mb_substr')
            ? mb_substr($query, 0, self::MAX_QUERY_LENGTH)
            : substr($query, 0, self::MAX_QUERY_LENGTH);

        $page = isset($queryParams['page']) && is_array($queryParams['page'])
            ? $queryParams['page']
            : [];
        $limit = isset($page['limit']) && is_numeric($page['limit'])
            ? (int) $page['limit']
            : self::MAX_RESULTS;
        $limit = max(1, min(self::MAX_RESULTS, $limit));

        $items = $this->scopes->searchActiveDiscussionCapable($query, $limit);

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
                'query' => $query,
                'limit' => $limit,
                'activeProjectionOnly' => true,
                'discussionCapableOnly' => true,
                'publicSafeFieldsOnly' => true,
                'maxQueryLength' => self::MAX_QUERY_LENGTH,
                'maxResults' => self::MAX_RESULTS,
                'relevanceMaxActive' => ContextWritePolicy::MAX_ACTIVE_RELEVANCE,
                'blankQueryDumpsGraph' => false,
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
