<?php

namespace FlatRate\WikiContext\Context;

use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Repository\ActiveScopeRepository;
use FlatRate\WikiContext\Repository\DiscussionContextRepository;
use FlatRate\WikiContext\Support\FeatureGates;
use FlatRate\WikiContext\Support\Uuid;
use Illuminate\Database\ConnectionInterface;

final class ContextWriteService
{
    public function __construct(
        private ConnectionInterface $db,
        private SettingsReader $settings,
        private ActiveScopeRepository $scopes,
        private DiscussionContextRepository $contexts
    ) {
    }

    /**
     * Initial assignment. Intended to execute from Discussion::afterSave while
     * Flarum's discussion-create transaction is still open.
     *
     * @param list<int> $requestedTagIds
     * @return array<string,mixed>
     */
    public function createInitial(
        int $discussionId,
        int $discussionOwnerId,
        int $actorId,
        WikiContextDto $dto,
        array $requestedTagIds
    ): array {
        $this->assertWriteGate();

        if ($this->contexts->find($discussionId) !== null) {
            throw new ContextWriteException('context_already_exists', 409);
        }

        $boardSlugs = $this->contexts->requestedPrimaryBoardSlugs($requestedTagIds);
        $validated = $this->validateDesiredState($dto, $boardSlugs);
        $provenance = ContextWritePolicy::provenanceFor($discussionOwnerId, $actorId);
        $now = date('Y-m-d H:i:s');

        $this->db->table('flatrate_wiki_discussion_context')->insert([
            'discussion_id' => $discussionId,
            'primary_scope_uuid' => $validated->primaryScopeUuid,
            'context_revision' => 1,
            'provenance' => $provenance,
            'assigned_by_user_id' => $actorId ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($validated->relevanceScopeUuids as $scopeUuid) {
            $this->db->table('flatrate_wiki_discussion_relevance')->insert([
                'discussion_id' => $discussionId,
                'relevant_scope_uuid' => $scopeUuid,
                'provenance' => $provenance,
                'actor_id' => $actorId ?: null,
                'created_at' => $now,
                'removed_at' => null,
            ]);

            $this->audit(
                $discussionId,
                $actorId,
                'relevance_added',
                null,
                $validated->primaryScopeUuid,
                $scopeUuid,
                null,
                1,
                $provenance,
                $validated->graphVersionUuid
            );
        }

        $this->audit(
            $discussionId,
            $actorId,
            'primary_assigned',
            null,
            $validated->primaryScopeUuid,
            null,
            null,
            1,
            $provenance,
            $validated->graphVersionUuid
        );

        return $this->state($discussionId);
    }

    /**
     * Protect semantic/Flarum board alignment when ordinary tag edits occur.
     *
     * Legacy discussions without semantic context remain unaffected. Cross-board
     * moves require a future coordinated operation that updates both authorities.
     *
     * @param list<int> $requestedTagIds
     */
    public function assertExistingBoardCompatible(int $discussionId, array $requestedTagIds): void
    {
        $current = $this->contexts->find($discussionId);
        if ($current === null) {
            return;
        }

        $currentPrimary = $this->scopes->find((string) $current->primary_scope_uuid);
        if ($currentPrimary === null) {
            throw new ContextWriteException('current_primary_scope_not_active', 409);
        }

        $boardSlugs = $this->contexts->requestedPrimaryBoardSlugs($requestedTagIds);
        $requiredBoard = (string) ($currentPrimary->owning_board_key ?? '');

        if ($requiredBoard === '' || !in_array($requiredBoard, $boardSlugs, true)) {
            throw new ContextWriteException('board_context_change_requires_coordinated_move', 409);
        }
    }

    /**
     * Full-state same-board correction with optimistic concurrency.
     *
     * @return array<string,mixed>
     */
    public function correct(
        int $discussionId,
        int $discussionOwnerId,
        int $actorId,
        WikiContextDto $dto
    ): array {
        $this->assertWriteGate();

        return $this->db->transaction(function () use ($discussionId, $discussionOwnerId, $actorId, $dto) {
            $current = $this->contexts->find($discussionId, true);

            if ($current === null) {
                throw new ContextWriteException('context_not_found', 404);
            }

            $currentRevision = (int) $current->context_revision;
            if ($dto->revisionConflict($currentRevision)) {
                throw new ContextWriteException('context_revision_conflict', 409);
            }

            $currentPrimary = $this->scopes->find((string) $current->primary_scope_uuid);
            if ($currentPrimary === null) {
                throw new ContextWriteException('current_primary_scope_not_active', 409);
            }

            $boardSlugs = $this->contexts->currentPrimaryBoardSlugs($discussionId);
            $validated = $this->validateDesiredState(
                $dto,
                $boardSlugs,
                (string) $currentPrimary->owning_board_key
            );

            $currentRelevanceRows = $this->contexts->activeRelevance($discussionId);
            $currentRelevance = array_values(array_map(
                fn ($row) => strtolower((string) $row->relevant_scope_uuid),
                $currentRelevanceRows
            ));
            sort($currentRelevance);

            $desiredRelevance = $validated->relevanceScopeUuids;
            sort($desiredRelevance);

            $primaryChanged = strtolower((string) $current->primary_scope_uuid) !== $validated->primaryScopeUuid;
            $relevanceChanged = $currentRelevance !== $desiredRelevance;

            if (!$primaryChanged && !$relevanceChanged) {
                return $this->state($discussionId);
            }

            $newRevision = $currentRevision + 1;
            $provenance = ContextWritePolicy::provenanceFor($discussionOwnerId, $actorId);
            $now = date('Y-m-d H:i:s');

            $updated = $this->db->table('flatrate_wiki_discussion_context')
                ->where('discussion_id', $discussionId)
                ->where('context_revision', $currentRevision)
                ->update([
                    'primary_scope_uuid' => $validated->primaryScopeUuid,
                    'context_revision' => $newRevision,
                    'provenance' => $provenance,
                    'assigned_by_user_id' => $actorId ?: null,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new ContextWriteException('context_revision_conflict', 409);
            }

            $toRemove = array_values(array_diff($currentRelevance, $desiredRelevance));
            $toAdd = array_values(array_diff($desiredRelevance, $currentRelevance));

            foreach ($toRemove as $scopeUuid) {
                $this->db->table('flatrate_wiki_discussion_relevance')
                    ->where('discussion_id', $discussionId)
                    ->where('relevant_scope_uuid', $scopeUuid)
                    ->whereNull('removed_at')
                    ->update(['removed_at' => $now]);

                $this->audit(
                    $discussionId,
                    $actorId,
                    'relevance_removed',
                    (string) $current->primary_scope_uuid,
                    $validated->primaryScopeUuid,
                    $scopeUuid,
                    $currentRevision,
                    $newRevision,
                    $provenance,
                    $validated->graphVersionUuid
                );
            }

            foreach ($toAdd as $scopeUuid) {
                $existing = $this->db->table('flatrate_wiki_discussion_relevance')
                    ->where('discussion_id', $discussionId)
                    ->where('relevant_scope_uuid', $scopeUuid)
                    ->first();

                if ($existing) {
                    $this->db->table('flatrate_wiki_discussion_relevance')
                        ->where('discussion_id', $discussionId)
                        ->where('relevant_scope_uuid', $scopeUuid)
                        ->update([
                            'provenance' => $provenance,
                            'actor_id' => $actorId ?: null,
                            'created_at' => $now,
                            'removed_at' => null,
                        ]);
                } else {
                    $this->db->table('flatrate_wiki_discussion_relevance')->insert([
                        'discussion_id' => $discussionId,
                        'relevant_scope_uuid' => $scopeUuid,
                        'provenance' => $provenance,
                        'actor_id' => $actorId ?: null,
                        'created_at' => $now,
                        'removed_at' => null,
                    ]);
                }

                $this->audit(
                    $discussionId,
                    $actorId,
                    'relevance_added',
                    (string) $current->primary_scope_uuid,
                    $validated->primaryScopeUuid,
                    $scopeUuid,
                    $currentRevision,
                    $newRevision,
                    $provenance,
                    $validated->graphVersionUuid
                );
            }

            if ($primaryChanged) {
                $this->audit(
                    $discussionId,
                    $actorId,
                    'primary_corrected',
                    (string) $current->primary_scope_uuid,
                    $validated->primaryScopeUuid,
                    null,
                    $currentRevision,
                    $newRevision,
                    $provenance,
                    $validated->graphVersionUuid
                );
            }

            return $this->state($discussionId);
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function state(int $discussionId): array
    {
        $context = $this->contexts->find($discussionId);
        if ($context === null) {
            return [
                'primaryScopeId' => null,
                'contextRevision' => null,
                'relevanceScopeIds' => [],
            ];
        }

        $relevance = array_values(array_map(
            fn ($row) => strtolower((string) $row->relevant_scope_uuid),
            $this->contexts->activeRelevance($discussionId)
        ));
        sort($relevance);

        return [
            'primaryScopeId' => strtolower((string) $context->primary_scope_uuid),
            'contextRevision' => (int) $context->context_revision,
            'relevanceScopeIds' => $relevance,
        ];
    }

    /**
     * @param list<string> $boardSlugs
     */
    private function validateDesiredState(
        WikiContextDto $dto,
        array $boardSlugs,
        ?string $requiredBoardKey = null
    ): ValidatedContextWrite {
        $primaryScopeUuid = Uuid::normalize($dto->primaryScopeId);
        if ($primaryScopeUuid === null) {
            throw new ContextWriteException('invalid_primary_scope');
        }

        $expectedGraphVersionUuid = Uuid::normalize($dto->expectedGraphVersionId);
        if ($expectedGraphVersionUuid === null) {
            throw new ContextWriteException('expected_graph_version_required');
        }

        $primary = $this->scopes->find($primaryScopeUuid);
        if ($primary === null) {
            throw new ContextWriteException('primary_scope_not_active');
        }

        if (!(bool) $primary->discussion_capable) {
            throw new ContextWriteException('primary_scope_not_discussion_capable');
        }

        $primaryBoard = (string) ($primary->owning_board_key ?? '');
        if ($primaryBoard === '') {
            throw new ContextWriteException('primary_scope_missing_board');
        }

        if (strtolower((string) $primary->graph_version_uuid) !== $expectedGraphVersionUuid) {
            throw new ContextWriteException('expected_graph_version_stale', 409);
        }

        if ($requiredBoardKey !== null && $primaryBoard !== $requiredBoardKey) {
            throw new ContextWriteException('cross_board_primary_move_not_allowed');
        }

        if (!in_array($primaryBoard, $boardSlugs, true)) {
            throw new ContextWriteException('board_scope_mismatch');
        }

        if (count($dto->relevanceScopeIds) > ContextWritePolicy::MAX_ACTIVE_RELEVANCE) {
            throw new ContextWriteException('relevance_limit_exceeded');
        }

        $normalizedRelevance = [];
        foreach ($dto->relevanceScopeIds as $rawScopeUuid) {
            $scopeUuid = Uuid::normalize($rawScopeUuid);
            if ($scopeUuid === null) {
                throw new ContextWriteException('invalid_relevance_scope');
            }
            if ($scopeUuid === $primaryScopeUuid) {
                throw new ContextWriteException('primary_scope_cannot_be_relevance');
            }
            if (in_array($scopeUuid, $normalizedRelevance, true)) {
                throw new ContextWriteException('duplicate_relevance_scope');
            }

            $scope = $this->scopes->find($scopeUuid);
            if ($scope === null) {
                throw new ContextWriteException('relevance_scope_not_active');
            }
            if (!(bool) $scope->discussion_capable) {
                throw new ContextWriteException('relevance_scope_not_discussion_capable');
            }
            if (strtolower((string) $scope->graph_version_uuid) !== $expectedGraphVersionUuid) {
                throw new ContextWriteException('relevance_graph_version_mismatch', 409);
            }
            if (strtolower((string) $scope->community_uuid) !== strtolower((string) $primary->community_uuid)) {
                throw new ContextWriteException('relevance_community_mismatch');
            }

            $normalizedRelevance[] = $scopeUuid;
        }

        sort($normalizedRelevance);

        return new ValidatedContextWrite(
            $primaryScopeUuid,
            $expectedGraphVersionUuid,
            strtolower((string) $primary->community_uuid),
            $primaryBoard,
            $normalizedRelevance
        );
    }

    private function assertWriteGate(): void
    {
        if (!$this->settings->bool(FeatureGates::CONTEXT_WRITES_ENABLED)) {
            throw new ContextWriteException('context_writes_disabled', 403);
        }
    }

    private function audit(
        int $discussionId,
        int $actorId,
        string $action,
        ?string $priorPrimary,
        ?string $newPrimary,
        ?string $relevantScope,
        ?int $priorRevision,
        ?int $newRevision,
        string $provenance,
        string $graphVersionUuid
    ): void {
        $this->db->table('flatrate_wiki_discussion_context_audit')->insert([
            'discussion_id' => $discussionId,
            'actor_id' => $actorId ?: null,
            'action' => $action,
            'prior_primary_scope_uuid' => $priorPrimary,
            'new_primary_scope_uuid' => $newPrimary,
            'relevant_scope_uuid' => $relevantScope,
            'prior_revision' => $priorRevision,
            'new_revision' => $newRevision,
            'provenance' => $provenance,
            'graph_version_uuid' => $graphVersionUuid,
            'reason' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
