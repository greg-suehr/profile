<?php

declare(strict_types=1);

namespace App\Shared\PghPoets;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'pghpoets:check', description: 'Check the pghpoets.com catalog: every claim cites a source that exists.')]
final class CheckCatalogCommand extends Command
{
    public function __construct(private readonly Catalog $catalog)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $problems = $this->catalog->problems();

        if ([] === $problems) {
            $io->success(\sprintf('%d poets, no problems.', \count($this->catalog->poets())));

            return Command::SUCCESS;
        }

        $io->listing($problems);
        $io->error(\sprintf('%d problem(s).', \count($problems)));

        return Command::FAILURE;
    }
}
