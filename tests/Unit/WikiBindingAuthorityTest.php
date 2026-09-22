<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Support\DirectoryDisplayPolicy;
use FlatRate\WikiContext\Support\WikiBuild;
use FlatRate\WikiContext\Support\WikiContract;
use PHPUnit\Framework\TestCase;

final class WikiBindingAuthorityTest extends TestCase
{
    public function test_build_and_contract_are_explicit_non_empty_constants(): void
    {
        $this->assertNotSame('', WikiBuild::BUILD_ID);
        $this->assertStringStartsWith('flatrate-wiki-context.build.', WikiBuild::BUILD_ID);
        $this->assertNotSame('', WikiContract::VERSION);
        $this->assertStringStartsWith('flatrate.wiki.context.contract.', WikiContract::VERSION);
        $this->assertStringNotContainsString('git', strtolower(WikiBuild::BUILD_ID));
        echo "WIKI001P1B_EXPLICIT_BUILD_AUTHORITY=PASS\n";
        echo "WIKI001P1B_EXPLICIT_CONTRACT_AUTHORITY=PASS\n";
    }

    public function test_directory_display_policy_digest_is_deterministic_sha256(): void
    {
        $a = DirectoryDisplayPolicy::digest();
        $b = DirectoryDisplayPolicy::digest();
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $a);
        echo "WIKI001P1B_POLICY_DIGEST=PASS\n";
    }
}
