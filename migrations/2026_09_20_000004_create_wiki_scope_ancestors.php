<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('flatrate_wiki_scope_ancestors', function (Blueprint $table) {
            $table->char('graph_version_uuid', 36);
            $table->char('descendant_scope_uuid', 36);
            $table->char('ancestor_scope_uuid', 36);
            $table->unsignedInteger('depth');

            $table->primary(
                ['graph_version_uuid', 'descendant_scope_uuid', 'ancestor_scope_uuid'],
                'wiki_scope_ancestors_pk'
            );
            // Given target ancestor S, which assigned scopes are descendants of S?
            $table->index(
                ['graph_version_uuid', 'ancestor_scope_uuid', 'descendant_scope_uuid'],
                'wiki_scope_ancestors_lookup_idx'
            );
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_scope_ancestors');
    },
];
