<?php
namespace App\Katzen\Command;

use App\Katzen\DataFixtures\DependencyPurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'katzen:fixtures:purge',
    description: 'Purge database with smart dependency handling'
)]
class PurgeFixturesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private DependencyPurger $purger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Purging database...');
        
        $this->purger->purge();
        
        $output->writeln('Database purged successfully');
        
        return Command::SUCCESS;
    }
}
