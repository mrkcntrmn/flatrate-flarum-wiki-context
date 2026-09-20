<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('flatrate_wiki_projection_state', function (Blueprint $table) {
            $table->char('community_uuid', 36);
            $table->char('active_graph_version_uuid', 36);
            $table->unsignedBigInteger('active_generation');
            $table->dateTime('activated_at');
            $table->dateTime('updated_at');

            $table->primary('community_uuid');
            $table->index('active_graph_version_uuid');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_projection_state');
    },
];
