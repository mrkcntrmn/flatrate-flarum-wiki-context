<?php

namespace FlatRate\WikiContext\Command;

use Flarum\Console\AbstractCommand;

final class ProjectionReconcileCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('flatrate:wiki:projection-reconcile')
            ->setDescription('Read-only projection reconciliation skeleton (default dry-run).');
    }

    protected function fire(): void
    {
        $this->info('projection_reconcile=SKELETON_ONLY');
        $this->info('dry_run=true');
        $this->info('PRODUCTION_MUTATION=false');
    }
}
