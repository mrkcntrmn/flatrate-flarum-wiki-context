<?php

/*
 * This file is part of flatrate/flarum-wiki-context.
 *
 * THIS PACKAGE IS NOT YET AUTHORIZED FOR PRODUCTION INSTALLATION.
 * All public and production behavior defaults fail-closed.
 */

namespace FlatRate\WikiContext;

use Flarum\Api\Serializer\BasicDiscussionSerializer;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Discussion\Event\Started as DiscussionStarted;
use Flarum\Extend;
use FlatRate\WikiContext\Api\Controllers\DiscussionContextController;
use FlatRate\WikiContext\Api\Controllers\ProjectionActivateController;
use FlatRate\WikiContext\Api\Controllers\ProjectionChunkController;
use FlatRate\WikiContext\Api\Controllers\ProjectionStageController;
use FlatRate\WikiContext\Api\Controllers\ProjectionValidateController;
use FlatRate\WikiContext\Api\Controllers\PreviewAcceptController;
use FlatRate\WikiContext\Api\Controllers\PreviewStatusController;
use FlatRate\WikiContext\Api\Controllers\ScopeChildrenController;
use FlatRate\WikiContext\Api\Controllers\ScopeResolveController;
use FlatRate\WikiContext\Api\Controllers\ScopeSearchController;
use FlatRate\WikiContext\Api\Controllers\ScopeShowController;
use FlatRate\WikiContext\Api\Serializers\DiscussionWikiContextAttributes;
use FlatRate\WikiContext\Context\ContextWritePolicy;
use FlatRate\WikiContext\Command\BackfillRootContextCommand;
use FlatRate\WikiContext\Command\PreviewAcceptCommand;
use FlatRate\WikiContext\Command\PreviewStatusCommand;
use FlatRate\WikiContext\Command\ProjectionReconcileCommand;
use FlatRate\WikiContext\Command\ProjectionRollbackCommand;
use FlatRate\WikiContext\Command\ProjectionStatusCommand;
use FlatRate\WikiContext\Filter\WikiScopeFilter;
use FlatRate\WikiContext\Listener\CaptureDiscussionWikiContext;
use FlatRate\WikiContext\Listener\PersistStartedDiscussionWikiContext;
use FlatRate\WikiContext\Middleware\BrowseRouteGateMiddleware;
use FlatRate\WikiContext\Middleware\ProjectionHmacMiddleware;
use FlatRate\WikiContext\Support\FeatureGates;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/forum.less')
        ->route('/browse/{id}', 'flatrate.wiki.browse')
        ->route('/browse/{id}/{slug}', 'flatrate.wiki.browse.slug'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    (new Extend\Settings())
        ->default(FeatureGates::PROJECTION_SYNC_ENABLED, '0')
        ->default(FeatureGates::BROWSE_ROUTES_ENABLED, '0')
        ->default(FeatureGates::CONTEXT_WRITES_ENABLED, '0')
        ->default(FeatureGates::DERIVED_FEEDS_ENABLED, '0')
        ->default(FeatureGates::BRAND_ROOT_DERIVED_FEEDS_ENABLED, '0')
        ->default(FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED, '0')
        ->default(FeatureGates::PUBLIC_ROLLOUT_ENABLED, '0')
        ->default('flatrate-wiki.projection_hmac_secret', '')
        ->default('flatrate-wiki.projection_max_clock_skew_seconds', '60')
        ->serializeToForum('flatRateWikiBrowseRoutesEnabled', FeatureGates::BROWSE_ROUTES_ENABLED, 'boolval')
        ->serializeToForum('flatRateWikiContextWritesEnabled', FeatureGates::CONTEXT_WRITES_ENABLED, 'boolval')
        ->serializeToForum('flatRateWikiDerivedFeedsEnabled', FeatureGates::DERIVED_FEEDS_ENABLED, 'boolval')
        ->serializeToForum('flatRateWikiBrandRootDerivedFeedsEnabled', FeatureGates::BRAND_ROOT_DERIVED_FEEDS_ENABLED, 'boolval')
        ->serializeToForum('flatRateWikiAdminGhostPreviewEnabled', FeatureGates::ADMIN_GHOST_PREVIEW_ENABLED, 'boolval')
        ->serializeToForum('flatRateWikiPublicRolloutEnabled', FeatureGates::PUBLIC_ROLLOUT_ENABLED, 'boolval'),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attributes(static function (ForumSerializer $serializer, $model, array $attributes): array {
            // Public-safe numeric policy bound from server authority (not a magic client constant).
            $attributes['flatRateWikiRelevanceMaxActive'] = ContextWritePolicy::MAX_ACTIVE_RELEVANCE;

            return $attributes;
        }),

    (new Extend\Routes('api'))
        // Static paths before /scopes/{id} so "search"/"resolve" are never treated as UUIDs.
        ->get('/flatrate-wiki/preview/status', 'flatrate.wiki.preview.status', PreviewStatusController::class)
        ->post('/flatrate-wiki/preview/accept', 'flatrate.wiki.preview.accept', PreviewAcceptController::class)
        ->get('/flatrate-wiki/scopes/search', 'flatrate.wiki.scopes.search', ScopeSearchController::class)
        ->get('/flatrate-wiki/scopes/resolve', 'flatrate.wiki.scopes.resolve', ScopeResolveController::class)
        ->get('/flatrate-wiki/scopes/{id}', 'flatrate.wiki.scopes.show', ScopeShowController::class)
        ->get('/flatrate-wiki/scopes/{id}/children', 'flatrate.wiki.scopes.children', ScopeChildrenController::class)
        ->post('/flatrate-wiki/projection/stage', 'flatrate.wiki.projection.stage', ProjectionStageController::class)
        ->post('/flatrate-wiki/projection/chunk', 'flatrate.wiki.projection.chunk', ProjectionChunkController::class)
        ->post('/flatrate-wiki/projection/validate', 'flatrate.wiki.projection.validate', ProjectionValidateController::class)
        ->post('/flatrate-wiki/projection/activate', 'flatrate.wiki.projection.activate', ProjectionActivateController::class)
        ->patch('/flatrate-wiki/discussions/{id}/context', 'flatrate.wiki.discussions.context', DiscussionContextController::class),

    (new Extend\Middleware('api'))
        ->add(ProjectionHmacMiddleware::class),

    (new Extend\Middleware('forum'))
        ->add(BrowseRouteGateMiddleware::class),

    (new Extend\Event())
        ->listen(DiscussionSaving::class, CaptureDiscussionWikiContext::class)
        ->listen(DiscussionStarted::class, PersistStartedDiscussionWikiContext::class),

    (new Extend\Filter(\Flarum\Discussion\Filter\DiscussionFilterer::class))
        ->addFilter(WikiScopeFilter::class),

    (new Extend\ApiSerializer(BasicDiscussionSerializer::class))
        ->attributes(DiscussionWikiContextAttributes::class),

    (new Extend\Console())
        ->command(ProjectionStatusCommand::class)
        ->command(ProjectionReconcileCommand::class)
        ->command(ProjectionRollbackCommand::class)
        ->command(BackfillRootContextCommand::class)
        ->command(PreviewStatusCommand::class)
        ->command(PreviewAcceptCommand::class),

    (new Extend\Csrf())
        ->exemptRoute('flatrate.wiki.projection.stage')
        ->exemptRoute('flatrate.wiki.projection.chunk')
        ->exemptRoute('flatrate.wiki.projection.validate')
        ->exemptRoute('flatrate.wiki.projection.activate'),
];
