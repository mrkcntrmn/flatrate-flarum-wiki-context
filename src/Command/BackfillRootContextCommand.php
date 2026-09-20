<?php

namespace FlatRate\WikiContext\Command;

use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Deterministic brand-tag → brand-root scope backfill only.
 * DRY_RUN=true by default. No LLM / title classification.
 */
final class BackfillRootContextCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('flatrate:wiki:backfill-root-context')
            ->setDescription('Deterministic root-context backfill (default dry-run).')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply changes (default is dry-run)');
    }

    protected function fire(): void
    {
        $apply = (bool) $this->input->getOption('apply');
        $this->info('backfill_root_context=SKELETON_ONLY');
        $this->info('dry_run=' . ($apply ? 'false' : 'true'));
        $this->info('allowed_mapping=accepted_brand_tag_to_brand_root_scope');
        $this->info('forbidden=title_classification,llm_guesses');
        if ($apply) {
            throw new \RuntimeException('backfill_apply_not_implemented_in_r1');
        }
    }
}
