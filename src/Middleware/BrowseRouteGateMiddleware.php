<?php

namespace FlatRate\WikiContext\Middleware;

use FlatRate\WikiContext\Support\BrowseAccessGate;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fail-closed guard for ordinary /browse routes.
 *
 * WIKI-001P ghost preview is intentionally NOT an exception here. Preview
 * receives a separate server-authorized path later.
 */
final class BrowseRouteGateMiddleware implements MiddlewareInterface
{
    public function __construct(private BrowseAccessGate $browseAccess)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!preg_match('~^/browse/[0-9a-fA-F-]+(?:/[^/]+)?/?$~', $path)) {
            return $handler->handle($request);
        }

        if (!$this->browseAccess->publicBrowseEnabled()) {
            return new JsonResponse([
                'errors' => [['status' => '404', 'code' => 'wiki_browse_closed']],
            ], 404);
        }

        return $handler->handle($request)
            ->withHeader('X-Robots-Tag', 'noindex, follow');
    }
}
