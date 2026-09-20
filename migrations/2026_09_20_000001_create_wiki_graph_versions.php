<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('flatrate_wiki_graph_versions', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
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

            $table->primary('graph_version_uuid');
            $table->index(['community_uuid', 'generation']);
            $table->index(['community_uuid', 'status']);
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_graph_versions');
    },
];
