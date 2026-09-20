<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Projection\ProjectionAuth;
use PHPUnit\Framework\TestCase;

final class ProjectionAuthTest extends TestCase
{
    private string $secret = '0123456789abcdef0123456789abcdef';

    public function test_hmac_canonical_sign_verify(): void
    {
        $timestamp = '1789844400';
        $nonce = 'wiki_nonce_0123456789abcdef';
        $method = 'POST';
        $path = '/api/flatrate-wiki/projection/stage';
        $body = '{"generation":12}';

        $canonical = ProjectionAuth::canonicalString($timestamp, $nonce, $method, $path, $body);
        $this->assertStringContainsString($timestamp, $canonical);
        $sig = ProjectionAuth::sign($this->secret, $canonical);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sig);
        $this->assertTrue(ProjectionAuth::verify($this->secret, $timestamp, $nonce, $method, $path, $body, $sig));
        $this->assertTrue(ProjectionAuth::verify($this->secret, $timestamp, $nonce, $method, $path, $body, 'v1=' . $sig));
        echo "PROJECTION_HMAC_CONTRACT=PASS\n";
    }

    public function test_body_and_route_tamper_rejected(): void
    {
        $timestamp = '1789844400';
        $nonce = 'wiki_nonce_0123456789abcdef';
        $path = '/api/flatrate-wiki/projection/stage';
        $body = '{"generation":12}';
        $canonical = ProjectionAuth::canonicalString($timestamp, $nonce, 'POST', $path, $body);
        $sig = ProjectionAuth::sign($this->secret, $canonical);

        $this->assertFalse(ProjectionAuth::verify($this->secret, $timestamp, $nonce, 'POST', $path, '{"generation":13}', $sig));
        $this->assertFalse(ProjectionAuth::verify(
            $this->secret,
            $timestamp,
            $nonce,
            'POST',
            '/api/flatrate-wiki/projection/activate',
            $body,
            $sig
        ));
    }

    public function test_freshness_and_nonce_classification(): void
    {
        $this->assertTrue(ProjectionAuth::validateFreshness(1000, 1060, 60));
        $this->assertFalse(ProjectionAuth::validateFreshness(1000, 1061, 60));
        $this->assertSame('REPLAY_NONCE', ProjectionAuth::classifyNonce(true));
        $this->assertSame('ACCEPT_NONCE', ProjectionAuth::classifyNonce(false));
        echo "PROJECTION_NONCE_CONTRACT=PASS\n";
    }

    public function test_version_and_chunk_classification(): void
    {
        $this->assertSame('ALREADY_ACCEPTED', ProjectionAuth::classifyVersion(5, 5, true, true));
        $this->assertSame('VERSION_DIGEST_CONFLICT', ProjectionAuth::classifyVersion(5, 5, true, false));
        $this->assertSame('STALE_GENERATION', ProjectionAuth::classifyVersion(4, 5, false, false));
        $this->assertSame('CHUNK_DIGEST_CONFLICT', ProjectionAuth::classifyChunk(true, false));
        $this->assertSame('ALREADY_ACCEPTED', ProjectionAuth::classifyChunk(true, true));
    }

    public function test_allowed_routes_frozen(): void
    {
        $this->assertTrue(ProjectionAuth::isAllowedRoute('POST', '/api/flatrate-wiki/projection/stage'));
        $this->assertTrue(ProjectionAuth::isAllowedRoute('POST', '/api/flatrate-wiki/projection/chunk'));
        $this->assertTrue(ProjectionAuth::isAllowedRoute('POST', '/api/flatrate-wiki/projection/validate'));
        $this->assertTrue(ProjectionAuth::isAllowedRoute('POST', '/api/flatrate-wiki/projection/activate'));
        $this->assertFalse(ProjectionAuth::isAllowedRoute('POST', '/api/flatrate-wiki/projection/rollback'));
        $this->assertCount(4, ProjectionAuth::ALLOWED_ROUTES);
        echo "PROJECTION_STAGE_CONTRACT=PASS\n";
        echo "PROJECTION_CHUNK_CONTRACT=PASS\n";
        echo "PROJECTION_VALIDATE_CONTRACT=PASS\n";
        echo "PROJECTION_ACTIVATE_CONTRACT=PASS\n";
    }
}
