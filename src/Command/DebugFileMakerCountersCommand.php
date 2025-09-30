<?php
namespace App\Command;

use App\Service\FileMakerClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DebugFileMakerCountersCommand extends Command
{
    protected static $defaultName = 'app:debug:fm-counters';

    public function __construct(private FileMakerClient $fm)
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this->setDescription('Show request-local FileMaker instrumentation counters and optionally reset them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $c = $this->fm->getCounters();
        $io->section('FileMakerClient counters');
        foreach ($c as $k => $v) {
            $io->writeln(sprintf('%s: calls=%d cache_hits=%d', $k, $v['calls'], $v['cache_hits']));
        }

        // Reset for convenience (so repeated runs are clear)
        $this->fm->resetInstrumentation();
        $io->text('Instrumentation counters reset.');

        return Command::SUCCESS;
    }
}
