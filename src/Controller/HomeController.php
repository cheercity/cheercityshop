<?php

namespace App\Controller;

use App\Service\BannerService;
use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use App\Service\NavService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    #[Route('/index', name: 'index')]
    public function index(Request $request, BannerService $bannerService, FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger, NavService $navService, DevNavController $devNavController): Response
    {
        // fetch banners for 'Main' position (debug: fresh data)
        $banners = $bannerService->getBanners('Main', 0);
        // fetch highlight bottom banners (one per position)
        $hb1 = $bannerService->getBanners('Highlight Bottom 1', 0);
        $hb2 = $bannerService->getBanners('Highlight Bottom 2', 0);
        $hb3 = $bannerService->getBanners('Highlight Bottom 3', 0);

        $highlightBanners = [
            'Highlight Bottom 1' => $hb1[0] ?? null,
            'Highlight Bottom 2' => $hb2[0] ?? null,
            'Highlight Bottom 3' => $hb3[0] ?? null,
        ];

        // Fetch a small set of products from FileMaker (Artikel Layout aus Registry)
        // Filter: Artikel_CMD_Row = 1 (only items flagged for CMD row). Limit to 8.
        $artikelLayout = $layouts->get('artikel', 'sym_Artikel');
        $products = [];

        // We'll try per-CMD finds for 1..6 so we collect articles for each CMD row
        $rows = [];
        $dbg = [];

        // For each CMD row, try multiple variants (string, int, exact) and collect results into productsByCmd
        $productsByCmd = [];
        for ($cmd = 1; $cmd <= 6; ++$cmd) {
            $tryQueries = [
                ['value' => (string) $cmd, 'note' => 'string "' . $cmd . '"'],
                ['value' => (int) $cmd, 'note' => 'integer ' . $cmd],
                ['value' => '==' . $cmd, 'note' => 'exact ==' . $cmd],
            ];

            $foundRows = [];
            foreach ($tryQueries as $q) {
                try {
                    $res = $fm->find($artikelLayout, ['Artikel_CMD_Row' => $q['value']], ['limit' => 8, 'offset' => 1]);
                    $dbg[] = ['cmd' => $cmd, 'attempt' => $q['note'], 'result' => isset($res['response']) ? (isset($res['response']['data']) ? count($res['response']['data']) : $res['response']) : null];
                    $attemptRows = $res['response']['data'] ?? [];
                    if (!empty($attemptRows)) {
                        $foundRows = $attemptRows;
                        break;
                    }
                } catch (\Throwable $e) {
                    $dbg[] = ['cmd' => $cmd, 'attempt' => $q['note'], 'exception' => (string) $e];
                }
            }

            // If we found rows for this CMD, append them to a temporary container (we'll normalize later)
            if (!empty($foundRows)) {
                $rows = array_merge($rows, $foundRows);
                $productsByCmd[$cmd] = [];
                foreach ($foundRows as $r) {
                    $f = $r['fieldData'] ?? [];
                    // Normalize fields and produce a safe slug for product URLs
                    $title = trim((string) ($f['Artikel_Bezeichnung_DE'] ?? $f['Artikel_Bezeichung_DE'] ?? $f['Bezeichnung_DE'] ?? $f['Bezeichnung'] ?? ''));
                    $rawAlias = trim((string) ($f['Alias'] ?? $f['alias'] ?? $f['direct_link_SEO'] ?? '')) ?: null;
                    $alias = null;
                    if ($rawAlias) {
                        // If the alias looks like a URL, extract the path and take the basename
                        if (false !== strpos($rawAlias, '://')) {
                            $path = parse_url($rawAlias, PHP_URL_PATH) ?: $rawAlias;
                            $candidate = trim((string) basename($path), '/');
                        } else {
                            $candidate = trim($rawAlias, '/');
                        }
                        // Replace any characters that are not allowed in slugs with hyphens
                        $candidate = preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $candidate);
                        $candidate = trim($candidate, '-');
                        if ('' !== $candidate) {
                            $alias = $candidate;
                        }
                    }

                    $item = [
                        'title' => $title,
                        'alias' => $alias,
                        'price' => isset($f['Artikel_Preis_VK_Brutto_Online']) ? $f['Artikel_Preis_VK_Brutto_Online'] : ($f['Artikel_Preis_VK_Brutto'] ?? ($f['Preis_VK_Brutto'] ?? null)),
                        'image' => $f['direct_link_image'] ?? $f['direct_link_SEO'] ?? $f['image'] ?? null,
                    ];
                    $productsByCmd[$cmd][] = $item;
                }
            }
        }

        // Persist debug info to legacy debug log for inspection
        try {
            $legacyPath = __DIR__ . '/../../var/log/legacy-debug-fm.log';
            @file_put_contents($legacyPath, date('c') . ' HomeController FM debug: ' . json_encode($dbg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore
        }

        // Optional: scan many records and report any with Artikel_CMD_Row == 1
        if ($request->query->get('debug_check_cmd')) {
            $matches = [];
            try {
                // fetch a large chunk (be careful in production)
                $big = $fm->list($artikelLayout, 2000, 1);
                $all = $big['response']['data'] ?? [];
                foreach ($all as $rec) {
                    $f = $rec['fieldData'] ?? [];
                    $val = isset($f['Artikel_CMD_Row']) ? $f['Artikel_CMD_Row'] : (isset($f['Artikel_CMD_ROW']) ? $f['Artikel_CMD_ROW'] : null);
                    if ('1' === (string) $val || 1 === $val) {
                        $matches[] = ['recID' => $f['recID'] ?? ($rec['recordId'] ?? null), 'title' => ($f['Artikel_Bezeichnung_DE'] ?? $f['Artikel_Bezeichung_DE'] ?? $f['Bezeichnung_DE'] ?? '')];
                    }
                }
            } catch (\Throwable $e) {
                // log the exception
                @file_put_contents(__DIR__ . '/../../var/log/legacy-debug-fm.log', date('c') . ' HomeController debug_check_cmd exception: ' . (string) $e . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
            @file_put_contents(__DIR__ . '/../../var/log/legacy-debug-fm.log', date('c') . ' HomeController debug_check_cmd matches: ' . json_encode($matches, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
            // also expose a quick dump in the response
            dump(['debug_check_cmd_matches' => $matches]);
        }

        // productsByCmd is already populated by per-CMD finds above

        // For backward compatibility keep products = CMD row 1 list (used by template)
        $products = $productsByCmd[1] ?? [];

        // Load intro highlights (Global_ID = 1) and pick the topHeadline based on first non-empty group
        $topHeadline = 'GET YOUR FASHION STYLE';
        $introData = [];
        try {
            $introLayout = $layouts->get('intro_highlights', 'sym_Intro_Highlights');
            $introRes = $fm->find($introLayout, ['Global_ID' => '1'], ['limit' => 1, 'offset' => 1]);
            $introData = $introRes['response']['data'][0]['fieldData'] ?? [];
            // Check groups 1..6 in order and use sale_descr_N if present
            for ($i = 1; $i <= 6; ++$i) {
                if (!empty($productsByCmd[$i])) {
                    $fieldName = 'sale_descr_' . $i;
                    $val = trim((string) ($introData[$fieldName] ?? ''));
                    if ('' !== $val) {
                        $topHeadline = $val;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // keep default topHeadline on error
        }

        // Optional debug output: use ?debug_output=log|console|dump
        // - log: writes to your Monolog logs (var/log/*.log)
        // - console: writes to PHP error log (visible in the PHP built-in server terminal)
        // - dump: uses the VarDumper dump() (visible in the web debug toolbar / profiler in dev)
        $debugOutput = $request->query->get('debug_output');
        if ($debugOutput) {
            if ('log' === $debugOutput) {
                // Prefer the dedicated FileMaker logger channel so messages go to var/log/filemaker.log
                if ($this->container->has('monolog.logger.filemaker')) {
                    $fmLogger = $this->container->get('monolog.logger.filemaker');
                    $fmLogger->info('HomeController products', ['products' => $products]);
                }
                // Always also write to the injected logger (environment log: dev.log or prod.log)
                $logger->info('HomeController products (env log)', ['products' => $products]);
            } elseif ('console' === $debugOutput) {
                // error_log will typically appear on the server console (php -S) or in PHP-FPM logs
                error_log('HomeController products: ' . json_encode($products, JSON_UNESCAPED_UNICODE));
            } elseif ('dump' === $debugOutput) {
                // dump() is helpful in dev and will appear in the web debug toolbar / profiler
                dump($products);
                // Also persist the dump to the FileMaker channel (or legacy file) so it's visible in logs
                if ($this->container->has('monolog.logger.filemaker')) {
                    $fmLogger = $this->container->get('monolog.logger.filemaker');
                    $fmLogger->info('HomeController products (dump)', ['products' => $products]);
                }
                // Fallback: append to a legacy debug file for convenience
                try {
                    $legacyPath = __DIR__ . '/../../var/log/legacy-debug-fm.log';
                    @file_put_contents($legacyPath, date('c') . ' HomeController products (dump): ' . json_encode($products, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
                } catch (\Throwable $e) {
                    // ignore write failures
                }
            }
        }

        // Attempt to fetch the navigation via NavService (cached) and map to the same analysis shape.
        try {
            $menu = $navService->getMenu();

            $mapNode = function (array $node) use (&$mapNode): array {
                $children = [];
                if (!empty($node['children']) && is_array($node['children'])) {
                    $children = array_values(array_map($mapNode, $node['children']));
                }

                $rawValue = '';
                foreach (['label', 'value', 'title', 'name'] as $k) {
                    if (!empty($node[$k]) && is_string($node[$k])) {
                        $rawValue = trim($node[$k]);
                        break;
                    }
                }
                if ('(ohne Titel)' === $rawValue) {
                    $rawValue = '';
                }
                if ('' === $rawValue && !empty($node['slug'])) {
                    $rawValue = ucwords(str_replace('-', ' ', (string) $node['slug']));
                }
                if ('' === $rawValue) {
                    $rawValue = 'unnamed';
                }

                $link = $node['href'] ?? ($node['link'] ?? '#');
                if ('' === $link) {
                    $link = '#';
                }

                return [
                    'value' => $rawValue,
                    'slug' => $node['slug'] ?? null,
                    'link' => $link,
                    'count' => $node['count'] ?? (is_array($children) ? count($children) : 0),
                    'children' => $children,
                ];
            };

            $groupedTree = array_values(array_map($mapNode, $menu));

            $total = count($groupedTree);
            $unnamed = 0;
            foreach ($groupedTree as $n) {
                $v = trim((string) ($n['value'] ?? ''));
                if ('' === $v || 'unnamed' === strtolower($v)) {
                    ++$unnamed;
                }
            }

            if (0 === $total || ($total <= 3 && $unnamed === $total) || ($total > 0 && ($unnamed / max(1, $total)) > 0.6)) {
                // Fallback to the controller analysis when NavService result looks poor
                try {
                    $logger->info('HomeController: falling back to DevNavController analyzeNavigation due to sparse/unnamed menu');
                    $analysis = $devNavController->analyzeNavigation($fm, $layouts, $logger);
                } catch (\Throwable $e) {
                    $analysis = [];
                    $logger->error('HomeController: failed to fetch dev nav analysis fallback: ' . $e->getMessage());
                }
            } else {
                $analysis = ['groupedTree' => $groupedTree];
            }
        } catch (\Throwable $e) {
            $analysis = [];
            $logger->error('HomeController: failed to fetch nav menu: ' . $e->getMessage());
        }

        $av = $this->getAssetVersion();

        return $this->render('home/index.html.twig', [
            'header' => 'flase',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable1.css'],
            'script' => ['assets/js/vendors/zoom.js'],
            'banners' => $banners,
            'highlightBanners' => $highlightBanners,
            'products' => $products,
            'productsByCmd' => $productsByCmd,
            'introData' => $introData,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/servicer/{slug}', name: 'servicer')]
    #[Route('/service/{slug}', name: 'service')]
    public function servicer(string $slug, FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): Response
    {
        // Fetch the module row from FileMaker where alias/title == $slug (uncached, single find)
        $contentHtml = null;
        $foundRow = null;
        $tried = [];
        try {
            $layout = $layouts->get('module', 'sym_Module');

            // Try a few likely field names for alias/title (exact matches)
            $candidateFields = ['alias', 'Alias', 'titel', 'Titel', 'title', 'Title'];
            foreach ($candidateFields as $field) {
                try {
                    $tried[] = $field;
                    $res = $fm->find($layout, [$field => $slug], ['limit' => 1, 'offset' => 1]);
                    $row = $res['response']['data'][0]['fieldData'] ?? null;
                    if (is_array($row) && !empty($row)) {
                        $foundRow = $row;
                        break;
                    }
                } catch (\Throwable $e) {
                    // ignore this attempt, continue to next field
                }
            }

            // If not found by direct field queries, list a larger set and scan case-insensitively
            if (null === $foundRow) {
                try {
                    $listRes = $fm->list($layout, 1000, 1);
                    $rows = $listRes['response']['data'] ?? [];
                    foreach ($rows as $rec) {
                        $fd = $rec['fieldData'] ?? [];
                        // check alias or titel case-insensitive
                        if (!empty($fd['alias']) && strcasecmp((string) $fd['alias'], $slug) === 0) {
                            $foundRow = $fd;
                            break;
                        }
                        if (!empty($fd['Alias']) && strcasecmp((string) $fd['Alias'], $slug) === 0) {
                            $foundRow = $fd;
                            break;
                        }
                        if (!empty($fd['titel']) && strcasecmp((string) $fd['titel'], $slug) === 0) {
                            $foundRow = $fd;
                            break;
                        }
                        if (!empty($fd['titel']) && stripos((string) $fd['titel'], (string) $slug) !== false) {
                            $foundRow = $fd;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore list failures
                }
            }

            if (is_array($foundRow)) {
                $contentHtml = $foundRow['content_DE_HTML'] ?? $foundRow['Content_DE_HTML'] ?? $foundRow['content_de_html'] ?? $foundRow['Content_DE'] ?? null;
            }
        } catch (\Throwable $e) {
            // log and continue with fallback (template will show textarea)
            $logger->warning('servicer: exception while finding sym_Module', ['slug' => $slug, 'exception' => (string) $e, 'tried' => $tried]);
        }

        if (null === $contentHtml) {
            $logger->info('servicer: no content found for slug', ['slug' => $slug, 'tried' => $tried, 'foundRow' => (bool) $foundRow]);
        } else {
            $logger->info('servicer: content loaded for slug', ['slug' => $slug]);
        }

        return $this->render('shop/servicer.html.twig', [
            'title' => 'Servicer: ' . htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE),
            'slug' => $slug,
            'content_de_html' => $contentHtml,
            'footer' => 'true',
        ]);
    }

    #[Route('/all-category', name: 'allCategory')]
    public function allCategory(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/allCategory.html.twig', [
            'title' => 'All Catagory',
            'subTitle' => 'Home',
            'subTitle2' => 'All Catagory',
            'css' => ['assets/css/variables/variable6.css'],
        ]);
    }

    #[Route('/category', name: 'category')]
    public function category(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/category.html.twig', [
            'title' => 'Catagory',
            'subTitle' => 'Home',
            'subTitle2' => 'Catagory',
            'footer' => 'true',
            'css' => '<link rel="stylesheet" type="text/css" href="/assets/css/jquery.nstSlider.min.css?v=' . $av . '"/> <link rel="stylesheet" type="text/css" href="/assets/css/variables/variable6.css?v=' . $av . '"/>',
            'script' => ['assets/js/vendors/jquery.nstSlider.min.js', 'assets/js/vendors/zoom.js'],
        ]);
    }

    #[Route('/external-products', name: 'externalProducts')]
    public function externalProducts(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/externalProducts.html.twig', [
            'title' => 'External Product',
            'subTitle' => 'Home',
            'subTitle2' => 'External Product',
            'css' => '<link rel="stylesheet" type="text/css" href="/assets/css/jquery.nstSlider.min.css?v=' . $av . '"/> <link rel="stylesheet" type="text/css" href="/assets/css/variables/variable6.css?v=' . $av . '"/>',
        ]);
    }

    #[Route('/index-eight', name: 'indexEight')]
    public function indexEight(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexEight.html.twig', [
            'header' => 'flase',
            'css' => ['assets/css/variables/variable4.css'],
        ]);
    }

    #[Route('/index-five', name: 'indexFive')]
    public function indexFive(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexFive.html.twig', [
            'header' => 'true',
            'css' => ['assets/css/variables/variable4.css'],
        ]);
    }

    #[Route('/index-four', name: 'indexFour')]
    public function indexFour(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexFour.html.twig', [
            'header' => 'flase',
            'css' => ['assets/css/variables/variable3.css'],
        ]);
    }

    #[Route('/index-nine', name: 'indexNine')]
    public function indexNine(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexNine.html.twig', [
            'header' => 'flase',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable1.css'],
            'script' => '<script src="/assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/index-seven', name: 'indexSeven')]
    public function indexSeven(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexSeven.html.twig', [
            'header' => 'flase',
            'css' => ['assets/css/variables/variable7.css'],
        ]);
    }

    #[Route('/index-six', name: 'indexSix')]
    public function indexSix(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexSix.html.twig', [
            'header' => 'flase',
            'css' => ['assets/css/variables/variable5.css'],
        ]);
    }

    #[Route('/index-ten', name: 'indexTen')]
    public function indexTen(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexTen.html.twig', [
            'header' => 'flase',
            'css' => ['assets/css/variables/variable10.css'],
        ]);
    }

    #[Route('/index-three', name: 'indexThree')]
    public function indexThree(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexThree.html.twig', [
            'header' => 'flase',
            'css' => ['assets/css/variables/variable2.css'],
        ]);
    }

    #[Route('/index-two', name: 'indexTwo')]
    public function indexTwo(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/indexTwo.html.twig', [
            'header' => 'flase',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable1.css'],
        ]);
    }

    #[Route('/login', name: 'login')]
    public function login(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/login.html.twig', [
            'title' => 'Log In',
            'subTitle' => 'home',
            'subTitle2' => 'Log In',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable6.css'],
        ]);
    }

    #[Route('/out-of-stock-products', name: 'outOfStockProducts')]
    public function outOfStockProducts(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/outOfStockProducts.html.twig', [
            'title' => 'Out Of Stock',
            'subTitle' => 'home',
            'subTitle2' => 'Out Of Stock',
            'css' => ['assets/css/variables/variable6.css'],
        ]);
    }

    #[Route('/shop-five-column', name: 'shopFiveColumn')]
    public function shopFiveColumn(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/shopFiveColumn.html.twig', [
            'header' => 'true',
            'title' => 'Shop Five Column',
            'subTitle' => 'home',
            'subTitle2' => 'Shop Five Column',
            'css' => ['assets/css/variables/variable1.css', 'assets/css/jquery.nstSlider.min.css'],
        ]);
    }

    #[Route('/simple-products', name: 'simpleProducts')]
    public function simpleProducts(): Response
    {
        return $this->render('home/simpleProducts.html.twig', [
            'title' => 'Simple Products',
            'subTitle' => 'home',
            'subTitle2' => 'Simple Products',
        ]);
    }

    #[Route('/thank-you', name: 'thankYou')]
    public function thankYou(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/thankYou.html.twig', [
            'title' => 'Thank You',
            'subTitle' => 'home',
            'subTitle2' => 'Thank You',
            'css' => ['assets/css/variables/variable1.css'],
        ]);
    }

    #[Route('/wishlist', name: 'wishlist')]
    public function wishlist(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('home/wishlist.html.twig', [
            'title' => 'wishlist',
            'subTitle' => 'home',
            'subTitle2' => 'wishlist',
            'css' => ['assets/css/variables/variable1.css'],
        ]);
    }

    private function getAssetVersion(): string
    {
        try {
            return (string) $this->getParameter('asset_version');
        } catch (\Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException $e) {
            return (string) time();
        }
    }
}
