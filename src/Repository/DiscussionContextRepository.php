<?php

namespace FlatRate\WikiContext\Repository;

use Illuminate\Database\ConnectionInterface;

final class DiscussionContextRepository
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(int $discussionId, bool $forUpdate = false): ?object
    {
        $query = $this->db->table('flatrate_wiki_discussion_context')
            ->where('discussion_id', $discussionId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return list<object> */
    public function activeRelevance(int $discussionId): array
    {
        return $this->db->table('flatrate_wiki_discussion_relevance')
            ->where('discussion_id', $discussionId)
            ->whereNull('removed_at')
            ->orderBy('relevant_scope_uuid')
            ->get()
            ->all();
    }

    /** @return list<object> */
    public function allRelevance(int $discussionId): array
    {
        return $this->db->table('flatrate_wiki_discussion_relevance')
            ->where('discussion_id', $discussionId)
            ->get()
            ->all();
    }

    /** @return list<string> */
    public function currentPrimaryBoardSlugs(int $discussionId): array
    {
        return $this->db->table('discussion_tag as dt')
            ->join('tags as t', 't.id', '=', 'dt.tag_id')
            ->where('dt.discussion_id', $discussionId)
            ->whereNotNull('t.position')
            ->whereNull('t.parent_id')
            ->orderBy('t.id')
            ->pluck('t.slug')
            ->map(fn ($slug) => (string) $slug)
            ->all();
    }

    /**
     * @param list<int> $tagIds
     * @return list<string>
     */
    public function requestedPrimaryBoardSlugs(array $tagIds): array
    {
        if (!$tagIds) {
            return [];
        }

        return $this->db->table('tags')
            ->whereIn('id', $tagIds)
            ->whereNotNull('position')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->all();
    }
}
