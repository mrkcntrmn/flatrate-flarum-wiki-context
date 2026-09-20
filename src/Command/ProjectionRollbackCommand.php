<?php

namespace FlatRate\WikiContext\Command;

use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * CLI-only rollback. No remote/public rollback API.
 */
final class ProjectionRollbackCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('flatrate:wiki:projection-rollback')
            ->setDescription('Operator CLI rollback to a historical accepted graph version.')
            ->addArgument('version', InputArgument::REQUIRED, 'Target graph_version_uuid')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Operator reason', 'unspecified')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report without switching');
    }

    protected function fire(): void
    {
        $version = (string) $this->input->getArgument('version');
        $reason = (string) $this->input->getOption('reason');
        $dryRun = (bool) $this->input->getOption('dry-run');

        $this->info('projection_rollback=SKELETON_ONLY');
        $this->info('target_version=' . $version);
        $this->info('reason=' . $reason);
        $this->info('dry_run=' . ($dryRun ? 'true' : 'false'));
        $this->info('remote_rollback_api=false');
        $this->error('IMPLEMENTATION_STATE=deferred_full_rollback_transaction');
        // Fail closed for non-dry-run until fixtures exist.
        if (!$dryRun) {
            throw new \RuntimeException('projection_rollback_not_implemented_in_r1');
        }
    }
}
