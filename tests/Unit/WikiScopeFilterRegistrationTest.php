<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Filter\WikiScopeFilter;
use FlatRate\WikiContext\Projection\SettingsReader;
use Flarum\Settings\SettingsRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class WikiScopeFilterRegistrationTest extends TestCase
{
    public function test_filter_key(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $filter = new WikiScopeFilter(new SettingsReader($settings));

        $this->assertSame('wiki-scope', $filter->getFilterKey());
        echo "WIKI_SCOPE_FILTER_REGISTRATION=PASS\n";
    }
}
