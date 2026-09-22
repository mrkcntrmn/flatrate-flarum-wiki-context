<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Preview\PreviewAudienceFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PreviewAudienceFactoryTest extends TestCase
{
    public function test_default_and_required_profiles_never_elevate(): void
    {
        $factory = new PreviewAudienceFactory();
        $default = $factory->create();
        $this->assertSame('standard_member', $default->profile);
        $this->assertFalse($default->elevatedVisibility);
        $this->assertFalse($default->canSeeHiddenDiscussions());

        foreach ($factory->requiredAudiences() as $audience) {
            $this->assertFalse($audience->elevatedVisibility);
            $this->assertFalse($audience->canSeeHiddenDiscussions());
        }

        $this->expectException(InvalidArgumentException::class);
        $factory->create('admin');
    }
}
