<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Support\BrowseAccessGate;
use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ScopeChildrenController implements RequestHandlerInterface
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

        return new JsonResponse([
            'data' => [],
            'meta' => [
                'directoryDisplayPolicy' => DirectoryDisplayPolicy::contract(),
                'directChildrenOnly' => true,
                'implementation' => 'skeleton',
            ],
        ]);
    }
}
