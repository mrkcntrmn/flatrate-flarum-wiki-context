<?php

namespace FlatRate\WikiContext\Preview;

use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use FlatRate\WikiContext\Support\GhostPreviewPolicy;
use RuntimeException;

/**
 * Deterministic disposable semantic fixture catalog + membership evaluator.
 *
 * Proves server/query semantics only. Does not claim browser/component rendering.
 */
final class PreviewFixtureCatalog
{
    public const ALL_DISCUSSIONS = 'all_discussions';
    public const CATCH_ALL_PATH = 'Other / Misc';

    /** @var array<string,mixed> */
    private array $raw;

    /** @var array<string,string> pathKey => scopeId */
    private array $scopeIds = [];

    /** @var array<string,list<string>> scopeId => ancestor scopeIds including self */
    private array $ancestorsIncludingSelf = [];

    /** @var list<array<string,mixed>> */
    private array $discussions = [];

    public function __construct(?array $raw = null)
    {
        $this->raw = $raw ?? $this->loadDefault();
        $this->index();
    }

    public static function fromDefaultFixtureFile(): self
    {
        return new self();
    }

    /**
     * @return list<string>
     */
    public function requiredFixtureNames(): array
    {
        $fromPolicy = GhostPreviewPolicy::contract()['required_fixtures'];
        $fromFile = $this->raw['preview_fixtures'] ?? [];

        if ($fromPolicy !== $fromFile) {
            throw new RuntimeException('preview_fixture_list_mismatch');
        }

        return $fromPolicy;
    }

    public function scopeIdForPath(array $path): string
    {
        $key = $this->pathKey($path);
        if (!isset($this->scopeIds[$key])) {
            throw new RuntimeException('preview_fixture_unknown_scope:' . $key);
        }

        return $this->scopeIds[$key];
    }

    /**
     * Membership mirrors WikiScopeFilter semantics:
     * primary exact OR primary descendant-of-target
     * OR relevance exact OR relevance descendant-of-target.
     *
     * @return list<string> discussion ids visible to the audience under the target
     */
    public function discussionIdsForScope(string $targetScopeId, PreviewAudience $audience): array
    {
        $ids = [];
        foreach ($this->discussions as $discussion) {
            if (($discussion['hidden'] ?? false) && !$audience->canSeeHiddenDiscussions()) {
                continue;
            }

            if ($this->isMember($discussion, $targetScopeId)) {
                $ids[] = $discussion['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    public function allVisibleDiscussionIds(PreviewAudience $audience): array
    {
        $ids = [];
        foreach ($this->discussions as $discussion) {
            if (($discussion['hidden'] ?? false) && !$audience->canSeeHiddenDiscussions()) {
                continue;
            }
            $ids[] = $discussion['id'];
        }

        return $ids;
    }

    public function hiddenDiscussionIds(): array
    {
        $ids = [];
        foreach ($this->discussions as $discussion) {
            if ($discussion['hidden'] ?? false) {
                $ids[] = $discussion['id'];
            }
        }

        return $ids;
    }

    /**
     * Cross-context visibility expectations for labor_law_relevant_toyota.
     *
     * @return array<string,bool>
     */
    public function crossContextExpectations(): array
    {
        return [
            'Labor Law' => true,
            'Texas' => true,
            'Flat-Rate Compensation' => true,
            'Toyota' => true,
            'All Discussions' => true,
            'Camry' => false,
        ];
    }

    public function directoryOverflowContract(): array
    {
        $contract = DirectoryDisplayPolicy::contract();

        return [
            'catch_all_label' => DirectoryDisplayPolicy::CATCH_ALL_LABEL,
            'overflow' => $contract['desktop']['overflow'],
            'catch_all_always_last' => $contract['catch_all']['always_last'],
            'overflow_does_not_use_misc' => $contract['catch_all']['overflow_does_not_use_misc'],
        ];
    }

    private function isMember(array $discussion, string $targetScopeId): bool
    {
        $primaryId = $discussion['primary_scope_id'];
        if ($this->scopeIsOrDescendantOf($primaryId, $targetScopeId)) {
            return true;
        }

        foreach ($discussion['relevance_scope_ids'] as $relevanceId) {
            if ($this->scopeIsOrDescendantOf($relevanceId, $targetScopeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when $scopeId equals target OR target is an ancestor of $scopeId
     * (i.e. $scopeId is exact or a descendant of the target).
     */
    private function scopeIsOrDescendantOf(string $scopeId, string $targetScopeId): bool
    {
        $ancestors = $this->ancestorsIncludingSelf[$scopeId] ?? [];

        return in_array($targetScopeId, $ancestors, true);
    }

    private function index(): void
    {
        foreach ($this->raw['scopes'] as $scope) {
            $path = $scope['path'];
            $id = $this->deterministicScopeId($path);
            $this->scopeIds[$this->pathKey($path)] = $id;

            $ancestorPaths = [];
            for ($i = 1; $i <= count($path); $i++) {
                $ancestorPaths[] = array_slice($path, 0, $i);
            }
            $ancestorIds = [];
            foreach ($ancestorPaths as $ancestorPath) {
                $ancestorIds[] = $this->deterministicScopeId($ancestorPath);
            }
            $this->ancestorsIncludingSelf[$id] = $ancestorIds;
        }

        // Catch-all sentinel scope under Toyota for directory fixtures.
        $catchAllPath = ['Toyota', self::CATCH_ALL_PATH];
        $catchAllId = $this->deterministicScopeId($catchAllPath);
        $this->scopeIds[$this->pathKey($catchAllPath)] = $catchAllId;
        $this->ancestorsIncludingSelf[$catchAllId] = [
            $this->deterministicScopeId(['Toyota']),
            $catchAllId,
        ];

        foreach ($this->raw['discussions'] as $discussion) {
            $primaryPath = $discussion['primary'];
            $relevanceIds = [];
            foreach ($discussion['relevance'] as $relevancePath) {
                $relevanceIds[] = $this->scopeIdForPath($relevancePath);
            }

            $this->discussions[] = [
                'id' => (string) $discussion['id'],
                'primary_scope_id' => $this->scopeIdForPath($primaryPath),
                'relevance_scope_ids' => $relevanceIds,
                'hidden' => (bool) ($discussion['hidden'] ?? false),
            ];
        }
    }

    private function deterministicScopeId(array $path): string
    {
        $hex = substr(hash('sha256', 'flatrate.wiki.preview.scope|' . $this->pathKey($path)), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            '4' . substr($hex, 13, 3),
            '8' . substr($hex, 17, 3),
            substr($hex, 20, 12)
        );
    }

    private function pathKey(array $path): string
    {
        return implode("\0", $path);
    }

    private function loadDefault(): array
    {
        $path = dirname(__DIR__, 2) . '/tests/fixtures/semantic-scopes.json';
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('preview_fixture_file_unreadable');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('preview_fixture_file_invalid');
        }

        return $decoded;
    }
}
