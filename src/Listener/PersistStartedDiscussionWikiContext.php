<?php

namespace FlatRate\WikiContext\Listener;

use FlatRate\WikiContext\Context\ContextWriteException;
use FlatRate\WikiContext\Context\ContextWriteService;
use FlatRate\WikiContext\Context\PendingContextWrite;
use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Discussion\Event\Started;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;

/**
 * Persist pending semantic state only after Flarum has successfully created the
 * first post and is dispatching Discussion\Event\Started.
 */
final class PersistStartedDiscussionWikiContext
{
    public function __construct(
        private ContextWriteService $writes,
        private SettingsReader $settings
    ) {
    }

    public function handle(Started $event): void
    {
        $discussion = $event->discussion;

        if (!$discussion->relationLoaded(CaptureDiscussionWikiContext::PENDING_RELATION)) {
            return;
        }

        $pending = $discussion->getRelation(CaptureDiscussionWikiContext::PENDING_RELATION);
        if (!$pending instanceof PendingContextWrite) {
            return;
        }

        if (
            !$this->settings->bool(FeatureGates::CONTEXT_WRITES_ENABLED)
            || !$this->settings->bool(FeatureGates::PUBLIC_ROLLOUT_ENABLED)
        ) {
            $discussion->delete();
            throw new PermissionDeniedException;
        }

        try {
            $this->writes->persistInitialValidated(
                (int) $discussion->id,
                (int) $discussion->user_id,
                (int) $event->actor->id,
                $pending->validated
            );

            $discussion->unsetRelation(CaptureDiscussionWikiContext::PENDING_RELATION);
        } catch (\Throwable $e) {
            // Flarum 1.8.19 has already created the first post by this point.
            // Fail closed by deleting the just-created discussion; Flarum/FKs
            // clean the post/context children, while our semantic transaction
            // rolls back before this catch executes.
            $discussion->delete();

            if ($e instanceof ContextWriteException) {
                if ($e->httpStatus === 403) {
                    throw new PermissionDeniedException;
                }

                throw new ValidationException([
                    'flatRateWikiContext' => $e->reason,
                ]);
            }

            throw $e;
        }
    }
}
