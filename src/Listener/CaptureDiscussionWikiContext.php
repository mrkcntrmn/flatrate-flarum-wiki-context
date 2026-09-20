<?php

namespace FlatRate\WikiContext\Listener;

use FlatRate\WikiContext\Context\ContextWriteException;
use FlatRate\WikiContext\Context\ContextWriteService;
use FlatRate\WikiContext\Context\PendingContextWrite;
use FlatRate\WikiContext\Context\WikiContextDto;
use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;

/**
 * Flarum 1.8.19 create/update pre-save hook.
 *
 * New discussions: validate semantic state and attach it transiently to the
 * Discussion instance. Persistence waits for Discussion\Event\Started, which
 * only dispatches after first-post creation succeeds.
 *
 * Existing discussions: semantic corrections are forbidden on the normal
 * discussion PATCH; ordinary tag edits are checked so they cannot silently
 * drift a contextualized discussion to another owning board.
 */
final class CaptureDiscussionWikiContext
{
    public const PENDING_RELATION = '_flatRateWikiPendingContext';

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

        try {
            $validated = $this->writes->prepareInitial(
                $dto,
                $this->extractRequestedTagIds($event->data)
            );
        } catch (ContextWriteException $e) {
            if ($e->httpStatus === 403) {
                throw new PermissionDeniedException;
            }

            throw new ValidationException([
                'flatRateWikiContext' => $e->reason,
            ]);
        }

        $event->discussion->setRelation(
            self::PENDING_RELATION,
            new PendingContextWrite($validated)
        );
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
