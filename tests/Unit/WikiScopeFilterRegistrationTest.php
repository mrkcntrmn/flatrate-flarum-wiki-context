<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Filter\WikiScopeFilter;
use PHPUnit\Framework\TestCase;

final class WikiScopeFilterRegistrationTest extends TestCase
{
    public function test_filter_key(): void
    {
        $filter = new WikiScopeFilter();
        $this->assertSame('wiki-scope', $filter->getFilterKey());
        echo "WIKI_SCOPE_FILTER_REGISTRATION=PASS\n";
    }
}
