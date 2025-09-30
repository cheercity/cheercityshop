<?php

namespace App\Twig;

use App\Controller\DevNavController;
use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use App\Service\NavService;
use Psr\Log\LoggerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class NavAnalysisExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private NavService $navService,
        private DevNavController $devNavController,
        private FileMakerClient $fm,
        private FileMakerLayoutRegistry $layouts,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function getGlobals(): array
    {
        try {
            // Use NavService which provides caching and a normalized menu structure
            $menu = $this->navService->getMenu();

            // Normalize NavService nodes to the shape templates expect: value/link/children
            $mapNode = function (array $node) use (&$mapNode): array {
                $children = [];
                if (!empty($node['children']) && is_array($node['children'])) {
                    $children = array_values(array_map($mapNode, $node['children']));
                }

                // Determine a readable label (defensive): try several fields then fall back to slug
                $rawValue = '';
                foreach (['label', 'value', 'title', 'name'] as $k) {
                    if (!empty($node[$k]) && is_string($node[$k])) {
                        $rawValue = trim($node[$k]);
                        break;
                    }
                }

                // treat the legacy placeholder '(ohne Titel)' as empty
                if ('(ohne Titel)' === $rawValue) {
                    $rawValue = '';
                }

                if ('' === $rawValue && !empty($node['slug'])) {
                    // humanize slug: replace dashes with spaces and ucfirst
                    $rawValue = ucwords(str_replace('-', ' ', (string) $node['slug']));
                }

                if ('' === $rawValue) {
                    $rawValue = 'unnamed';
                }

                $link = $node['href'] ?? ($node['link'] ?? '#');
                if ('' === $link) {
                    $link = '#';
                }

                // Ensure templates receive a usable slug. NavService intentionally
                // exposes the FileMaker alias in 'slug' when present. However many
                // FM records lack an alias; to avoid the header rendering empty
                // links we synthesize a fallback slug from the human label here.
                // This keeps NavService authoritative for alias values while
                // allowing the UI to remain functional when aliases are missing.
                $slug = $node['slug'] ?? null;
                if (empty($slug) && '' !== $rawValue) {
                    // simple slugify: transliterate then replace non-alnum with '-'
                    $s = $rawValue;
                    if (function_exists('transliterator_transliterate')) {
                        try {
                            $tmp = @transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
                            if (false !== $tmp && null !== $tmp) {
                                $s = $tmp;
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    } else {
                        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                        if (false !== $tmp && null !== $tmp) {
                            $s = $tmp;
                        }
                    }
                    $s = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $s);
                    $s = trim($s, '-');
                    $slug = strtolower($s ?: 'n-a');
                }

                return [
                    'value' => $rawValue,
                    'slug' => $slug,
                    'link' => $link,
                    'count' => $node['count'] ?? (is_array($children) ? count($children) : 0),
                    'children' => $children,
                ];
            };

            $groupedTree = array_values(array_map($mapNode, $menu));

            // Heuristik: wenn das Ergebnis nur wenige Knoten enthält or viele unnamed labels,
            // verwenden wir den DevNavController-Fallback, der eine robustere Analyse baut.
            $total = count($groupedTree);
            $unnamed = 0;
            foreach ($groupedTree as $n) {
                $v = trim((string) ($n['value'] ?? ''));
                if ('' === $v || 'unnamed' === strtolower($v)) {
                    ++$unnamed;
                }
            }

            if (0 === $total || ($total <= 3 && $unnamed === $total) || ($total > 0 && ($unnamed / max(1, $total)) > 0.6)) {
                try {
                    $this->logger?->info('NavAnalysisExtension: falling back to DevNavController analyzeNavigation due to sparse/unnamed menu');
                    $analysis = $this->devNavController->analyzeNavigation($this->fm, $this->layouts, $this->logger);
                    if (isset($analysis['groupedTree']) && is_array($analysis['groupedTree'])) {
                        return ['analysis' => $analysis];
                    }
                } catch (\Throwable $e) {
                    // ignore and continue with the NavService result
                    $this->logger?->warning('NavAnalysisExtension fallback failed: '.$e->getMessage());
                }
            }

            return ['analysis' => ['groupedTree' => $groupedTree]];
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('NavAnalysisExtension failed to build menu: '.$e->getMessage());
            }

            return ['analysis' => ['groupedTree' => []]];
        }
    }
}
