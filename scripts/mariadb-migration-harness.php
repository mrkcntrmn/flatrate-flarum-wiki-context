<?php

/**
 * Disposable MariaDB migration harness.
 * Creates a minimal discussions table, runs extension migrations up/down/reinstall
 * with the configured Illuminate table prefix.
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require dirname(__DIR__) . '/vendor/autoload.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'wiki_ctx';
$username = getenv('DB_USERNAME') ?: 'wiki';
$password = getenv('DB_PASSWORD') ?: 'wiki';
$prefix = getenv('TABLE_PREFIX');
if ($prefix === false) {
    $prefix = '';
}

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $host,
    'port' => $port,
    'database' => $database,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => $prefix,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$schema = $capsule->schema();

$migrationFiles = glob(dirname(__DIR__) . '/migrations/*.php');
sort($migrationFiles);

function dropAllWikiTables($schema, string $prefix): void
{
    // Constraint names are DB-global in MariaDB. Wipe both prefix variants when
    // re-running the harness against the same database.
    $logical = [
        'flatrate_wiki_preview_acceptance',
        'flatrate_wiki_discussion_context_audit',
        'flatrate_wiki_discussion_relevance',
        'flatrate_wiki_discussion_context',
        'flatrate_wiki_projection_chunks',
        'flatrate_wiki_projection_nonces',
        'flatrate_wiki_scope_ancestors',
        'flatrate_wiki_scopes',
        'flatrate_wiki_projection_state',
        'flatrate_wiki_graph_versions',
        'discussions',
    ];

    $db = Capsule::connection();
    foreach ([$prefix, '', 'flarum_'] as $p) {
        foreach ($logical as $table) {
            $physical = $p . $table;
            try {
                $db->statement('SET FOREIGN_KEY_CHECKS=0');
                $db->statement("DROP TABLE IF EXISTS `{$physical}`");
            } finally {
                $db->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
}

function ensureDiscussions($schema): void
{
    if ($schema->hasTable('discussions')) {
        return;
    }
    $schema->create('discussions', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title', 200)->nullable();
    });
}

function runMigrations(array $files, $schema, string $direction): void
{
    $ordered = $direction === 'up' ? $files : array_reverse($files);
    foreach ($ordered as $file) {
        $migration = require $file;
        if (!isset($migration[$direction]) || !is_callable($migration[$direction])) {
            throw new RuntimeException("Missing {$direction} in {$file}");
        }
        $migration[$direction]($schema);
    }
}

function assertSchema($schema, string $prefix): void
{
    $required = [
        'flatrate_wiki_graph_versions',
        'flatrate_wiki_projection_state',
        'flatrate_wiki_scopes',
        'flatrate_wiki_scope_ancestors',
        'flatrate_wiki_projection_nonces',
        'flatrate_wiki_projection_chunks',
        'flatrate_wiki_discussion_context',
        'flatrate_wiki_discussion_relevance',
        'flatrate_wiki_discussion_context_audit',
        'flatrate_wiki_preview_acceptance',
    ];
    foreach ($required as $table) {
        if (!$schema->hasTable($table)) {
            throw new RuntimeException("missing table {$prefix}{$table}");
        }
    }

    // Uniqueness / index smoke via insert conflicts where practical
    $db = Capsule::connection();
    $db->table('flatrate_wiki_graph_versions')->insert([
        'graph_version_uuid' => '11111111-1111-4111-8111-111111111111',
        'community_uuid' => '22222222-2222-4222-8222-222222222222',
        'generation' => 1,
        'parent_graph_version_uuid' => null,
        'schema_version' => 'v1',
        'content_digest' => 'digest-a',
        'node_count' => 0,
        'scope_count' => 0,
        'ancestor_count' => 0,
        'alias_count' => 0,
        'chunk_count' => 0,
        'status' => 'staging',
        'received_at' => date('Y-m-d H:i:s'),
        'validated_at' => null,
        'activated_at' => null,
    ]);

    $db->table('flatrate_wiki_projection_state')->insert([
        'community_uuid' => '22222222-2222-4222-8222-222222222222',
        'active_graph_version_uuid' => '11111111-1111-4111-8111-111111111111',
        'active_generation' => 1,
        'activated_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $db->table('flatrate_wiki_preview_acceptance')->insert([
        'accepted_by_user_id' => 1,
        'accepted_at' => date('Y-m-d H:i:s'),
        'active_graph_version_uuid' => '11111111-1111-4111-8111-111111111111',
        'extension_build_id' => 'build-test',
        'wiki_contract_version' => 'v1',
        'directory_display_policy_digest' => 'policy-digest',
        'audience_profiles_verified' => 'guest,standard_member',
        'fixture_results_digest' => 'fixtures-digest',
        'status' => 'PASS',
    ]);

    echo "SCHEMA_ASSERT=PASS prefix='{$prefix}'\n";
}

dropAllWikiTables($schema, $prefix);
ensureDiscussions($schema);
runMigrations($migrationFiles, $schema, 'up');
assertSchema($schema, $prefix);

runMigrations($migrationFiles, $schema, 'down');
foreach ([
    'flatrate_wiki_graph_versions',
    'flatrate_wiki_preview_acceptance',
] as $t) {
    if ($schema->hasTable($t)) {
        throw new RuntimeException("table remained after down: {$t}");
    }
}

ensureDiscussions($schema);
runMigrations($migrationFiles, $schema, 'up');
assertSchema($schema, $prefix);

echo "HARNESS_OK prefix='{$prefix}'\n";
