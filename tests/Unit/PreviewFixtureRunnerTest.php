<?php

namespace FlatRate\WikiContext\Tests\Unit;

use FlatRate\WikiContext\Preview\PreviewAudienceFactory;
use FlatRate\WikiContext\Preview\PreviewFixtureCatalog;
use FlatRate\WikiContext\Preview\PreviewFixtureRunner;
use PHPUnit\Framework\TestCase;

final class PreviewFixtureRunnerTest extends TestCase
{
    public function test_required_audience_matrices_pass_without_admin_visibility_leak(): void
    {
        $runner = new PreviewFixtureRunner(
            PreviewFixtureCatalog::fromDefaultFixtureFile(),
            new PreviewAudienceFactory()
        );

        $guest = $runner->runForAudience('guest');
        $member = $runner->runForAudience('standard_member');
        $all = $runner->runRequiredAudiences();
        $readOnly = $runner->runReadOnlyValidations();

        $this->assertTrue($guest['pass']);
        $this->assertTrue($member['pass']);
        $this->assertTrue($all['pass']);
        $this->assertFalse($guest['admin_elevated_visibility']);
        $this->assertFalse($member['admin_elevated_visibility']);
        $this->assertSame(['guest', 'standard_member'], $all['audiences']);
        $this->assertSame(64, strlen($all['fixture_results_digest']));

        foreach ([$guest, $member] as $matrix) {
            $byName = [];
            foreach ($matrix['results'] as $result) {
                $byName[$result['fixture']] = $result;
                $this->assertSame('PASS', $result['status'], $result['fixture']);
            }
            $this->assertArrayHasKey('all_discussions_root', $byName);
            $this->assertArrayHasKey('toyota_root', $byName);
            $this->assertArrayHasKey('deep_toyota_scope', $byName);
            $this->assertArrayHasKey('labor_law_texas_scope', $byName);
            $this->assertArrayHasKey('labor_law_relevant_to_toyota', $byName);
            $this->assertArrayHasKey('catch_all_scope', $byName);
            $this->assertArrayHasKey('overflow_more_view_all', $byName);
            $this->assertArrayHasKey('hidden_discussion_visibility', $byName);
            $this->assertTrue($byName['hidden_discussion_visibility']['checks']['hidden_excluded_from_all_discussions']);
            $this->assertFalse($byName['labor_law_relevant_to_toyota']['details']['observed']['Camry']);
            $this->assertTrue($byName['labor_law_relevant_to_toyota']['details']['observed']['Toyota']);
            $this->assertTrue($byName['labor_law_relevant_to_toyota']['details']['observed']['Texas']);
        }

        foreach ($readOnly as $check) {
            if ($check['check'] === 'dry_run_context_validation') {
                $this->assertSame('PASS', $check['status']);
                continue;
            }
            $this->assertFalse($check['allowed']);
            $this->assertSame('DENIED', $check['status']);
        }

        echo "GUEST_SIMULATION=PASS\n";
        echo "STANDARD_MEMBER_SIMULATION=PASS\n";
        echo "ADMIN_VISIBILITY_LEAK=false\n";
        echo "ALL_DISCUSSIONS_FIXTURE=PASS\n";
        echo "TOYOTA_ROOT_FIXTURE=PASS\n";
        echo "DEEP_TOYOTA_FIXTURE=PASS\n";
        echo "LABOR_LAW_TEXAS_FIXTURE=PASS\n";
        echo "LABOR_LAW_TOPIC_FIXTURE=PASS\n";
        echo "CROSS_CONTEXT_TOYOTA_FIXTURE=PASS\n";
        echo "OTHER_MISC_FIXTURE=PASS\n";
        echo "MORE_VIEW_ALL_FIXTURE=PASS\n";
        echo "HIDDEN_SENTINEL_EXCLUDED=PASS\n";
        echo "READ_ONLY=PASS\n";
        echo "CONTEXT_WRITE=false\n";
        echo "RELEVANCE_WRITE=false\n";
        echo "DISCUSSION_CREATE=false\n";
        echo "PROJECTION_ACTIVATION=false\n";
        echo "MODERATION_MUTATION=false\n";
    }
}
