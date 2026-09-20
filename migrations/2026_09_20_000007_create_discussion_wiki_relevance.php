<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('flatrate_wiki_discussion_relevance', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id');
            $table->char('relevant_scope_uuid', 36);
            $table->string('provenance', 64);
            $table->unsignedInteger('actor_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('removed_at')->nullable();

            $table->primary(['discussion_id', 'relevant_scope_uuid'], 'wiki_relevance_pk');
            $table->index(['relevant_scope_uuid', 'removed_at'], 'wiki_relevance_scope_active_idx');

            $table->foreign('discussion_id')
                ->references('id')
                ->on('discussions')
                ->onDelete('cascade');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_discussion_relevance');
    },
];
