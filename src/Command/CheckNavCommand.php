<?php
namespace App\Command;

use App\Service\NavService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:check-nav', description: 'Check NavService / FileMaker integration and dump the menu (for debugging)')]
final class CheckNavCommand extends Command
{
    public function __construct(private NavService $nav, private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('NavService / FileMaker check');

        try {
            $items = $this->nav->getMenu();
        } catch (\Throwable $e) {
            $this->logger->error('CheckNavCommand: getMenu threw exception: ' . $e->getMessage(), ['exception' => $e]);
            $io->error('getMenu failed with exception: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (empty($items)) {
            $io->warning('getMenu returned an empty array. Possible causes:' . PHP_EOL .
                '- missing/invalid FM_* env vars' . PHP_EOL .
                '- FileMaker authentication/network error' . PHP_EOL .
                '- layout/filter returned no records'
            );
            $this->logger->warning('CheckNavCommand: getMenu returned empty array');
        } else {
            $io->success(sprintf('getMenu returned %d root items', count($items)));
            $output->writeln(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return Command::SUCCESS;
    }
}
