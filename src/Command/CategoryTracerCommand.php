<?php
namespace App\Command;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use App\Service\NavService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CategoryTracerCommand extends Command
{
    protected static $defaultName = 'app:trace:category-products';
    protected static $defaultDescription = 'Trace a category slug -> FM Category_ID and list product samples from sym_Artikel';

    public function __construct(private NavService $navService, private FileMakerClient $fm, private FileMakerLayoutRegistry $layouts)
    {
        // Provide an explicit name to the base Command to avoid empty-name registration issues
        parent::__construct('app:trace:category-products');
    }

    protected function configure(): void
    {
        $this->addArgument('slug', InputArgument::REQUIRED, 'Category slug (alias) to trace');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $slug = (string) $input->getArgument('slug');

        $io->title('Category tracer');
        $io->text('Resolving slug: '.$slug);

        $aliasMap = $this->navService->getAliasToCatSort();
        if (empty($aliasMap)) {
            $io->text('Alias map empty — forcing NavService::getMenu() to build mapping (bypass cache)');
            $this->navService->getMenu(null, ['Status' => '1'], 0);
            $aliasMap = $this->navService->getAliasToCatSort();
        }

        $catSort = $aliasMap[strtolower($slug)] ?? null;
        if (null === $catSort) {
            $io->error(sprintf('No cat_sort mapping found for slug "%s"', $slug));
            return Command::FAILURE;
        }

        $io->success(sprintf('Found mapping: slug="%s" -> Category_ID (cat_sort) = %s', $slug, $catSort));

        // Query FileMaker
        try {
            $layout = $this->layouts->get('artikel', 'sym_Artikel');
        } catch (\Throwable $e) {
            $io->error('Failed to resolve layout "sym_Artikel": '.$e->getMessage());
            return Command::FAILURE;
        }

        try {
            $res = $this->fm->find($layout, ['Category_ID' => $catSort], ['limit' => 200]);
        } catch (\Throwable $e) {
            $io->error('FileMaker query failed: '.$e->getMessage());
            return Command::FAILURE;
        }

        $rows = $res['response']['data'] ?? [];
        $count = count($rows);
        $io->section('Products returned: '.$count);

        if ($count === 0) {
            $io->warning('No products found for this category.');
            return Command::SUCCESS;
        }

        $samples = array_slice($rows, 0, 5);
        $io->text('Showing up to 5 sample products (field keys & detected SKU-like values if present)');

        foreach ($samples as $idx => $r) {
            $nr = $idx + 1;
            $fd = $r['fieldData'] ?? [];
            $io->writeln(sprintf('--- Product %d ---', $nr));

            // Try to detect a SKU-like field
            $sku = null;
            foreach ($fd as $k => $v) {
                $lk = strtolower($k);
                if (str_contains($lk, 'sku') || str_contains($lk, 'artikel') || str_contains($lk, 'artnr') || str_contains($lk, 'art') || str_contains($lk, 'ean') || str_contains($lk, 'article')) {
                    $sku = (string) $v;
                    break;
                }
            }

            if ($sku) {
                $io->writeln('Detected SKU-like value: '.$sku);
            } else {
                $io->writeln('No SKU-like field detected. Printing first 5 fields:');
                $i = 0;
                foreach ($fd as $k => $v) {
                    $io->writeln(sprintf('  %s: %s', $k, is_scalar($v) ? (string) $v : json_encode($v)));
                    $i++;
                    if ($i >= 5) {
                        break;
                    }
                }
            }
        }

        // Print instrumentation counters for this run (helpful for perf analysis)
        try {
            if (method_exists($this->fm, 'getCounters')) {
                $io->section('FileMakerClient instrumentation');
                $c = $this->fm->getCounters();
                foreach ($c as $k => $v) {
                    $io->writeln(sprintf('%s: calls=%d cache_hits=%d', $k, $v['calls'], $v['cache_hits']));
                }
            }
        } catch (\Throwable $e) {
            // don't fail the command for counters issues
            $io->warning('Failed to fetch instrumentation counters: '.$e->getMessage());
        }

        return Command::SUCCESS;
    }
}
