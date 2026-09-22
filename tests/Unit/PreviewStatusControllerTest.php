<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Api\Controllers\PreviewStatusController;
use FlatRate\WikiContext\Preview\PreviewAuthorization;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class PreviewStatusControllerTest extends TestCase
{
    public function test_guest_and_member_are_denied_server_side(): void
    {
        foreach ([
            $this->actor(null, false),
            $this->actor(42, false),
        ] as $actor) {
            $response = $this->controller(true, false, false)->handle(
                $this->request($actor)
            );

            $this->assertSame(403, $response->getStatusCode());
            $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            $this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        }

        echo "WIKI001P1A_NON_ADMIN_PREVIEW_DENIED=PASS\n";
    }

    public function test_admin_fails_closed_when_preview_gate_is_off(): void
    {
        $response = $this->controller(false, false, false)->handle(
            $this->request($this->actor(1, true))
        );

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('wiki_preview_closed', $body['errors'][0]['code']);
    }

    public function test_admin_status_is_read_only_and_does_not_open_public_browse(): void
    {
        $response = $this->controller(true, false, false)->handle(
            $this->request($this->actor(1, true))
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertTrue($body['ok']);
        $this->assertSame('admin_ghost_preview', $body['mode']);
        $this->assertTrue($body['read_only']);
        $this->assertSame(['guest', 'standard_member'], $body['audience_profiles']);
        $this->assertSame('standard_member', $body['default_audience']);
        $this->assertFalse($body['admin_elevated_visibility_for_user_preview']);
        $this->assertFalse($body['public_rollout_enabled']);
        $this->assertFalse($body['browse_routes_enabled']);
        $this->assertFalse($body['mutations']['discussion_create']);
        $this->assertFalse($body['mutations']['context_write']);
        $this->assertFalse($body['mutations']['relevance_write']);
        $this->assertFalse($body['mutations']['projection_activation']);
        $this->assertFalse($body['mutations']['moderation_mutation']);
        $this->assertSame('not_evaluated_in_p1a', $body['acceptance_receipt']['state']);
        $this->assertFalse($body['acceptance_receipt']['creation_supported']);
        $this->assertFalse($body['production_mutation']);

        echo "WIKI001P1A_ADMIN_STATUS_READ_ONLY=PASS\n";
        echo "WIKI001P1A_PUBLIC_BROWSE_REMAINS_CLOSED=PASS\n";
    }

    private function controller(bool $preview, bool $public, bool $browse): PreviewStatusController
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $values = [
            FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED => $preview ? '1' : '0',
            FeatureGates::PUBLIC_ROLLOUT_ENABLED => $public ? '1' : '0',
            FeatureGates::BROWSE_ROUTES_ENABLED => $browse ? '1' : '0',
        ];
        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $values[$key] ?? $default
        );

        return new PreviewStatusController($settings, new PreviewAuthorization());
    }

    private function actor(?int $id, bool $admin): User
    {
        $actor = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isAdmin'])
            ->getMock();
        $actor->id = $id;
        $actor->method('isAdmin')->willReturn($admin);

        return $actor;
    }

    private function request(User $actor)
    {
        $session = new class ($actor) {
            public function __construct(private User $actor)
            {
            }

            public function getActor(): User
            {
                return $this->actor;
            }
        };

        return (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/flatrate-wiki/preview/status')
            ->withAttribute('session', $session);
    }
}
