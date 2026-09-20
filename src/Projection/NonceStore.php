<?php

namespace FlatRate\WikiContext\Projection;

use Illuminate\Database\ConnectionInterface;

final class NonceStore
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function seen(string $nonce): bool
    {
        $hash = ProjectionAuth::sha256Hex($nonce);

        return $this->db->table('flatrate_wiki_projection_nonces')
            ->where('nonce_hash', $hash)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->exists();
    }

    public function remember(string $nonce, int $ttlSeconds = ProjectionAuth::NONCE_TTL_SECONDS): void
    {
        $hash = ProjectionAuth::sha256Hex($nonce);
        $now = time();
        $this->db->table('flatrate_wiki_projection_nonces')->insert([
            'nonce_hash' => $hash,
            'created_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $now + $ttlSeconds),
        ]);
    }

    public function cleanup(): int
    {
        return $this->db->table('flatrate_wiki_projection_nonces')
            ->where('expires_at', '<=', date('Y-m-d H:i:s'))
            ->delete();
    }
}
