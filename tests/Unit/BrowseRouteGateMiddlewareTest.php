<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Middleware\BrowseRouteGateMiddleware;
use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\BrowseAccessGate;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BrowseRouteGateMiddlewareTest extends TestCase
{
    public function test_non_browse_route_passes_through(): void
    {
        $middleware = $this->middleware(false, false, true);
        $response = $middleware->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/d/1-test'),
            $this->okHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_browse_route_fails_closed_without_both_public_gates(): void
    {
        foreach ([
            [false, false, false],
            [true, false, false],
            [false, true, false],
            [true, false, true],
        ] as [$browse, $public, $preview]) {
            $middleware = $this->middleware($browse, $public, $preview);
            $response = $middleware->process(
                (new ServerRequestFactory())->createServerRequest(
                    'GET',
                    '/browse/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/toyota'
                ),
                $this->okHandler()
            );

            $this->assertSame(404, $response->getStatusCode());
        }

        echo "WIKI001F_PUBLIC_ROUTE_GATE_FAIL_CLOSED=PASS\n";
        echo "WIKI001F_GHOST_PREVIEW_NOT_PUBLIC_BYPASS=PASS\n";
    }

    public function test_open_public_browse_is_noindex_until_seo_tranche(): void
    {
        $middleware = $this->middleware(true, true, false);
        $response = $middleware->process(
            (new ServerRequestFactory())->createServerRequest(
                'GET',
                '/browse/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/toyota'
            ),
            $this->okHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('noindex, follow', $response->getHeaderLine('X-Robots-Tag'));

        echo "WIKI001F_INDEXABILITY_DEFAULT_FALSE=PASS\n";
    }

    private function middleware(bool $browse, bool $public, bool $preview): BrowseRouteGateMiddleware
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $values = [
            FeatureGates::BROWSE_ROUTES_ENABLED => $browse ? '1' : '0',
            FeatureGates::PUBLIC_ROLLOUT_ENABLED => $public ? '1' : '0',
            FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED => $preview ? '1' : '0',
        ];

        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $values[$key] ?? $default
        );

        return new BrowseRouteGateMiddleware(
            new BrowseAccessGate(new SettingsReader($settings))
        );
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['ok' => true], 200);
            }
        };
    }
}
