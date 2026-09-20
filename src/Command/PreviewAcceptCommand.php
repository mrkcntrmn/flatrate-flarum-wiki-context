<?php

namespace FlatRate\WikiContext\Command;

use Flarum\Console\AbstractCommand;

/**
 * Fail-closed until required fixtures and validation checks exist.
 */
final class PreviewAcceptCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName('flatrate:wiki:preview-accept')
            ->setDescription('Record admin ghost-preview acceptance receipt (fail-closed in R1).');
    }

    protected function fire(): void
    {
        $this->error('preview_accept=SKELETON_ONLY');
        $this->error('IMPLEMENTATION_STATE=fixtures_and_validation_not_yet_available');
        throw new \RuntimeException('preview_accept_not_implemented_in_r1');
    }
}
