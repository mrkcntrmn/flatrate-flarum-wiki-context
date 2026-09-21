<?php

namespace FlatRate\WikiContext\Command;

use FlatRate\WikiContext\Projection\ProjectionService;
use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;

final class ProjectionReconcileCommand extends AbstractCommand
{
    public function __construct(private ProjectionService $projection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('flatrate:wiki:projection-reconcile')
            ->setDescription('Read-only projection reconciliation against active graph rows.')
            ->addOption('community', null, InputOption::VALUE_REQUIRED, 'Optional community UUID filter');
    }

    protected function fire(): void
    {
        $community = $this->input->getOption('community');
        $report = $this->projection->reconcile(
            is_string($community) ? $community : null
        );

        $this->info('projection_reconcile=IMPLEMENTED');
        $this->info('dry_run=true');
        $this->info('PRODUCTION_MUTATION=false');
        $this->info('communities=' . count($report['communities'] ?? []));

        foreach ($report['communities'] as $row) {
            $this->info(sprintf(
                'community=%s active_graph=%s ok=%s scopes=%s/%s ancestors=%s/%s integrity=%s',
                $row['community_uuid'],
                $row['active_graph_version_uuid'],
                $row['ok'] ? 'true' : 'false',
                $row['actual_scope_count'],
                $row['manifest_scope_count'] ?? 'null',
                $row['actual_ancestor_count'],
                $row['manifest_ancestor_count'] ?? 'null',
                $row['integrity_status']
            ));
            if (!empty($row['integrity_reason'])) {
                $this->error('integrity_reason=' . $row['integrity_reason']);
            }
        }
    }
}
