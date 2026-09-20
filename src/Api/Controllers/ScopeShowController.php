<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Support\BrowseAccessGate;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Public scope metadata skeleton.
 *
 * Ordinary browse APIs require BOTH browse-routes and public-rollout gates.
 * WIKI-001P ghost preview must use a separately authorized server path.
 */
final class ScopeShowController implements RequestHandlerInterface
{
    public function __construct(private BrowseAccessGate $browseAccess)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->browseAccess->publicBrowseEnabled()) {
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
