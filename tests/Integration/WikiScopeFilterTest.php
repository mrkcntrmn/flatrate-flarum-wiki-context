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

final class WikiScopeFilterTest extends TestCase
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
            $table->unsignedInteger('last_activity');
            $table->unsignedInteger('top_score');
            $table->boolean('visible')->default(true);
        });

        $schema->create('flatrate_wiki_projection_state', function (Blueprint $table) {
            $table->char('community_uuid', 36)->primary();
            $table->char('active_graph_version_uuid', 36);
            $table->unsignedBigInteger('active_generation');
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

        $this->db->table('flatrate_wiki_projection_state')->insert([
            'community_uuid' => self::COMMUNITY,
            'active_graph_version_uuid' => self::GRAPH_V1,
            'active_generation' => 1,
        ]);

        foreach ([
            [self::TOYOTA, 'active'],
            [self::CAMRY, 'active'],
            [self::DEEP_TOYOTA, 'active'],
            [self::LABOR, 'active'],
            [self::TEXAS, 'active'],
            [self::RETIRED_SCOPE, 'retired'],
        ] as [$scope, $status]) {
            $this->addScope(self::GRAPH_V1, $scope, $status);
        }

        foreach ([
            [self::CAMRY, self::TOYOTA, 1],
            [self::DEEP_TOYOTA, self::CAMRY, 1],
            [self::DEEP_TOYOTA, self::TOYOTA, 2],
            [self::TEXAS, self::LABOR, 1],
        ] as [$descendant, $ancestor, $depth]) {
            $this->addAncestor(self::GRAPH_V1, $descendant, $ancestor, $depth);
        }

        $this->db->table('discussions')->insert([
            ['id' => 1, 'last_activity' => 90, 'top_score' => 10, 'visible' => 1],
            ['id' => 2, 'last_activity' => 80, 'top_score' => 50, 'visible' => 1],
            ['id' => 3, 'last_activity' => 70, 'top_score' => 30, 'visible' => 1],
            ['id' => 4, 'last_activity' => 60, 'top_score' => 40, 'visible' => 1],
            ['id' => 5, 'last_activity' => 100, 'top_score' => 100, 'visible' => 0],
            ['id' => 6, 'last_activity' => 50, 'top_score' => 20, 'visible' => 1],
            ['id' => 7, 'last_activity' => 40, 'top_score' => 15, 'visible' => 1],
            ['id' => 8, 'last_activity' => 30, 'top_score' => 60, 'visible' => 1],
            ['id' => 9, 'last_activity' => 20, 'top_score' => 5, 'visible' => 1],
            ['id' => 10, 'last_activity' => 10, 'top_score' => 70, 'visible' => 1],
        ]);

        $this->db->table('flatrate_wiki_discussion_context')->insert([
            ['discussion_id' => 1, 'primary_scope_uuid' => self::TOYOTA],
            ['discussion_id' => 2, 'primary_scope_uuid' => self::CAMRY],
            ['discussion_id' => 3, 'primary_scope_uuid' => self::LABOR],
            ['discussion_id' => 4, 'primary_scope_uuid' => self::DEEP_TOYOTA],
            ['discussion_id' => 5, 'primary_scope_uuid' => self::TOYOTA],
            ['discussion_id' => 6, 'primary_scope_uuid' => self::LABOR],
            ['discussion_id' => 7, 'primary_scope_uuid' => self::RETIRED_SCOPE],
            ['discussion_id' => 8, 'primary_scope_uuid' => self::LABOR],
            ['discussion_id' => 9, 'primary_scope_uuid' => self::DEEP_TOYOTA],
            ['discussion_id' => 10, 'primary_scope_uuid' => self::LABOR],
        ]);

        $this->db->table('flatrate_wiki_discussion_relevance')->insert([
            ['discussion_id' => 3, 'relevant_scope_uuid' => self::TOYOTA, 'removed_at' => null],
            ['discussion_id' => 4, 'relevant_scope_uuid' => self::TOYOTA, 'removed_at' => null],
            ['discussion_id' => 6, 'relevant_scope_uuid' => self::TOYOTA, 'removed_at' => '2026-09-20 12:00:00'],
            ['discussion_id' => 8, 'relevant_scope_uuid' => self::CAMRY, 'removed_at' => null],
        ]);
    }

    public function test_public_filter_fails_closed_until_both_gates_are_open(): void
    {
        $this->assertSame([], $this->idsFor(self::TOYOTA, false, false, true));
        $this->assertSame([], $this->idsFor(self::TOYOTA, false, true, false));
        $this->assertSame([1, 2, 3, 4, 5, 8, 9], $this->idsFor(self::TOYOTA));
    }

    public function test_membership_unions_exact_descendant_and_relevance_without_duplicates(): void
    {
        $this->assertSame(
            [1, 2, 3, 4, 5, 8, 9],
            $this->idsFor(self::TOYOTA)
        );
    }

    public function test_outer_visibility_latest_sort_and_pagination_remain_authoritative(): void
    {
        $page1 = $this->pagedIds(self::TOYOTA, 'last_activity', 2, 0);
        $page2 = $this->pagedIds(self::TOYOTA, 'last_activity', 2, 2);
        $page3 = $this->pagedIds(self::TOYOTA, 'last_activity', 2, 4);

        $this->assertSame([1, 2], $page1);
        $this->assertSame([3, 4], $page2);
        $this->assertSame([8, 9], $page3);
        $this->assertSame([1, 2, 3, 4, 8, 9], array_merge($page1, $page2, $page3));
    }

    public function test_top_sort_remains_authoritative(): void
    {
        $this->assertSame(
            [8, 2, 4, 3, 1, 9],
            $this->orderedVisibleIds(self::TOYOTA, 'top_score')
        );
    }

    public function test_removed_relevance_and_retired_scope_do_not_create_positive_membership(): void
    {
        $ids = $this->idsFor(self::TOYOTA);

        $this->assertNotContains(6, $ids);
        $this->assertNotContains(7, $ids);
    }

    public function test_unknown_or_malformed_target_fails_closed_even_when_negated(): void
    {
        $unknown = '99999999-9999-4999-8999-999999999999';

        $this->assertSame([], $this->idsFor($unknown));
        $this->assertSame([], $this->idsFor($unknown, true));
        $this->assertSame([], $this->idsFor('not-a-uuid'));
        $this->assertSame([], $this->idsFor('not-a-uuid', true));
    }

    public function test_negation_excludes_the_whole_membership_union(): void
    {
        $query = $this->db->table('discussions')
            ->where('visible', 1)
            ->orderBy('id');

        $this->applyFilter($query, self::TOYOTA, true);

        $this->assertSame([6, 7, 10], array_map('intval', $query->pluck('id')->all()));
    }

    public function test_graph_rotation_preserves_stable_assignments_but_drops_retired_descendant_membership(): void
    {
        foreach ([
            self::TOYOTA,
            self::CAMRY,
            self::LABOR,
            self::TEXAS,
        ] as $scope) {
            $this->addScope(self::GRAPH_V2, $scope, 'active');
        }

        $this->addAncestor(self::GRAPH_V2, self::CAMRY, self::TOYOTA, 1);
        $this->addAncestor(self::GRAPH_V2, self::TEXAS, self::LABOR, 1);

        $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', self::COMMUNITY)
            ->update([
                'active_graph_version_uuid' => self::GRAPH_V2,
                'active_generation' => 2,
            ]);

        $query = $this->db->table('discussions')
            ->where('visible', 1)
            ->orderBy('id');

        $this->applyFilter($query, self::TOYOTA);

        // 1 remains exact Toyota, 2 remains active Camry descendant,
        // 3/4 remain via explicit Toyota relevance, 8 via active Camry relevance.
        // 9 had only a deep descendant assignment; that scope is absent in V2.
        $this->assertSame([1, 2, 3, 4, 8], array_map('intval', $query->pluck('id')->all()));
    }

    private function idsFor(
        string $scopeUuid,
        bool $negate = false,
        bool $derivedFeeds = true,
        bool $publicRollout = true
    ): array {
        $query = $this->db->table('discussions')->orderBy('id');
        $this->applyFilter($query, $scopeUuid, $negate, $derivedFeeds, $publicRollout);

        return array_map('intval', $query->pluck('id')->all());
    }

    private function orderedVisibleIds(string $scopeUuid, string $column): array
    {
        $query = $this->db->table('discussions')
            ->where('visible', 1)
            ->orderByDesc($column);

        $this->applyFilter($query, $scopeUuid);

        return array_map('intval', $query->pluck('id')->all());
    }

    private function pagedIds(string $scopeUuid, string $column, int $limit, int $offset): array
    {
        $query = $this->db->table('discussions')
            ->where('visible', 1)
            ->orderByDesc($column)
            ->limit($limit)
            ->offset($offset);

        $this->applyFilter($query, $scopeUuid);

        return array_map('intval', $query->pluck('id')->all());
    }

    private function applyFilter(
        $query,
        string $scopeUuid,
        bool $negate = false,
        bool $derivedFeeds = true,
        bool $publicRollout = true
    ): void {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(
            function (string $key, $default = null) use ($derivedFeeds, $publicRollout) {
                return match ($key) {
                    FeatureGates::DERIVED_FEEDS_ENABLED => $derivedFeeds ? '1' : '0',
                    FeatureGates::PUBLIC_ROLLOUT_ENABLED => $publicRollout ? '1' : '0',
                    default => $default,
                };
            }
        );

        $filter = new WikiScopeFilter(new SettingsReader($settings));
        $state = new FilterState($query, new User());

        $filter->filter($state, $scopeUuid, $negate);
    }

    private function addScope(string $graph, string $scope, string $status): void
    {
        $this->db->table('flatrate_wiki_scopes')->insert([
            'graph_version_uuid' => $graph,
            'scope_uuid' => $scope,
            'community_uuid' => self::COMMUNITY,
            'lifecycle_status' => $status,
        ]);
    }

    private function addAncestor(string $graph, string $descendant, string $ancestor, int $depth): void
    {
        $this->db->table('flatrate_wiki_scope_ancestors')->insert([
            'graph_version_uuid' => $graph,
            'descendant_scope_uuid' => $descendant,
            'ancestor_scope_uuid' => $ancestor,
            'depth' => $depth,
        ]);
    }

    private const GRAPH_V1 = '11111111-1111-4111-8111-111111111111';
    private const GRAPH_V2 = '22222222-2222-4222-8222-222222222222';
    private const COMMUNITY = '33333333-3333-4333-8333-333333333333';

    private const TOYOTA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const CAMRY = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const DEEP_TOYOTA = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const LABOR = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    private const TEXAS = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
    private const RETIRED_SCOPE = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
}
