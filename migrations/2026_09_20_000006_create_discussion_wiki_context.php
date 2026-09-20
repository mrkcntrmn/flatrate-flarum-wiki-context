<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('flatrate_wiki_discussion_context', function (Blueprint $table) use ($schema) {
            $table->unsignedInteger('discussion_id');
            $table->char('primary_scope_uuid', 36);
            $table->unsignedInteger('context_revision')->default(1);
            $table->string('provenance', 64);
            $table->unsignedInteger('assigned_by_user_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->primary('discussion_id');
            $table->index('primary_scope_uuid');

            // Prefix-aware FK via Illuminate; do not hard-code unprefixed SQL.
            $table->foreign('discussion_id')
                ->references('id')
                ->on('discussions')
                ->onDelete('cascade');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_discussion_context');
    },
];
