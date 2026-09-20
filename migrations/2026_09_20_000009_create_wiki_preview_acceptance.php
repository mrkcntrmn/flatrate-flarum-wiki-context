<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->create('flatrate_wiki_preview_acceptance', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('accepted_by_user_id');
            $table->dateTime('accepted_at');
            $table->char('active_graph_version_uuid', 36);
            $table->string('extension_build_id', 128);
            $table->string('wiki_contract_version', 64);
            $table->string('directory_display_policy_digest', 128);
            $table->string('audience_profiles_verified', 255);
            $table->string('fixture_results_digest', 128);
            $table->string('status', 32);

            $table->index(['status', 'accepted_at'], 'wiki_preview_acceptance_status_idx');
            $table->index('active_graph_version_uuid');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('flatrate_wiki_preview_acceptance');
    },
];
