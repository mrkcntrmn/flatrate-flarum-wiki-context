<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
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

            $table->index(['discussion_id', 'created_at'], 'wiki_context_audit_discussion_idx');
            // Intentionally no post body / PII columns.
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_discussion_context_audit');
    },
];
