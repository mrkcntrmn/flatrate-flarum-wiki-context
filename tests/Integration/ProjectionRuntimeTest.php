<?php

namespace FlatRate\WikiContext\Tests\Integration;

use FlatRate\WikiContext\Projection\ProjectionService;
use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class ProjectionRuntimeTest extends TestCase
{
    private const COMMUNITY = '11111111-1111-4111-8111-111111111111';
    private const GRAPH_V1 = '22222222-2222-4222-8222-222222222222';
    private const GRAPH_V2 = '33333333-3333-4333-8333-333333333333';
    private const ROOT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const CHILD = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const MISC = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

    private Capsule $capsule;
    private ConnectionInterface $db;
    private ProjectionService $projection;

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
        $schema->create('flatrate_wiki_graph_versions', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36)->primary();
            $table->char('community_uuid', 36);
            $table->unsignedBigInteger('generation');
            $table->char('parent_graph_version_uuid', 36)->nullable();
            $table->string('schema_version', 64);
            $table->string('content_digest', 128);
            $table->unsignedInteger('node_count')->default(0);
            $table->unsignedInteger('scope_count')->default(0);
            $table->unsignedInteger('ancestor_count')->default(0);
            $table->unsignedInteger('alias_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->string('status', 32);
            $table->dateTime('received_at');
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('activated_at')->nullable();
        });
        $schema->create('flatrate_wiki_projection_state', function (Blueprint $table) {
            $table->char('community_uuid', 36)->primary();
            $table->char('active_graph_version_uuid', 36);
            $table->unsignedBigInteger('active_generation');
            $table->dateTime('activated_at');
            $table->dateTime('updated_at');
        });
        $schema->create('flatrate_wiki_projection_chunks', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('chunk_count');
            $table->string('chunk_digest', 128);
            $table->unsignedInteger('scope_row_count')->default(0);
            $table->unsignedInteger('ancestor_row_count')->default(0);
            $table->unsignedInteger('alias_row_count')->default(0);
            $table->dateTime('received_at');
            $table->primary(['graph_version_uuid', 'chunk_index']);
        });
        $schema->create('flatrate_wiki_scopes', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
            $table->char('scope_uuid', 36);
            $table->char('community_uuid', 36);
            $table->string('scope_type', 64);
            $table->string('owning_board_key', 128)->nullable();
            $table->string('route_key', 191)->nullable();
            $table->string('display_label', 255);
            $table->string('lifecycle_status', 32)->default('active');
            $table->char('primary_parent_scope_uuid', 36)->nullable();
            $table->string('scope_fingerprint', 128);
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

        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $key === FeatureGates::PROJECTION_SYNC_ENABLED ? '1' : $default
        );

        $this->projection = new ProjectionService($this->db, new SettingsReader($settings));
    }

    public function test_stage_chunk_validate_activate_materializes_rows_without_mutating_prior_active_on_reject(): void
    {
        $stage = $this->projection->stage([
            'graph_version_id' => self::GRAPH_V1,
            'community_id' => self::COMMUNITY,
            'generation' => 1,
            'parent_graph_version_id' => null,
            'schema_version' => 'wiki.graph.v1',
            'content_digest' => 'digest-v1',
            'expected_chunk_count' => 1,
            'scope_count' => 3,
            'ancestor_count' => 2,
            'node_count' => 3,
        ]);
        $this->assertSame('staged', $stage['status']);

        $chunk = $this->projection->chunk([
            'graph_version_id' => self::GRAPH_V1,
            'chunk_index' => 0,
            'chunk_count' => 1,
            'chunk_digest' => 'chunk-v1',
            'scopes' => $this->scopesFor(self::GRAPH_V1),
            'ancestors' => [
                [
                    'descendant_scope_uuid' => self::CHILD,
                    'ancestor_scope_uuid' => self::ROOT,
                    'depth' => 1,
                ],
                [
                    'descendant_scope_uuid' => self::MISC,
                    'ancestor_scope_uuid' => self::ROOT,
                    'depth' => 1,
                ],
            ],
        ]);
        $this->assertSame('accepted', $chunk['status']);
        $this->assertFalse($chunk['active_projection_mutated']);
        $this->assertSame(3, (int) $this->db->table('flatrate_wiki_scopes')->count());
        $this->assertSame(0, (int) $this->db->table('flatrate_wiki_projection_state')->count());

        $validated = $this->projection->validate(['graph_version_id' => self::GRAPH_V1]);
        $this->assertSame('validated', $validated['status']);

        $activated = $this->projection->activate(['graph_version_id' => self::GRAPH_V1]);
        $this->assertSame('activated', $activated['status']);
        $this->assertSame(self::GRAPH_V1, $this->db->table('flatrate_wiki_projection_state')->value('active_graph_version_uuid'));
        $this->assertTrue($activated['reconcile']['scope_count_matches_manifest']);

        // Second generation with bad catch-all board must fail validate; active unchanged.
        $this->projection->stage([
            'graph_version_id' => self::GRAPH_V2,
            'community_id' => self::COMMUNITY,
            'generation' => 2,
            'parent_graph_version_id' => self::GRAPH_V1,
            'schema_version' => 'wiki.graph.v1',
            'content_digest' => 'digest-v2',
            'expected_chunk_count' => 1,
            'scope_count' => 3,
            'ancestor_count' => 2,
            'node_count' => 3,
        ]);

        $badScopes = $this->scopesFor(self::GRAPH_V2);
        $badScopes[2]['owning_board_key'] = 'other-board';
        $this->projection->chunk([
            'graph_version_id' => self::GRAPH_V2,
            'chunk_index' => 0,
            'chunk_count' => 1,
            'chunk_digest' => 'chunk-v2-bad',
            'scopes' => $badScopes,
            'ancestors' => [
                [
                    'descendant_scope_uuid' => self::CHILD,
                    'ancestor_scope_uuid' => self::ROOT,
                    'depth' => 1,
                ],
                [
                    'descendant_scope_uuid' => self::MISC,
                    'ancestor_scope_uuid' => self::ROOT,
                    'depth' => 1,
                ],
            ],
        ]);

        $badValidate = $this->projection->validate(['graph_version_id' => self::GRAPH_V2]);
        $this->assertSame('reject', $badValidate['status']);
        $this->assertSame('CATCH_ALL_BOARD_MISMATCH', $badValidate['reason']);
        $this->assertSame(self::GRAPH_V1, $this->db->table('flatrate_wiki_projection_state')->value('active_graph_version_uuid'));

        echo "WIKI001P0_PROJECTION_MATERIALIZE=PASS\n";
        echo "WIKI001P0_VALIDATE_INTEGRITY=PASS\n";
        echo "WIKI001P0_ACTIVE_UNCHANGED_ON_REJECT=PASS\n";
    }

    public function test_chunk_idempotent_and_reconcile_rollback_dry_run(): void
    {
        $this->projection->stage([
            'graph_version_id' => self::GRAPH_V1,
            'community_id' => self::COMMUNITY,
            'generation' => 1,
            'schema_version' => 'wiki.graph.v1',
            'content_digest' => 'digest-v1',
            'expected_chunk_count' => 1,
            'scope_count' => 3,
            'ancestor_count' => 2,
        ]);
        $payload = [
            'graph_version_id' => self::GRAPH_V1,
            'chunk_index' => 0,
            'chunk_count' => 1,
            'chunk_digest' => 'chunk-v1',
            'scopes' => $this->scopesFor(self::GRAPH_V1),
            'ancestors' => [
                [
                    'descendant_scope_uuid' => self::CHILD,
                    'ancestor_scope_uuid' => self::ROOT,
                    'depth' => 1,
                ],
                [
                    'descendant_scope_uuid' => self::MISC,
                    'ancestor_scope_uuid' => self::ROOT,
                    'depth' => 1,
                ],
            ],
        ];
        $this->assertSame('accepted', $this->projection->chunk($payload)['status']);
        $again = $this->projection->chunk($payload);
        $this->assertSame('already_accepted', $again['status']);
        $this->assertSame(3, (int) $this->db->table('flatrate_wiki_scopes')->count());

        $this->projection->validate(['graph_version_id' => self::GRAPH_V1]);
        $this->projection->activate(['graph_version_id' => self::GRAPH_V1]);

        $reconcile = $this->projection->reconcile(self::COMMUNITY);
        $this->assertTrue($reconcile['communities'][0]['ok']);
        $this->assertTrue($reconcile['dry_run']);

        $dry = $this->projection->rollback(self::GRAPH_V1, 'noop', true);
        $this->assertSame('already_active', $dry['status']);

        echo "WIKI001P0_CHUNK_IDEMPOTENT=PASS\n";
        echo "WIKI001P0_RECONCILE=PASS\n";
    }

    public function test_alias_rows_fail_closed_until_table_exists(): void
    {
        $this->projection->stage([
            'graph_version_id' => self::GRAPH_V1,
            'community_id' => self::COMMUNITY,
            'generation' => 1,
            'schema_version' => 'wiki.graph.v1',
            'content_digest' => 'digest-v1',
            'expected_chunk_count' => 1,
            'scope_count' => 1,
            'ancestor_count' => 0,
        ]);

        $result = $this->projection->chunk([
            'graph_version_id' => self::GRAPH_V1,
            'chunk_index' => 0,
            'chunk_count' => 1,
            'chunk_digest' => 'chunk-alias',
            'scopes' => [[
                'scope_uuid' => self::ROOT,
                'community_uuid' => self::COMMUNITY,
                'scope_type' => 'brand',
                'display_label' => 'Toyota',
                'scope_fingerprint' => 'fp-root',
                'owning_board_key' => 'toyota',
            ]],
            'ancestors' => [],
            'aliases' => [['old_route_key' => 'old', 'target_scope_uuid' => self::ROOT]],
        ]);

        $this->assertSame('reject', $result['status']);
        $this->assertSame('ALIAS_ROWS_UNSUPPORTED', $result['reason']);
        $this->assertSame(0, (int) $this->db->table('flatrate_wiki_scopes')->count());
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function scopesFor(string $graphIgnored): array
    {
        return [
            [
                'scope_uuid' => self::ROOT,
                'community_uuid' => self::COMMUNITY,
                'scope_type' => 'brand',
                'display_label' => 'Toyota',
                'scope_fingerprint' => 'fp-root',
                'owning_board_key' => 'toyota',
                'route_key' => 'toyota',
                'discussion_capable' => true,
            ],
            [
                'scope_uuid' => self::CHILD,
                'community_uuid' => self::COMMUNITY,
                'scope_type' => 'model',
                'display_label' => 'Camry',
                'scope_fingerprint' => 'fp-child',
                'owning_board_key' => 'toyota',
                'primary_parent_scope_uuid' => self::ROOT,
                'route_key' => 'toyota-camry',
            ],
            [
                'scope_uuid' => self::MISC,
                'community_uuid' => self::COMMUNITY,
                'scope_type' => 'catch_all',
                'display_label' => 'Other',
                'scope_fingerprint' => 'fp-misc',
                'owning_board_key' => 'toyota',
                'primary_parent_scope_uuid' => self::ROOT,
                'is_catch_all' => true,
                'discussion_capable' => true,
            ],
        ];
    }
}
