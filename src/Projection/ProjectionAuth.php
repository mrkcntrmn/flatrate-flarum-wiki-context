<?php

namespace FlatRate\WikiContext\Projection;

/**
 * HMAC-SHA256 projection sync protocol v2 transport helpers.
 * Mirror of control-repo src/wiki/projection-sync.mjs contracts.
 */
final class ProjectionAuth
{
    public const MAX_CLOCK_SKEW_SECONDS = 60;
    public const NONCE_TTL_SECONDS = 120;
    public const MIN_SECRET_BYTES = 32;

    public const HEADER_TIMESTAMP = 'X-FlatRate-Timestamp';
    public const HEADER_NONCE = 'X-FlatRate-Nonce';
    public const HEADER_SIGNATURE = 'X-FlatRate-Signature';

    /** @var list<array{method:string,path:string}> */
    public const ALLOWED_ROUTES = [
        ['method' => 'POST', 'path' => '/api/flatrate-wiki/projection/stage'],
        ['method' => 'POST', 'path' => '/api/flatrate-wiki/projection/chunk'],
        ['method' => 'POST', 'path' => '/api/flatrate-wiki/projection/validate'],
        ['method' => 'POST', 'path' => '/api/flatrate-wiki/projection/activate'],
    ];

    public static function sha256Hex(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function canonicalString(
        string $timestamp,
        string $nonce,
        string $method,
        string $path,
        string $rawBody
    ): string {
        if (!preg_match('/^\d{10}$/', $timestamp)) {
            throw new \InvalidArgumentException('invalid_timestamp');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{22,128}$/', $nonce)) {
            throw new \InvalidArgumentException('invalid_nonce');
        }
        if ($method === '' || $path === '' || $path[0] !== '/') {
            throw new \InvalidArgumentException('invalid_method_or_path');
        }

        return implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            self::sha256Hex($rawBody),
        ]);
    }

    public static function sign(string $secret, string $canonical): string
    {
        if (strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new \InvalidArgumentException('invalid_secret');
        }

        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function normalizeSignature(?string $signature): ?string
    {
        if ($signature === null) {
            return null;
        }
        $value = str_starts_with($signature, 'v1=') ? substr($signature, 3) : $signature;
        if (!preg_match('/^[a-f0-9]{64}$/i', $value)) {
            return null;
        }

        return strtolower($value);
    }

    public static function verify(
        string $secret,
        string $timestamp,
        string $nonce,
        string $method,
        string $path,
        string $rawBody,
        ?string $signature
    ): bool {
        $normalized = self::normalizeSignature($signature);
        if ($normalized === null) {
            return false;
        }

        try {
            $canonical = self::canonicalString($timestamp, $nonce, $method, $path, $rawBody);
            $expected = self::sign($secret, $canonical);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return hash_equals($expected, $normalized);
    }

    public static function validateFreshness(int $timestamp, int $nowSeconds, int $maxSkew = self::MAX_CLOCK_SKEW_SECONDS): bool
    {
        return abs($nowSeconds - $timestamp) <= $maxSkew;
    }

    public static function isAllowedRoute(string $method, string $path): bool
    {
        $method = strtoupper($method);
        foreach (self::ALLOWED_ROUTES as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                return true;
            }
        }

        return false;
    }

    public static function classifyVersion(int $incomingGeneration, int $activeGeneration, bool $sameVersion, bool $sameDigest): string
    {
        if ($sameVersion && $sameDigest) {
            return 'ALREADY_ACCEPTED';
        }
        if ($sameVersion && !$sameDigest) {
            return 'VERSION_DIGEST_CONFLICT';
        }
        if ($incomingGeneration < $activeGeneration) {
            return 'STALE_GENERATION';
        }

        return 'ACCEPT_CANDIDATE';
    }

    public static function classifyChunk(bool $chunkExists, bool $sameDigest): string
    {
        if (!$chunkExists) {
            return 'ACCEPT_CHUNK';
        }

        return $sameDigest ? 'ALREADY_ACCEPTED' : 'CHUNK_DIGEST_CONFLICT';
    }

    public static function classifyNonce(bool $nonceSeen): string
    {
        return $nonceSeen ? 'REPLAY_NONCE' : 'ACCEPT_NONCE';
    }
}
