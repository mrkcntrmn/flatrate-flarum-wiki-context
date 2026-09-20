<?php

namespace FlatRate\WikiContext\Command;

use FlatRate\WikiContext\Support\FeatureGates;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

final class ProjectionStatusCommand extends AbstractCommand
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private ConnectionInterface $db
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('flatrate:wiki:projection-status')
            ->setDescription('Report WIKI projection feature gates and active graph pointer (no secrets).');
    }

    protected function fire(): void
    {
        foreach (FeatureGates::all() as $key) {
            $this->info($key . '=' . ($this->settings->get($key) ? 'true' : 'false'));
        }

        $states = $this->db->table('flatrate_wiki_projection_state')->get();
        if ($states->isEmpty()) {
            $this->info('active_graph=none');
        } else {
            foreach ($states as $state) {
                $this->info(sprintf(
                    'community=%s active_graph=%s generation=%s',
                    $state->community_uuid,
                    $state->active_graph_version_uuid,
                    $state->active_generation
                ));
            }
        }

        $this->info('PRODUCTION_MUTATION=false');
    }
}
