<?php

namespace FlatRate\WikiContext\Tests\Integration;

use FlatRate\WikiContext\Context\ContextWriteException;
use FlatRate\WikiContext\Context\ContextWritePolicy;
use FlatRate\WikiContext\Context\ContextWriteService;
use FlatRate\WikiContext\Context\WikiContextDto;
use FlatRate\WikiContext\Projection\SettingsReader;
use FlatRate\WikiContext\Repository\ActiveScopeRepository;
use FlatRate\WikiContext\Repository\DiscussionContextRepository;
use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class ContextWriteServiceTest extends TestCase
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

        $schema->create('flatrate_wiki_projection_state', function (Blueprint $table) {
            $table->char('community_uuid', 36)->primary();
            $table->char('active_graph_version_uuid', 36);
            $table->unsignedBigInteger('active_generation');
            $table->dateTime('activated_at');
            $table->dateTime('updated_at');
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
            $table->string('scope_fingerprint', 128);
            $table->boolean('is_catch_all')->default(false);
            $table->boolean('discussion_capable')->default(true);
        });

        $schema->create('flatrate_wiki_discussion_context', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id')->primary();
            $table->char('primary_scope_uuid', 36);
            $table->unsignedInteger('context_revision');
            $table->string('provenance', 64);
            $table->unsignedInteger('assigned_by_user_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        $schema->create('flatrate_wiki_discussion_relevance', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id');
            $table->char('relevant_scope_uuid', 36);
            $table->string('provenance', 64);
            $table->unsignedInteger('actor_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('removed_at')->nullable();
            $table->primary(['discussion_id', 'relevant_scope_uuid']);
        });

        $schema->create('flatrate_wiki_discussion_context_audit', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('discussion_id');
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('action', 64);
            $table->char('prior_primary_scope_uuid', 36)->nullable();
            $table->char('new_primary_scope_uuid', 36)->nullable();
            $table->char('relevant_scope_uuid', 36)->nullable();
            $table->unsignedInteger('prior_revision')->nullable();
            $table->unsignedInteger('new_revision')->nullable();
            $table->string('provenance', 64);
            $table->char('graph_version_uuid', 36)->nullable();
            $table->string('reason', 255)->nullable();
            $table->dateTime('created_at');
        });

        $schema->create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug');
            $table->integer('position')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
        });

        $schema->create('discussion_tag', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id');
            $table->unsignedInteger('tag_id');
        });

        $graph = self::GRAPH;
        $community = self::COMMUNITY;
        $now = '2026-09-20 10:00:00';

        $this->db->table('flatrate_wiki_projection_state')->insert([
            'community_uuid' => $community,
            'active_graph_version_uuid' => $graph,
            'active_generation' => 1,
            'activated_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            [self::TOYOTA_ROOT, 'board_root', 'toyota', 'Toyota'],
            [self::TOYOTA_CAMRY, 'vehicle_model', 'toyota', 'Camry'],
            [self::TOYOTA_BRAKES, 'vehicle_system', 'toyota', 'Brakes'],
            [self::LABOR_TEXAS, 'labor_jurisdiction', 'labor-law', 'Texas'],
            [self::LABOR_TOPIC, 'labor_topic', 'labor-law', 'Flat-Rate Compensation'],
        ] as [$scope, $type, $board, $label]) {
            $this->db->table('flatrate_wiki_scopes')->insert([
                'graph_version_uuid' => $graph,
                'scope_uuid' => $scope,
                'community_uuid' => $community,
                'scope_type' => $type,
                'owning_board_key' => $board,
                'route_key' => null,
                'display_label' => $label,
                'lifecycle_status' => 'active',
                'primary_parent_scope_uuid' => null,
                'scope_fingerprint' => hash('sha256', $scope),
                'is_catch_all' => false,
                'discussion_capable' => true,
            ]);
        }

        $this->db->table('tags')->insert([
            ['id' => 1, 'slug' => 'toyota', 'position' => 0, 'parent_id' => null],
            ['id' => 2, 'slug' => 'labor-law', 'position' => 1, 'parent_id' => null],
            ['id' => 3, 'slug' => 'secondary', 'position' => null, 'parent_id' => null],
        ]);
    }

    public function test_prepare_is_write_free_and_started_persist_path_writes_only_after_validation(): void
    {
        $service = $this->service(true);

        $validated = $service->prepareInitial(
            $this->dto(self::TOYOTA_CAMRY, null, [self::LABOR_TEXAS]),
            [1]
        );

        $this->assertSame(self::TOYOTA_CAMRY, $validated->primaryScopeUuid);
        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_context')->count());
        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_relevance')->count());
        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_context_audit')->count());

        $state = $service->persistInitialValidated(10, 7, 7, $validated);

        $this->assertSame(self::TOYOTA_CAMRY, $state['primaryScopeId']);
        $this->assertSame(1, $state['contextRevision']);
        $this->assertSame([self::LABOR_TEXAS], $state['relevanceScopeIds']);
        $this->assertSame(1, $this->db->table('flatrate_wiki_discussion_context')->count());
        $this->assertSame(1, $this->db->table('flatrate_wiki_discussion_relevance')->count());
        $this->assertSame(2, $this->db->table('flatrate_wiki_discussion_context_audit')->count());
    }

    public function test_prepared_context_fails_closed_if_active_graph_rotates_before_started_persistence(): void
    {
        $service = $this->service(true);

        $validated = $service->prepareInitial(
            $this->dto(self::TOYOTA_CAMRY, null, [self::LABOR_TEXAS]),
            [1]
        );

        $this->db->table('flatrate_wiki_projection_state')
            ->where('community_uuid', self::COMMUNITY)
            ->update([
                'active_graph_version_uuid' => '99999999-9999-4999-8999-999999999999',
                'active_generation' => 2,
            ]);

        try {
            $service->persistInitialValidated(10, 7, 7, $validated);
            $this->fail('Expected validated_context_became_stale');
        } catch (ContextWriteException $e) {
            $this->assertSame('validated_context_became_stale', $e->reason);
            $this->assertSame(409, $e->httpStatus);
        }

        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_context')->count());
        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_relevance')->count());
        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_context_audit')->count());
    }

    public function test_initial_assignment_persists_revision_relevance_and_author_provenance(): void
    {
        $service = $this->service(true);
        $dto = $this->dto(self::TOYOTA_CAMRY, null, [self::LABOR_TEXAS]);

        $state = $service->createInitial(10, 7, 7, $dto, [1, 3]);

        $this->assertSame(self::TOYOTA_CAMRY, $state['primaryScopeId']);
        $this->assertSame(1, $state['contextRevision']);
        $this->assertSame([self::LABOR_TEXAS], $state['relevanceScopeIds']);

        $context = $this->db->table('flatrate_wiki_discussion_context')->where('discussion_id', 10)->first();
        $this->assertSame(ContextWritePolicy::AUTHOR_SELECTED, $context->provenance);
        $this->assertSame(7, (int) $context->assigned_by_user_id);

        $relevance = $this->db->table('flatrate_wiki_discussion_relevance')->where('discussion_id', 10)->first();
        $this->assertSame(ContextWritePolicy::AUTHOR_SELECTED, $relevance->provenance);
        $this->assertNull($relevance->removed_at);

        $this->assertSame(2, $this->db->table('flatrate_wiki_discussion_context_audit')->where('discussion_id', 10)->count());
    }

    public function test_disabled_gate_rejects_without_rows(): void
    {
        $service = $this->service(false);

        try {
            $service->createInitial(10, 7, 7, $this->dto(self::TOYOTA_ROOT), [1]);
            $this->fail('Expected context_writes_disabled');
        } catch (ContextWriteException $e) {
            $this->assertSame('context_writes_disabled', $e->reason);
            $this->assertSame(403, $e->httpStatus);
        }

        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_context')->count());
    }

    public function test_board_scope_mismatch_is_rejected(): void
    {
        $service = $this->service(true);

        $this->expectWriteError('board_scope_mismatch', function () use ($service) {
            $service->createInitial(10, 7, 7, $this->dto(self::TOYOTA_ROOT), [2]);
        });

        $this->assertSame(0, $this->db->table('flatrate_wiki_discussion_context')->count());
    }

    public function test_relevance_limit_and_duplicates_fail_closed(): void
    {
        $service = $this->service(true);

        $tooMany = [
            self::LABOR_TEXAS,
            self::LABOR_TOPIC,
            self::TOYOTA_BRAKES,
            self::TOYOTA_ROOT,
            self::LABOR_TEXAS,
            self::LABOR_TOPIC,
        ];

        $this->expectWriteError('relevance_limit_exceeded', function () use ($service, $tooMany) {
            $service->createInitial(10, 7, 7, $this->dto(self::TOYOTA_CAMRY, null, $tooMany), [1]);
        });

        $this->expectWriteError('duplicate_relevance_scope', function () use ($service) {
            $service->createInitial(
                11,
                7,
                7,
                $this->dto(self::TOYOTA_CAMRY, null, [self::LABOR_TEXAS, self::LABOR_TEXAS]),
                [1]
            );
        });
    }

    public function test_stale_revision_returns_409_and_preserves_state(): void
    {
        $service = $this->service(true);
        $service->createInitial(10, 7, 7, $this->dto(self::TOYOTA_CAMRY, null, [self::LABOR_TEXAS]), [1]);
        $this->db->table('discussion_tag')->insert(['discussion_id' => 10, 'tag_id' => 1]);

        try {
            $service->correct(
                10,
                7,
                7,
                $this->dto(self::TOYOTA_BRAKES, 0, [self::LABOR_TOPIC])
            );
            $this->fail('Expected context_revision_conflict');
        } catch (ContextWriteException $e) {
            $this->assertSame('context_revision_conflict', $e->reason);
            $this->assertSame(409, $e->httpStatus);
        }

        $state = $service->state(10);
        $this->assertSame(self::TOYOTA_CAMRY, $state['primaryScopeId']);
        $this->assertSame(1, $state['contextRevision']);
        $this->assertSame([self::LABOR_TEXAS], $state['relevanceScopeIds']);
    }

    public function test_same_board_correction_increments_once_and_soft_lifecycles_relevance(): void
    {
        $service = $this->service(true);
        $service->createInitial(10, 7, 7, $this->dto(self::TOYOTA_CAMRY, null, [self::LABOR_TEXAS]), [1]);
        $this->db->table('discussion_tag')->insert(['discussion_id' => 10, 'tag_id' => 1]);

        $state = $service->correct(
            10,
            7,
            7,
            $this->dto(self::TOYOTA_BRAKES, 1, [self::LABOR_TOPIC])
        );

        $this->assertSame(self::TOYOTA_BRAKES, $state['primaryScopeId']);
        $this->assertSame(2, $state['contextRevision']);
        $this->assertSame([self::LABOR_TOPIC], $state['relevanceScopeIds']);

        $removed = $this->db->table('flatrate_wiki_discussion_relevance')
            ->where('discussion_id', 10)
            ->where('relevant_scope_uuid', self::LABOR_TEXAS)
            ->first();
        $this->assertNotNull($removed->removed_at);

        $added = $this->db->table('flatrate_wiki_discussion_relevance')
            ->where('discussion_id', 10)
            ->where('relevant_scope_uuid', self::LABOR_TOPIC)
            ->first();
        $this->assertNull($added->removed_at);

        $actions = $this->db->table('flatrate_wiki_discussion_context_audit')
            ->where('discussion_id', 10)
            ->pluck('action')
            ->all();
        $this->assertContains('primary_corrected', $actions);
        $this->assertContains('relevance_removed', $actions);
        $this->assertContains('relevance_added', $actions);
    }

    public function test_cross_board_primary_move_is_rejected_without_revision_change(): void
    {
        $service = $this->service(true);
        $service->createInitial(10, 7, 7, $this->dto(self::TOYOTA_CAMRY), [1]);
        $this->db->table('discussion_tag')->insert(['discussion_id' => 10, 'tag_id' => 1]);

        $this->expectWriteError('cross_board_primary_move_not_allowed', function () use ($service) {
            $service->correct(10, 7, 7, $this->dto(self::LABOR_TEXAS, 1));
        });

        $state = $service->state(10);
        $this->assertSame(self::TOYOTA_CAMRY, $state['primaryScopeId']);
        $this->assertSame(1, $state['contextRevision']);
    }

    public function test_existing_context_blocks_cross_board_tag_drift_but_legacy_discussion_is_unchanged(): void
    {
        $service = $this->service(true);
        $service->createInitial(10, 7, 7, $this->dto(self::TOYOTA_CAMRY), [1]);

        // Same top-level board remains valid.
        $service->assertExistingBoardCompatible(10, [1, 3]);

        $this->expectWriteError('board_context_change_requires_coordinated_move', function () use ($service) {
            $service->assertExistingBoardCompatible(10, [2]);
        });

        // No semantic row means old/legacy Flarum behavior is preserved.
        $service->assertExistingBoardCompatible(99, [2]);
        $this->assertTrue(true);
    }

    public function test_moderator_assignment_provenance_is_server_derived(): void
    {
        $service = $this->service(true);
        $service->createInitial(10, 7, 99, $this->dto(self::TOYOTA_CAMRY), [1]);

        $context = $this->db->table('flatrate_wiki_discussion_context')->where('discussion_id', 10)->first();
        $this->assertSame(ContextWritePolicy::MODERATOR_ASSIGNED, $context->provenance);
        $this->assertSame(99, (int) $context->assigned_by_user_id);
    }

    private function service(bool $enabled): ContextWriteService
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(
            fn (string $key, $default = null) => $key === FeatureGates::CONTEXT_WRITES_ENABLED
                ? ($enabled ? '1' : '0')
                : $default
        );

        return new ContextWriteService(
            $this->db,
            new SettingsReader($settings),
            new ActiveScopeRepository($this->db),
            new DiscussionContextRepository($this->db)
        );
    }

    /**
     * @param list<string> $relevance
     */
    private function dto(string $primary, ?int $revision = null, array $relevance = []): WikiContextDto
    {
        return new WikiContextDto(
            $primary,
            self::GRAPH,
            $revision,
            $relevance
        );
    }

    private function expectWriteError(string $reason, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected {$reason}");
        } catch (ContextWriteException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    private const GRAPH = '11111111-1111-4111-8111-111111111111';
    private const COMMUNITY = '22222222-2222-4222-8222-222222222222';
    private const TOYOTA_ROOT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const TOYOTA_CAMRY = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const TOYOTA_BRAKES = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const LABOR_TEXAS = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    private const LABOR_TOPIC = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
}
