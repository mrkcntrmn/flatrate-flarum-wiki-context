<?php

namespace FlatRate\WikiContext\Middleware;

use FlatRate\WikiContext\Projection\NonceStore;
use FlatRate\WikiContext\Projection\ProjectionAuth;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Path-specific HMAC gate for projection S2S routes only.
 * No broad CSRF exemption beyond named projection routes (see extend.php).
 */
final class ProjectionHmacMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private NonceStore $nonces
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        if (!ProjectionAuth::isAllowedRoute($method, $path)) {
            return $handler->handle($request);
        }

        $secret = getenv('FLATRATE_WIKI_PROJECTION_SECRET') ?: '';
        if ($secret === '') {
            $secret = (string) $this->settings->get('flatrate-wiki.projection_hmac_secret', '');
        }

        $timestamp = $request->getHeaderLine(ProjectionAuth::HEADER_TIMESTAMP);
        $nonce = $request->getHeaderLine(ProjectionAuth::HEADER_NONCE);
        $signature = $request->getHeaderLine(ProjectionAuth::HEADER_SIGNATURE);
        $rawBody = (string) $request->getBody();

        $skew = (int) $this->settings->get('flatrate-wiki.projection_max_clock_skew_seconds', ProjectionAuth::MAX_CLOCK_SKEW_SECONDS);

        if (!ProjectionAuth::validateFreshness((int) $timestamp, time(), $skew)) {
            return new JsonResponse(['errors' => [['code' => 'stale_timestamp']]], 401);
        }

        if (ProjectionAuth::classifyNonce($this->nonces->seen($nonce)) === 'REPLAY_NONCE') {
            return new JsonResponse(['errors' => [['code' => 'duplicate_nonce']]], 401);
        }

        if (!ProjectionAuth::verify($secret, $timestamp, $nonce, $method, $path, $rawBody, $signature)) {
            return new JsonResponse(['errors' => [['code' => 'invalid_hmac']]], 401);
        }

        $this->nonces->remember($nonce);

        return $handler->handle($request);
    }
}
