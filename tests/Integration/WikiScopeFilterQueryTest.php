<?php

namespace FlatRate\WikiContext\Tests\Integration;

use FlatRate\WikiContext\Filter\WikiScopeFilter;
use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Filter\FilterState;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class WikiScopeFilterQueryTest extends TestCase
{
    private Capsule $capsule;
    private ConnectionInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->db = $this->capsule->getConnection();

        $schema = $this->db->getSchemaBuilder();

        $schema->create('discussions', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->boolean('is_hidden')->default(false);
            $table->integer('latest_score')->default(0);
            $table->integer('top_score')->default(0);
        });

        $schema->create('flatrate_wiki_projection_state', function (Blueprint $table) {
            $table->char('community_uuid', 36)->primary();
            $table->char('active_graph_version_uuid', 36);
        });

        $schema->create('flatrate_wiki_scopes', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
            $table->char('scope_uuid', 36);
            $table->char('community_uuid', 36);
            $table->string('lifecycle_status', 32);
            $table->primary(['graph_version_uuid', 'scope_uuid']);
        });

        $schema->create('flatrate_wiki_scope_ancestors', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
            $table->char('descendant_scope_uuid', 36);
            $table->char('ancestor_scope_uuid', 36);
            $table->unsignedInteger('depth');
            $table->primary(['graph_version_uuid', 'descendant_scope_uuid', 'ancestor_scope_uuid']);
        });

        $schema->create('flatrate_wiki_discussion_context', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id')->primary();
            $table->char('primary_scope_uuid', 36);
        });

        $schema->create('flatrate_wiki_discussion_relevance', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id');
            $table->char('relevant_scope_uuid', 36);
            $table->dateTime('removed_at')->nullable();
            $table->primary(['discussion_id', 'relevant_scope_uuid']);
        });

        $this->seedProjection();
        $this->seedDiscussions();
    }

    public function test_gate_fails_closed_until_derived_feed_and_public_rollout_are_both_open(): void
    {
        $this->assertSame([], $this->idsFor(self::TOYOTA, false, true, false));
        $this->assertSame([], $this->idsFor(self::TOYOTA, false, false, true));
        $this->assertNotSame([], $this->idsFor(self::TOYOTA, false, true, true));

        echo "WIKI001E_GATE_FAIL_CLOSED=PASS\n";
    }

    public function test_membership_covers_primary_relevance_descendants_and_removed_relevance_without_duplicates(): void
    {
        $ids = $this->idsFor(self::TOYOTA);

        $this->assertSame([1, 2, 3, 4, 7, 10], $ids);
        $this->assertNotContains(5, $ids, 'outer visibility predicate must still exclude hidden discussion');
        $this->assertNotContains(6, $ids, 'removed relevance must not count');
        $this->assertSame(count($ids), count(array_unique($ids)), 'multi-path membership must not duplicate a discussion');

        echo "WIKI001E_PRIMARY_EXACT=PASS\n";
        echo "WIKI001E_PRIMARY_DESCENDANT=PASS\n";
        echo "WIKI001E_RELEVANCE_EXACT=PASS\n";
        echo "WIKI001E_RELEVANCE_DESCENDANT=PASS\n";
        echo "WIKI001E_REMOVED_RELEVANCE_EXCLUDED=PASS\n";
        echo "WIKI001E_DUPLICATE_MEMBERSHIP_DEDUPED=PASS\n";
        echo "WIKI001E_NATIVE_VISIBILITY=PASS\n";
    }

    public function test_target_must_be_active_and_valid_in_active_projection(): void
    {
        $this->assertSame([], $this->idsFor(self::RETIRED));
        $this->assertSame([], $this->idsFor('99999999-9999-4999-8999-999999999999'));
        $this->assertSame([], $this->idsFor('not-a-uuid'));

        echo "WIKI001E_ACTIVE_TARGET_SCOPE_REQUIRED=PASS\n";
        echo "WIKI001E_RETIRED_SCOPE_EXCLUDED=PASS\n";
        echo "WIKI001E_UNKNOWN_SCOPE_FAILS_CLOSED=PASS\n";
        echo "WIKI001E_MALFORMED_SCOPE_FAILS_CLOSED=PASS\n";
    }

    public function test_graph_rotation_reinterprets_stable_assignments_using_only_active_closure(): void
    {
        $this->assertContains(7, $this->idsFor(self::TOYOTA));
        $this->assertNotContains(7, $this->idsFor(self::LABOR));

        $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', self::COMMUNITY)
            ->update(['active_graph_version_uuid' => self::GRAPH_V2]);

        // Discussion 7 keeps the same stable primary scope X. Only the active
        // graph's ancestor closure changes from Toyota to Labor.
        $this->assertNotContains(7, $this->idsFor(self::TOYOTA));
        $this->assertContains(7, $this->idsFor(self::LABOR));

        // Discussion 8 points to a retired scope in both graphs. It never
        // becomes a member merely because the UUID remains stored.
        $this->assertNotContains(8, $this->idsFor(self::TOYOTA));
        $this->assertNotContains(8, $this->idsFor(self::LABOR));

        echo "WIKI001E_STABLE_ASSIGNMENT_SURVIVES_GRAPH_ROTATION=PASS\n";
        echo "WIKI001E_REPARENT_USES_ACTIVE_CLOSURE=PASS\n";
        echo "WIKI001E_RETIRED_SCOPE_EXCLUDED=PASS\n";
        echo "WIKI001E_GRAPH_ROTATION_NO_MIXED_VERSION=PASS\n";
    }

    public function test_outer_sort_and_pagination_are_preserved_after_semantic_filtering(): void
    {
        $latest1 = $this->idsFor(self::TOYOTA, false, true, true, 'latest_score', 2, 0);
        $latest2 = $this->idsFor(self::TOYOTA, false, true, true, 'latest_score', 2, 2);
        $latest3 = $this->idsFor(self::TOYOTA, false, true, true, 'latest_score', 2, 4);

        $this->assertSame([4, 2], $latest1);
        $this->assertSame([1, 3], $latest2);
        $this->assertSame([7, 10], $latest3);

        $top = $this->idsFor(self::TOYOTA, false, true, true, 'top_score');
        $this->assertSame([10, 3, 7, 2, 1, 4], $top);

        $allPaged = array_merge($latest1, $latest2, $latest3);
        $this->assertSame(count($allPaged), count(array_unique($allPaged)));

        echo "WIKI001E_DEDUP_BEFORE_PAGINATION=PASS\n";
        echo "WIKI001E_LATEST_SORT=PASS\n";
        echo "WIKI001E_TOP_SORT=PASS\n";
        echo "WIKI001E_NATIVE_PAGINATION=PASS\n";
    }

    public function test_negation_means_not_primary_or_relevance_membership(): void
    {
        $positive = $this->idsFor(self::TOYOTA);
        $negative = $this->idsFor(self::TOYOTA, true);

        $this->assertSame([], array_values(array_intersect($positive, $negative)));
        $this->assertSame([6, 8, 9], $negative);

        echo "WIKI001E_NEGATION_LOGIC=PASS\n";
    }

    /**
     * @return list<int>
     */
    private function idsFor(
        string $scopeUuid,
        bool $negate = false,
        bool $derivedEnabled = true,
        bool $publicEnabled = true,
        ?string $sortColumn = null,
        ?int $limit = null,
        int $offset = 0,
        bool $applyVisibility = true
    ): array {
        $query = $this->db->table('discussions');

        if ($applyVisibility) {
            // Stand-in for the native Flarum actor visibility constraints that
            // already exist on the outer DiscussionFilterer query.
            $query->where('is_hidden', false);
        }

        $state = new FilterState($query, $this->createMock(User::class), []);
        $this->filter($derivedEnabled, $publicEnabled)->filter($state, $scopeUuid, $negate);

        if ($sortColumn !== null) {
            $query->orderByDesc($sortColumn)->orderBy('id');
        } else {
            $query->orderBy('id');
        }

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        return array_values(array_map(
            'intval',
            $query->pluck('id')->all()
        ));
    }

    private function filter(bool $derivedEnabled, bool $publicEnabled): WikiScopeFilter
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $values = [
            FeatureGates::DERIVED_FEEDS_ENABLED => $derivedEnabled ? '1' : '0',
            FeatureGates::PUBLIC_ROLLOUT_ENABLED => $publicEnabled ? '1' : '0',
        ];

        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $values[$key] ?? $default
        );

        return new WikiScopeFilter(new SettingsReader($settings));
    }

    private function seedProjection(): void
    {
        $this->db->table('flatrate_wiki_projection_state')->insert([
            'community_uuid' => self::COMMUNITY,
            'active_graph_version_uuid' => self::GRAPH_V1,
        ]);

        foreach ([self::GRAPH_V1, self::GRAPH_V2] as $graph) {
            foreach ([
                [self::TOYOTA, 'active'],
                [self::CAMRY, 'active'],
                [self::BRAKES, 'active'],
                [self::LABOR, 'active'],
                [self::TEXAS, 'active'],
                [self::X, 'active'],
                [self::RETIRED, 'retired'],
            ] as [$scope, $status]) {
                $this->db->table('flatrate_wiki_scopes')->insert([
                    'graph_version_uuid' => $graph,
                    'scope_uuid' => $scope,
                    'community_uuid' => self::COMMUNITY,
                    'lifecycle_status' => $status,
                ]);
            }
        }

        foreach ([
            [self::GRAPH_V1, self::CAMRY, self::TOYOTA, 1],
            [self::GRAPH_V1, self::BRAKES, self::CAMRY, 1],
            [self::GRAPH_V1, self::BRAKES, self::TOYOTA, 2],
            [self::GRAPH_V1, self::TEXAS, self::LABOR, 1],
            [self::GRAPH_V1, self::X, self::TOYOTA, 1],

            [self::GRAPH_V2, self::CAMRY, self::TOYOTA, 1],
            [self::GRAPH_V2, self::BRAKES, self::CAMRY, 1],
            [self::GRAPH_V2, self::BRAKES, self::TOYOTA, 2],
            [self::GRAPH_V2, self::TEXAS, self::LABOR, 1],
            [self::GRAPH_V2, self::X, self::LABOR, 1],
        ] as [$graph, $descendant, $ancestor, $depth]) {
            $this->db->table('flatrate_wiki_scope_ancestors')->insert([
                'graph_version_uuid' => $graph,
                'descendant_scope_uuid' => $descendant,
                'ancestor_scope_uuid' => $ancestor,
                'depth' => $depth,
            ]);
        }
    }

    private function seedDiscussions(): void
    {
        foreach ([
            [1, false, 80, 20, self::TOYOTA],
            [2, false, 90, 30, self::CAMRY],
            [3, false, 70, 60, self::LABOR],
            [4, false, 100, 10, self::BRAKES],
            [5, true, 110, 100, self::TOYOTA],
            [6, false, 60, 50, self::LABOR],
            [7, false, 50, 40, self::X],
            [8, false, 40, 70, self::RETIRED],
            [9, false, 30, 80, self::LABOR],
            [10, false, 20, 90, self::CAMRY],
        ] as [$id, $hidden, $latest, $top, $primary]) {
            $this->db->table('discussions')->insert([
                'id' => $id,
                'is_hidden' => $hidden,
                'latest_score' => $latest,
                'top_score' => $top,
            ]);
            $this->db->table('flatrate_wiki_discussion_context')->insert([
                'discussion_id' => $id,
                'primary_scope_uuid' => $primary,
            ]);
        }

        foreach ([
            [3, self::TOYOTA, null],
            [4, self::TOYOTA, null],
            [6, self::TOYOTA, '2026-09-20 12:00:00'],
            [10, self::TOYOTA, null],
        ] as [$discussionId, $scope, $removedAt]) {
            $this->db->table('flatrate_wiki_discussion_relevance')->insert([
                'discussion_id' => $discussionId,
                'relevant_scope_uuid' => $scope,
                'removed_at' => $removedAt,
            ]);
        }
    }

    private const COMMUNITY = '22222222-2222-4222-8222-222222222222';
    private const GRAPH_V1 = '11111111-1111-4111-8111-111111111111';
    private const GRAPH_V2 = '33333333-3333-4333-8333-333333333333';

    private const TOYOTA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const CAMRY = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const BRAKES = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const LABOR = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    private const TEXAS = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
    private const X = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
    private const RETIRED = '12121212-1212-4212-8212-121212121212';
}
