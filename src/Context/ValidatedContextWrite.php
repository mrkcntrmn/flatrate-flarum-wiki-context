<?php

namespace FlatRate\WikiContext\Context;

/**
 * Server-validated semantic state. Client-supplied actor/provenance are never carried here.
 */
final class ValidatedContextWrite
{
    /**
     * @param list<string> $relevanceScopeUuids
     */
    public function __construct(
        public readonly string $primaryScopeUuid,
        public readonly string $graphVersionUuid,
        public readonly string $communityUuid,
        public readonly string $owningBoardKey,
        public readonly array $relevanceScopeUuids
    ) {
    }
}
