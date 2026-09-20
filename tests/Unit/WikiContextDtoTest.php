<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Context\WikiContextDto;
use FlatRate\WikiContext\Moderation\ContextModerationPolicy;
use PHPUnit\Framework\TestCase;

final class WikiContextDtoTest extends TestCase
{
    public function test_optimistic_concurrency(): void
    {
        $dto = WikiContextDto::fromClientPayload([
            'flatRateWikiContext' => [
                'primaryScopeId' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'expectedGraphVersionId' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                'expectedRevision' => 3,
            ],
            'flatRateWikiRelevance' => [
                'scopeId' => ['cccccccc-cccc-4ccc-8ccc-cccccccccccc'],
            ],
        ]);

        $this->assertFalse($dto->revisionConflict(3));
        $this->assertTrue($dto->revisionConflict(4));
        $this->assertSame(409, ContextModerationPolicy::CONFLICT_HTTP_STATUS);
        $this->assertFalse(ContextModerationPolicy::allowsSilentLastWriteWins());
    }
}
