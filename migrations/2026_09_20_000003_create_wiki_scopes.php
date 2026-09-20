<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
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
            $table->unique(['graph_version_uuid', 'scope_fingerprint'], 'wiki_scopes_fingerprint_uq');
            $table->unique(['graph_version_uuid', 'route_key'], 'wiki_scopes_route_uq');
            $table->index(['graph_version_uuid', 'primary_parent_scope_uuid'], 'wiki_scopes_parent_idx');
            $table->index(['community_uuid', 'scope_uuid'], 'wiki_scopes_community_scope_idx');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_scopes');
    },
];
