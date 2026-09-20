<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Projection\ProjectionAuth;
use PHPUnit\Framework\TestCase;

/**
 * Security contract tests for projection HMAC / replay / tamper paths.
 */
final class ProjectionSecurityTest extends TestCase
{
    private string $secret = '0123456789abcdef0123456789abcdef';

    public function test_invalid_hmac_rejected(): void
    {
        $ok = ProjectionAuth::verify(
            $this->secret,
            '1789844400',
            'wiki_nonce_0123456789abcdef',
            'POST',
            '/api/flatrate-wiki/projection/stage',
            '{}',
            'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef'
        );
        $this->assertFalse($ok);
    }

    public function test_stale_timestamp_rejected(): void
    {
        $this->assertFalse(ProjectionAuth::validateFreshness(1000, 2000, 60));
    }

    public function test_oversized_secret_policy_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProjectionAuth::sign('too-short', 'canonical');
    }

    public function test_unsupported_rollback_route_not_allowed(): void
    {
        $this->assertFalse(ProjectionAuth::isAllowedRoute('POST', '/api/flatrate-wiki/projection/rollback'));
    }

    public function test_lower_generation_rejected(): void
    {
        $this->assertSame('STALE_GENERATION', ProjectionAuth::classifyVersion(3, 10, false, false));
    }
}
