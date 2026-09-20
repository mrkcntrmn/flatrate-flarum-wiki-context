<?php

namespace FlatRate\WikiContext\Tests\Integration;

use FlatRate\WikiContext\Repository\ScopeReadRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class ScopeReadRepositoryTest extends TestCase
{
    private Capsule $capsule;
    private ConnectionInterface $db;
    private ScopeReadRepository $repo;

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
        $schema->create('flatrate_wiki_projection_state', function (Blueprint $table) {
            $table->char('community_uuid', 36)->primary();
            $table->char('active_graph_version_uuid', 36);
        });
        $schema->create('flatrate_wiki_scopes', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
            $table->char('scope_uuid', 36);
            $table->char('community_uuid', 36);
            $table->string('scope_type', 64);
            $table->string('owning_board_key', 128)->nullable();
            $table->string('route_key', 191)->nullable();
            $table->string('display_label', 255);
            $table->string('lifecycle_status', 32);
            $table->char('primary_parent_scope_uuid', 36)->nullable();
            $table->boolean('is_catch_all')->default(false);
            $table->boolean('discussion_capable')->default(true);
            $table->primary(['graph_version_uuid', 'scope_uuid']);
        });
        $schema->create('flatrate_wiki_scope_ancestors', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
            $table->char('descendant_scope_uuid', 36);
            $table->char('ancestor_scope_uuid', 36);
            $table->unsignedInteger('depth');
            $table->primary(['graph_version_uuid', 'descendant_scope_uuid', 'ancestor_scope_uuid']);
        });

        $this->seedGraph(self::GRAPH_V1);
        $this->seedGraph(self::GRAPH_V2);

        $this->db->table('flatrate_wiki_projection_state')->insert([
            'community_uuid' => self::COMMUNITY,
            'active_graph_version_uuid' => self::GRAPH_V1,
        ]);

        $this->repo = new ScopeReadRepository($this->db);
    }

    public function test_find_active_returns_only_public_safe_active_scope_fields(): void
    {
        $scope = $this->repo->findActive(self::TOYOTA);

        $this->assertNotNull($scope);
        $this->assertSame([
            'id',
            'type',
            'label',
            'route',
            'owningBoardKey',
            'parentId',
            'discussionCapable',
            'isCatchAll',
        ], array_keys($scope));
        $this->assertSame('Toyota', $scope['label']);
        $this->assertNull($this->repo->findActive(self::RETIRED));
        $this->assertNull($this->repo->findActive('99999999-9999-4999-8999-999999999999'));

        echo "WIKI001F_ACTIVE_SCOPE_ONLY=PASS\n";
        echo "WIKI001F_PUBLIC_SAFE_SCOPE_FIELDS=PASS\n";
    }

    public function test_active_graph_version_id_rotates_with_projection_state(): void
    {
        $this->assertSame(self::GRAPH_V1, $this->repo->activeGraphVersionId(self::BRAKES));
        $this->assertSame(self::GRAPH_V1, $this->repo->activeGraphVersionId(self::TOYOTA));

        $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', self::COMMUNITY)
            ->update(['active_graph_version_uuid' => self::GRAPH_V2]);

        $this->assertSame(self::GRAPH_V2, $this->repo->activeGraphVersionId(self::BRAKES));
        $this->assertSame(self::BRAKES, $this->repo->findActive(self::BRAKES)['id']);
        $this->assertNull($this->repo->activeGraphVersionId(self::RETIRED));
        $this->assertNull($this->repo->activeGraphVersionId('99999999-9999-4999-8999-999999999999'));

        echo "WIKI001F_ACTIVE_GRAPH_VERSION_PUBLIC_SAFE=PASS\n";
        echo "WIKI001F_GRAPH_ROTATION_SCOPE_META=PASS\n";
    }

    public function test_search_active_discussion_capable_is_bounded_and_blank_safe(): void
    {
        $this->assertSame([], $this->repo->searchActiveDiscussionCapable(''));
        $this->assertSame([], $this->repo->searchActiveDiscussionCapable('   '));

        $brakes = $this->repo->searchActiveDiscussionCapable('Brakes');
        $this->assertSame(['Brakes'], array_column($brakes, 'label'));

        $retired = $this->repo->searchActiveDiscussionCapable('Retired');
        $this->assertSame([], array_column($retired, 'label'));

        $long = str_repeat('a', 80);
        $this->assertSame([], $this->repo->searchActiveDiscussionCapable($long));

        $bounded = $this->repo->searchActiveDiscussionCapable('a', 999);
        $this->assertLessThanOrEqual(20, count($bounded));

        echo "WIKI001F_RELEVANCE_SEARCH_ACTIVE_DISCUSSION_CAPABLE=PASS\n";
        echo "WIKI001F_SCOPE_SEARCH_NO_BLANK_DUMP=PASS\n";
    }

    public function test_resolve_active_is_bounded_and_skips_unknown_or_retired(): void
    {
        $resolved = $this->repo->resolveActive([
            self::TOYOTA,
            self::RETIRED,
            'not-a-uuid',
            self::BRAKES,
            self::TOYOTA,
        ]);

        $this->assertSame([self::TOYOTA, self::BRAKES], array_column($resolved, 'id'));

        $many = [];
        for ($i = 0; $i < 60; $i++) {
            $many[] = sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d', $i);
        }
        $many[0] = self::TOYOTA;
        $capped = $this->repo->resolveActive($many, 50);
        $this->assertLessThanOrEqual(50, count($capped));

        echo "WIKI001F_SCOPE_RESOLVE_BOUNDED=PASS\n";
    }

    public function test_breadcrumb_is_root_to_current_and_active_graph_only(): void
    {
        $labels = array_column($this->repo->breadcrumbs(self::BRAKES), 'label');
        $this->assertSame(['Toyota', 'Camry', 'Brakes'], $labels);

        $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', self::COMMUNITY)
            ->update(['active_graph_version_uuid' => self::GRAPH_V2]);

        $labelsV2 = array_column($this->repo->breadcrumbs(self::BRAKES), 'label');
        $this->assertSame(['Labor Law', 'Brakes'], $labelsV2);

        echo "WIKI001F_BREADCRUMB=PASS\n";
        echo "WIKI001F_ACTIVE_PROJECTION_ONLY=PASS\n";
    }

    public function test_normal_children_are_direct_only_and_catch_all_is_separate(): void
    {
        $page = $this->repo->normalChildren(self::TOYOTA, 0, 50);
        $labels = array_column($page['items'], 'label');

        $this->assertSame(['Camry', 'Corolla'], $labels);
        $this->assertNotContains('Brakes', $labels);
        $this->assertNotContains('Retired Child', $labels);
        $this->assertNotContains('Other / Misc', $labels);
        $this->assertSame(2, $page['total']);
        $this->assertFalse($page['has_more']);

        $catchAll = $this->repo->catchAllChild(self::TOYOTA);
        $this->assertNotNull($catchAll);
        $this->assertSame('Other / Misc', $catchAll['label']);
        $this->assertTrue($catchAll['isCatchAll']);
        $this->assertSame(3, $this->repo->childCount(self::TOYOTA));

        echo "WIKI001F_DIRECT_CHILDREN_ONLY=PASS\n";
        echo "WIKI001F_MISC_LAST=PASS\n";
        echo "WIKI001F_MISC_NOT_OVERFLOW=PASS\n";
        echo "WIKI001F_RETIRED_CHILD_EXCLUDED=PASS\n";
    }

    public function test_normal_children_pagination_is_bounded_without_consuming_misc(): void
    {
        $first = $this->repo->normalChildren(self::TOYOTA, 0, 1);
        $second = $this->repo->normalChildren(self::TOYOTA, 1, 1);

        $this->assertSame(['Camry'], array_column($first['items'], 'label'));
        $this->assertTrue($first['has_more']);
        $this->assertSame(['Corolla'], array_column($second['items'], 'label'));
        $this->assertFalse($second['has_more']);

        $bounded = $this->repo->normalChildren(self::TOYOTA, 0, 999);
        $this->assertSame(50, $bounded['limit']);
        $this->assertSame('Other / Misc', $this->repo->catchAllChild(self::TOYOTA)['label']);

        echo "WIKI001F_CHILD_PAGINATION=PASS\n";
        echo "WIKI001F_CHILD_LIMIT_BOUNDED=PASS\n";
        echo "WIKI001F_MISC_BUDGET_EXEMPT=PASS\n";
    }

    public function test_normal_children_search_is_bounded_to_active_direct_children(): void
    {
        $camry = $this->repo->normalChildren(self::TOYOTA, 0, 20, 'Cam');
        $this->assertSame(['Camry'], array_column($camry['items'], 'label'));
        $this->assertSame(1, $camry['total']);
        $this->assertSame('Cam', $camry['query']);

        $deep = $this->repo->normalChildren(self::TOYOTA, 0, 20, 'Brakes');
        $this->assertSame([], array_column($deep['items'], 'label'));

        $retired = $this->repo->normalChildren(self::TOYOTA, 0, 20, 'Retired');
        $this->assertSame([], array_column($retired['items'], 'label'));

        echo "WIKI001F_CHILD_SEARCH=PASS\n";
        echo "WIKI001F_CHILD_SEARCH_DIRECT_ONLY=PASS\n";
    }

    private function seedGraph(string $graph): void
    {
        $v2 = $graph === self::GRAPH_V2;

        foreach ([
            [self::TOYOTA, 'brand', 'Toyota', null, false, 'active'],
            [self::CAMRY, 'model', 'Camry', self::TOYOTA, false, 'active'],
            [self::COROLLA, 'model', 'Corolla', self::TOYOTA, false, 'active'],
            [self::BRAKES, 'system', 'Brakes', $v2 ? self::LABOR : self::CAMRY, false, 'active'],
            [self::MISC, 'catch_all', 'Other / Misc', self::TOYOTA, true, 'active'],
            [self::LABOR, 'domain', 'Labor Law', null, false, 'active'],
            [self::RETIRED, 'model', 'Retired Child', self::TOYOTA, false, 'retired'],
        ] as [$id, $type, $label, $parent, $catchAll, $status]) {
            $this->db->table('flatrate_wiki_scopes')->insert([
                'graph_version_uuid' => $graph,
                'scope_uuid' => $id,
                'community_uuid' => self::COMMUNITY,
                'scope_type' => $type,
                'owning_board_key' => $type === 'domain' ? 'labor-law' : 'toyota',
                'route_key' => strtolower(str_replace(' ', '-', $label)),
                'display_label' => $label,
                'lifecycle_status' => $status,
                'primary_parent_scope_uuid' => $parent,
                'is_catch_all' => $catchAll,
                'discussion_capable' => $type !== 'domain' || $label === 'Labor Law',
            ]);
        }

        $ancestors = $v2
            ? [
                [self::CAMRY, self::TOYOTA, 1],
                [self::COROLLA, self::TOYOTA, 1],
                [self::BRAKES, self::LABOR, 1],
                [self::MISC, self::TOYOTA, 1],
                [self::RETIRED, self::TOYOTA, 1],
            ]
            : [
                [self::CAMRY, self::TOYOTA, 1],
                [self::COROLLA, self::TOYOTA, 1],
                [self::BRAKES, self::CAMRY, 1],
                [self::BRAKES, self::TOYOTA, 2],
                [self::MISC, self::TOYOTA, 1],
                [self::RETIRED, self::TOYOTA, 1],
            ];

        foreach ($ancestors as [$descendant, $ancestor, $depth]) {
            $this->db->table('flatrate_wiki_scope_ancestors')->insert([
                'graph_version_uuid' => $graph,
                'descendant_scope_uuid' => $descendant,
                'ancestor_scope_uuid' => $ancestor,
                'depth' => $depth,
            ]);
        }
    }

    private const COMMUNITY = '22222222-2222-4222-8222-222222222222';
    private const GRAPH_V1 = '11111111-1111-4111-8111-111111111111';
    private const GRAPH_V2 = '33333333-3333-4333-8333-333333333333';

    private const TOYOTA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const CAMRY = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const COROLLA = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const BRAKES = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    private const MISC = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
    private const LABOR = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
    private const RETIRED = '12121212-1212-4212-8212-121212121212';
}
