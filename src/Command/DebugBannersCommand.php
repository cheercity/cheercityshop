<?php

namespace App\Command;

use App\Service\BannerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-banners',
    description: 'Debug banner data from FileMaker sym_Banner layout'
)]
class DebugBannersCommand extends Command
{
    private BannerService $bannerService;

    public function __construct(BannerService $bannerService)
    {
        parent::__construct();
        $this->bannerService = $bannerService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('position', InputArgument::OPTIONAL, 'Banner position to fetch (default: Main)', 'Main')
            ->addOption('all-positions', null, InputOption::VALUE_NONE, 'Show banners for all positions')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show banner statistics')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Bypass cache and get fresh data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $position = $input->getArgument('position');
        $showAllPositions = $input->getOption('all-positions');
        $showStats = $input->getOption('stats');
        $fresh = $input->getOption('fresh');
        $ttl = $fresh ? 0 : 300;

        $io->title('Banner Debug Tool');

        // Show statistics if requested
        if ($showStats) {
            $this->showStats($io);
        }

        // Show all positions if requested
        if ($showAllPositions) {
            $this->showAllPositions($io, $ttl);

            return Command::SUCCESS;
        }

        // Show specific position
        $this->showPosition($io, $position, $ttl);

        return Command::SUCCESS;
    }

    private function showStats(SymfonyStyle $io): void
    {
        $io->section('Banner Statistiken');

        $stats = $this->bannerService->getBannerStats();

        if (isset($stats['error'])) {
            $io->error('Fehler beim Laden der Statistiken: '.$stats['error']);

            return;
        }

        $io->info(sprintf('Gesamt: %d Banner', $stats['total']));
        $io->info(sprintf('Aktiv: %d Banner', $stats['active']));

        if (!empty($stats['positions'])) {
            $io->section('Banner nach Position');
            $tableData = [];
            foreach ($stats['positions'] as $pos => $counts) {
                $tableData[] = [
                    $pos,
                    $counts['total'],
                    $counts['active'],
                    $counts['total'] > 0 ? round(($counts['active'] / $counts['total']) * 100, 1).'%' : '0%',
                ];
            }

            $io->table(['Position', 'Gesamt', 'Aktiv', 'Aktiv %'], $tableData);
        }
    }

    private function showAllPositions(SymfonyStyle $io, int $ttl): void
    {
        $positions = $this->bannerService->getAllBannerPositions($ttl);

        if (empty($positions)) {
            $io->warning('Keine Banner-Positionen gefunden');

            return;
        }

        foreach ($positions as $position) {
            $this->showPosition($io, $position, $ttl);
        }
    }

    private function showPosition(SymfonyStyle $io, string $position, int $ttl): void
    {
        $io->section(sprintf('Banner für Position: %s', $position));

        $banners = $this->bannerService->getBanners($position, $ttl);

        if (empty($banners)) {
            $io->warning(sprintf('Keine aktiven Banner für Position "%s" gefunden', $position));
            $io->note('Überprüfe, dass Banner mit Aktiv = 1 und position = "'.$position.'" in FileMaker existieren.');

            return;
        }

        $io->success(sprintf('%d Banner gefunden für Position "%s"', count($banners), $position));

        // Create table data
        $tableData = [];
        foreach ($banners as $banner) {
            $tableData[] = [
                $banner['id'],
                $banner['sortorder'],
                $banner['title'] ?: '(leer)',
                $banner['subtitle'] ?: '(leer)',
                strlen($banner['description']) > 40
                    ? substr($banner['description'], 0, 37).'...'
                    : ($banner['description'] ?: '(leer)'),
                $banner['image'] ? '✓' : '✗',
                $banner['link'] ? '✓' : '✗',
                $banner['button_text'] ?: 'View Collection',
                '1' === $banner['aktiv'] ? '✓' : '✗',
            ];
        }

        $io->table([
            'ID', 'Sort', 'Titel', 'Untertitel', 'Beschreibung', 'Bild', 'Link', 'Button', 'Aktiv',
        ], $tableData);

        // Show detailed info for first banner
        if (!empty($banners)) {
            $firstBanner = $banners[0];
            $io->section('Detailansicht für ersten Banner:');

            $details = [
                ['ID', $firstBanner['id']],
                ['Titel', $firstBanner['title'] ?: '(leer)'],
                ['Untertitel', $firstBanner['subtitle'] ?: '(leer)'],
                ['Beschreibung', $firstBanner['description'] ?: '(leer)'],
                ['Bild', $firstBanner['image'] ?: '(leer)'],
                ['Link', $firstBanner['link'] ?: '(leer)'],
                ['Button Text', $firstBanner['button_text'] ?: 'View Collection'],
                ['Position', $firstBanner['position']],
                ['Sortorder', $firstBanner['sortorder']],
                ['Aktiv', $firstBanner['aktiv']],
            ];

            $io->definitionList(...$details);
        }
    }
}
