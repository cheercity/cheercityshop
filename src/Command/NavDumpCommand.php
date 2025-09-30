<?php

namespace App\Command;

use App\Service\NavService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:nav:dump', description: 'Dump alias -> cat_sort map from NavService')]
final class NavDumpCommand extends Command
{
    public function __construct(private NavService $nav, private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'TTL to use when fetching menu (0 = bypass cache)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ttl = (int) $input->getOption('ttl');

        // Force getMenu to run so aliasMap is built
        $this->nav->getMenu(null, ['Status' => '1'], $ttl);
        $map = $this->nav->getAliasToCatSort();

        $output->writeln(json_encode(['count' => count($map), 'map' => $map], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }
}
