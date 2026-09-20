<?php

namespace FlatRate\WikiContext\Context;

/**
 * Skeleton DTO for future normal Flarum discussion mutations.
 * Server derives actor/provenance/board compatibility; client values are not trusted.
 *
 * Optimistic concurrency: expectedRevision == currentRevision else 409 Conflict.
 */
final class WikiContextDto
{
    public function __construct(
        public readonly ?string $primaryScopeId,
        public readonly ?string $expectedGraphVersionId,
        public readonly ?int $expectedRevision,
        /** @var list<string> */
        public readonly array $relevanceScopeIds = []
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromClientPayload(array $payload): self
    {
        $context = $payload['flatRateWikiContext'] ?? [];
        $relevance = $payload['flatRateWikiRelevance'] ?? [];

        $scopeIds = [];
        if (isset($relevance['scopeId']) && is_array($relevance['scopeId'])) {
            $scopeIds = array_values(array_map('strval', $relevance['scopeId']));
        }

        return new self(
            isset($context['primaryScopeId']) ? (string) $context['primaryScopeId'] : null,
            isset($context['expectedGraphVersionId']) ? (string) $context['expectedGraphVersionId'] : null,
            isset($context['expectedRevision']) ? (int) $context['expectedRevision'] : null,
            $scopeIds
        );
    }

    public function revisionConflict(int $currentRevision): bool
    {
        if ($this->expectedRevision === null) {
            return true;
        }

        return $this->expectedRevision !== $currentRevision;
    }
}
