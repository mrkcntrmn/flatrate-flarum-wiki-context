<?php

namespace FlatRate\WikiContext\Listener;

use FlatRate\WikiContext\Context\ContextWriteException;
use FlatRate\WikiContext\Context\ContextWriteService;
use FlatRate\WikiContext\Context\WikiContextDto;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Tobyz\JsonApiServer\Exception\ConflictException;

final class SaveDiscussionWikiContext
{
    public function __construct(
        private ContextWriteService $writes,
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(Saving $event): void
    {
        $attributes = (array) ($event->data['attributes'] ?? []);
        $hasContext = array_key_exists('flatRateWikiContext', $attributes);
        $hasRelevance = array_key_exists('flatRateWikiRelevance', $attributes);

        if (!$hasContext && !$hasRelevance) {
            return;
        }

        // WIKI-001D only accepts semantic payload on normal discussion create.
        // Later corrections go through the dedicated context-only endpoint.
        if ($event->discussion->exists) {
            throw new ValidationException([
                'flatRateWikiContext' => 'wiki_context_update_requires_context_endpoint',
            ]);
        }

        if (!(bool) $this->settings->get(FeatureGates::CONTEXT_WRITES_ENABLED)) {
            throw new PermissionDeniedException;
        }

        if (!$hasContext) {
            throw new ValidationException([
                'flatRateWikiContext' => 'primary_context_required',
            ]);
        }

        $dto = WikiContextDto::fromClientPayload($attributes);
        $tagIds = $this->extractRequestedTagIds($event->data);

        $actorId = (int) $event->actor->id;

        $event->discussion->afterSave(function (Discussion $discussion) use ($actorId, $dto, $tagIds) {
            try {
                $this->writes->createInitial(
                    (int) $discussion->id,
                    (int) $discussion->user_id,
                    $actorId,
                    $dto,
                    $tagIds
                );
            } catch (ContextWriteException $e) {
                if ($e->httpStatus === 403) {
                    throw new PermissionDeniedException;
                }

                if ($e->httpStatus === 409) {
                    throw new ConflictException($e->reason);
                }

                throw new ValidationException([
                    'flatRateWikiContext' => $e->reason,
                ]);
            }
        });
    }

    /**
     * @param array<string,mixed> $data
     * @return list<int>
     */
    private function extractRequestedTagIds(array $data): array
    {
        $rows = $data['relationships']['tags']['data'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id']) || !is_numeric($row['id'])) {
                continue;
            }
            $ids[] = (int) $row['id'];
        }

        return array_values(array_unique($ids));
    }
}
