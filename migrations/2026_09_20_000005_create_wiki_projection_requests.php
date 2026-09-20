<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('flatrate_wiki_projection_nonces', function (Blueprint $table) {
            $table->char('nonce_hash', 64);
            $table->dateTime('expires_at');
            $table->dateTime('created_at');
            $table->primary('nonce_hash');
            $table->index('expires_at');
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

            $table->primary(['graph_version_uuid', 'chunk_index'], 'wiki_projection_chunks_pk');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_projection_chunks');
        $schema->dropIfExists('flatrate_wiki_projection_nonces');
    },
];
