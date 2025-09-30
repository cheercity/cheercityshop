<?php

namespace App\Command;

use App\Service\NavService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:nav-diagnose', description: 'Diagnose navigation tree produced by NavService')]
final class NavDiagnoseCommand extends Command
{
    public function __construct(private NavService $nav)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('layout', null, InputOption::VALUE_OPTIONAL, 'Layout name', 'sym_Navigation')
            ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'JSON filter for find()', '{}')
            ->addOption('ttl', null, InputOption::VALUE_OPTIONAL, 'cache ttl seconds', 300)
            ->addOption('dump', null, InputOption::VALUE_NONE, 'Dump full JSON tree to stdout');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $layout = (string) $input->getOption('layout');
        $filterJson = (string) $input->getOption('filter');
        $ttl = (int) $input->getOption('ttl');
        $dump = (bool) $input->getOption('dump');

        $filter = [];
        if ('' !== $filterJson) {
            try {
                $decoded = json_decode($filterJson, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $filter = $decoded;
                }
            } catch (\Throwable $e) {
                $io->error('Invalid --filter JSON: '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        $io->title('Navigation Diagnose');
        $io->text(sprintf('Calling NavService::getMenu(layout=%s, filter=%s, ttl=%d)', $layout, json_encode($filter), $ttl));

        try {
            $tree = $this->nav->getMenu($layout, $filter, $ttl);
        } catch (\Throwable $e) {
            $io->error('NavService threw an exception: '.$e->getMessage());
            // write to log as well
            @file_put_contents(dirname(__DIR__, 2).'/var/log/nav_diagnose.log', sprintf("[%s] exception: %s\n", date('c'), $e->getMessage()), FILE_APPEND);

            return Command::FAILURE;
        }

        // compute stats
        $stats = [
            'total' => 0,
            'max_depth' => 0,
            'missing_id' => 0,
            'missing_href' => 0,
        ];

        $walk = function (array $nodes, int $depth = 0) use (&$walk, &$stats) {
            $stats['max_depth'] = max($stats['max_depth'], $depth);
            foreach ($nodes as $n) {
                ++$stats['total'];
                if (empty($n['id'])) {
                    ++$stats['missing_id'];
                }
                if (empty($n['href'])) {
                    ++$stats['missing_href'];
                }
                if (!empty($n['children'])) {
                    $walk($n['children'], $depth + 1);
                }
            }
        };

        $walk($tree, 0);

        $io->section('Stats');
        $io->table(['metric', 'value'], [
            ['total nodes', $stats['total']],
            ['max depth', $stats['max_depth']],
            ['missing id', $stats['missing_id']],
            ['missing href', $stats['missing_href']],
        ]);

        // Dump JSON if requested
        if ($dump) {
            $io->section('JSON Dump');
            $output->writeln(json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        // write a short log
        $logPath = dirname(__DIR__, 2).'/var/log/nav_diagnose.log';
        @file_put_contents($logPath, sprintf("[%s] nav_diagnose layout=%s total=%d max_depth=%d missing_id=%d missing_href=%d\n", date('c'), $layout, $stats['total'], $stats['max_depth'], $stats['missing_id'], $stats['missing_href']), FILE_APPEND);

        $io->success('Diagnosis complete. Log written to var/log/nav_diagnose.log');

        return Command::SUCCESS;
    }
}
