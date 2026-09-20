<?php

namespace FlatRate\WikiContext\Listener;

use FlatRate\WikiContext\Context\ContextWriteException;
use FlatRate\WikiContext\Context\ContextWriteService;
use FlatRate\WikiContext\Context\WikiContextDto;
use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Tobyz\JsonApiServer\Exception\ConflictException;

final class SaveDiscussionWikiContext
{
    public function __construct(
        private ContextWriteService $writes,
        private SettingsReader $settings
    ) {
    }

    public function handle(Saving $event): void
    {
        $attributes = (array) ($event->data['attributes'] ?? []);
        $hasContext = array_key_exists('flatRateWikiContext', $attributes);
        $hasRelevance = array_key_exists('flatRateWikiRelevance', $attributes);

        $tagsChanged = array_key_exists('tags', (array) ($event->data['relationships'] ?? []));

        if ($event->discussion->exists) {
            if ($hasContext || $hasRelevance) {
                throw new ValidationException([
                    'flatRateWikiContext' => 'wiki_context_update_requires_context_endpoint',
                ]);
            }

            if ($tagsChanged) {
                try {
                    $this->writes->assertExistingBoardCompatible(
                        (int) $event->discussion->id,
                        $this->extractRequestedTagIds($event->data)
                    );
                } catch (ContextWriteException $e) {
                    if ($e->httpStatus === 409) {
                        throw new ConflictException($e->reason);
                    }

                    throw new ValidationException([
                        'tags' => $e->reason,
                    ]);
                }
            }

            return;
        }

        if (!$hasContext && !$hasRelevance) {
            return;
        }

        if (
            !$this->settings->bool(FeatureGates::CONTEXT_WRITES_ENABLED)
            || !$this->settings->bool(FeatureGates::PUBLIC_ROLLOUT_ENABLED)
        ) {
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
