<?php

namespace FlatRate\WikiContext\Api\Controllers;

use FlatRate\WikiContext\Context\ContextWriteException;
use FlatRate\WikiContext\Context\ContextWriteService;
use FlatRate\WikiContext\Context\WikiContextDto;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Context-only correction endpoint.
 *
 * Isolated from normal discussion PATCH so a 409 revision race can never
 * partially mutate title/body/tags before semantic state is rejected.
 */
final class DiscussionContextController implements RequestHandlerInterface
{
    public function __construct(
        private ContextWriteService $writes,
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!(bool) $this->settings->get(FeatureGates::CONTEXT_WRITES_ENABLED)) {
            return $this->error(403, 'context_writes_disabled');
        }

        $params = $request->getAttribute('routeParameters') ?? [];
        $id = $params['id'] ?? $request->getAttribute('id');
        if (!is_numeric($id)) {
            return $this->error(404, 'discussion_not_found');
        }

        /** @var Discussion|null $discussion */
        $discussion = Discussion::query()->find((int) $id);
        if ($discussion === null) {
            return $this->error(404, 'discussion_not_found');
        }

        $actor = RequestUtil::getActor($request);
        $actor->assertCan('view', $discussion);
        $actor->assertCan('tag', $discussion);

        $body = (array) ($request->getParsedBody() ?? []);
        $attributes = (array) ($body['data']['attributes'] ?? $body['attributes'] ?? $body);

        if (
            !array_key_exists('flatRateWikiContext', $attributes)
            || !array_key_exists('flatRateWikiRelevance', $attributes)
        ) {
            return $this->error(422, 'full_context_state_required');
        }

        $dto = WikiContextDto::fromClientPayload($attributes);

        try {
            $state = $this->writes->correct(
                (int) $discussion->id,
                (int) $discussion->user_id,
                (int) $actor->id,
                $dto
            );
        } catch (ContextWriteException $e) {
            return $this->error($e->httpStatus, $e->reason);
        }

        return new JsonResponse([
            'data' => [
                'type' => 'flatrate-wiki-discussion-context',
                'id' => (string) $discussion->id,
                'attributes' => [
                    'flatRateWikiContext' => [
                        'primaryScopeId' => $state['primaryScopeId'],
                        'contextRevision' => $state['contextRevision'],
                    ],
                    'flatRateWikiRelevance' => [
                        'scopeId' => $state['relevanceScopeIds'],
                    ],
                ],
            ],
        ], 200);
    }

    private function error(int $status, string $code): JsonResponse
    {
        return new JsonResponse([
            'errors' => [[
                'status' => (string) $status,
                'code' => $code,
            ]],
        ], $status);
    }
}
