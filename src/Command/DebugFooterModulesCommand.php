<?php

namespace App\Command;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:debug-footer-modules', description: 'Dump sym_Module rows for footer debugging')]
final class DebugFooterModulesCommand extends Command
{
    public function __construct(private FileMakerClient $fm, private FileMakerLayoutRegistry $layouts)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $layout = $this->layouts->get('module', 'sym_Module');
        $io->title(sprintf('Debug %s rows (Published = 1)', $layout));

        try {
            // Use list() to avoid complications with compound find payloads in this debug helper
            $data = $this->fm->list($layout, 1000, 1);
        } catch (\Throwable $e) {
            $io->error('FileMaker query failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $rows = [];
        if (isset($data['response']['data']) && is_array($data['response']['data'])) {
            foreach ($data['response']['data'] as $rec) {
                $rows[] = $rec['fieldData'] ?? [];
            }
        }

        if (empty($rows)) {
            $io->warning(sprintf('No rows returned for %s with Published=1', $layout));

            return Command::SUCCESS;
        }

        // Group by Modul
        $groups = [];
        foreach ($rows as $row) {
            $mod = trim((string) ($row['Modul'] ?? 'Ungrouped'));
            if ('' === $mod) {
                $mod = 'Ungrouped';
            }
            if (!isset($groups[$mod])) {
                $groups[$mod] = [];
            }
            $groups[$mod][] = $row;
        }

        // Sort each group by Sortorder (numeric asc)
        foreach ($groups as $mod => &$items) {
            usort($items, function ($a, $b) {
                return (int) ($a['Sortorder'] ?? 0) <=> (int) ($b['Sortorder'] ?? 0);
            });
        }
        unset($items);

        // Output grouped lists with Modul heading
        $io->success(sprintf('Found %d rows across %d modules', count($rows), count($groups)));
        foreach ($groups as $mod => $items) {
            $io->section($mod);
            $table = [];
            foreach ($items as $idx => $r) {
                $table[] = [
                    $idx + 1,
                    $r['Sortorder'] ?? '',
                    $r['titel'] ?? $r['Titel'] ?? '',
                    $r['link'] ?? $r['lnk'] ?? $r['cat_link'] ?? '',
                ];
            }
            $io->table(['#', 'Sortorder', 'Title', 'Link'], $table);
        }

        return Command::SUCCESS;
    }
}
