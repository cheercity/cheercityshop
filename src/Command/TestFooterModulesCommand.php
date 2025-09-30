<?php

namespace App\Command;

use App\Service\NavService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:footer-modules',
    description: 'Test footer modules from FileMaker',
)]
class TestFooterModulesCommand extends Command
{
    public function __construct(
        private NavService $navService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Testing Footer Modules');

        try {
            $io->section('Fetching footer modules...');
            $modules = $this->navService->getFooterModules(0); // TTL = 0 to bypass cache

            $io->info('Found '.count($modules).' module groups');

            if (empty($modules)) {
                $io->warning('No footer modules found!');
                $io->text([
                    'Possible reasons:',
                    '- FileMaker connection issues',
                    '- No records with Published=1 AND Footer_Status=1',
                    '- sym_Module layout does not exist',
                    '- Field names mismatch',
                ]);

                return Command::FAILURE;
            }

            foreach ($modules as $moduleName => $items) {
                $io->section("Module: $moduleName (".count($items).' items)');

                foreach ($items as $item) {
                    $io->text([
                        'Title: '.($item['titel'] ?? '(no title)'),
                        'Link: '.($item['lnk'] ?? '(no link)'),
                        'Sortorder: '.($item['Sortorder'] ?? '(no sort)'),
                        'Published: '.($item['Published'] ?? '(not set)'),
                        'Footer_Status: '.($item['Footer_Status'] ?? '(not set)'),
                        '---',
                    ]);
                }
            }

            $io->success('Footer modules test completed successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to get footer modules: '.$e->getMessage());
            $io->text('Exception: '.get_class($e));
            $io->text('File: '.$e->getFile().':'.$e->getLine());
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
