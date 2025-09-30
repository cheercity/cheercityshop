<?php

namespace App\Command;

use App\Service\NavService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:nav:rebuild', description: 'Rebuild the navigation cache (clears and repopulates).')]
final class NavRebuildCommand extends Command
{
    protected static $defaultName = 'app:nav:rebuild';

    public function __construct(private NavService $navService, private CacheItemPoolInterface $cache)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Rebuild the navigation cache (clears and repopulates).')
            ->addOption('layout', 'l', InputOption::VALUE_REQUIRED, 'Layout name to read from FileMaker', 'sym_Navigation')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'JSON encoded filter to pass to FileMaker', json_encode(['Status' => '1']))
            ->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Cache TTL in seconds (0 = no expiry in set call)', 300)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $layout = (string) $input->getOption('layout');
        $filterJson = (string) $input->getOption('filter');
        $ttl = (int) $input->getOption('ttl');

        $output->writeln(sprintf('Rebuilding nav cache for layout=%s ttl=%d', $layout, $ttl));

        $filter = null;
        try {
            $filter = json_decode($filterJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $output->writeln('<error>Invalid JSON for --filter: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        // Compute cache key consistent with NavService
        $cacheKey = 'nav_main_'.md5(json_encode([$layout, $filter]));

        // Delete existing cache item if present
        try {
            $deleted = $this->cache->deleteItem($cacheKey);
            $output->writeln($deleted ? '<info>Deleted existing cache item: '.$cacheKey.'</info>' : '<comment>No cache item found to delete: '.$cacheKey.'</comment>');
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to delete cache item: '.$e->getMessage().'</error>');
            // continue, attempt rebuild
        }

        try {
            $tree = $this->navService->getMenu($layout, $filter, $ttl);
            $count = is_array($tree) ? count($tree) : 0;
            $output->writeln(sprintf('<info>Rebuilt nav cache: %d root nodes</info>', $count));
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to rebuild nav: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
