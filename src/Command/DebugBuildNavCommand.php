<?php
namespace App\Command;

use App\Service\FileMakerClient;
use App\Service\NavService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DebugBuildNavCommand extends Command
{
    protected static $defaultName = 'app:debug:build-nav';

    public function __construct(private NavService $navService, private FileMakerClient $fm)
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this->setDescription('Build NavService::getMenu() and print FileMaker counters. Use --bypass to bypass cache.');
        $this->addOption('bypass', null, InputOption::VALUE_NONE, 'When set, call getMenu() with TTL=0 (bypass cache)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Force build nav and show FileMaker counters');

        // Reset counters for a clean run
        if (method_exists($this->fm, 'resetInstrumentation')) {
            $this->fm->resetInstrumentation();
        }

        // Build menu; respect --bypass option to force TTL=0
        $bypass = (bool) $input->getOption('bypass');
        try {
            if ($bypass) {
                $this->navService->getMenu(null, ['Status' => '1'], 0);
            } else {
                // default TTL (NavService default) to allow persistent cache hits
                $this->navService->getMenu();
            }
            $io->success('NavService::getMenu() executed'.($bypass ? ' (bypassed cache)' : ' (cache enabled)'));
        } catch (\Throwable $e) {
            $io->error('NavService::getMenu() failed: '.$e->getMessage());
        }

        // Print counters
        if (method_exists($this->fm, 'getCounters')) {
            $io->section('FileMakerClient counters');
            $c = $this->fm->getCounters();
            foreach ($c as $k => $v) {
                $io->writeln(sprintf('%s: calls=%d cache_hits=%d', $k, $v['calls'], $v['cache_hits']));
            }
        }

        return Command::SUCCESS;
    }
}
