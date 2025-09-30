<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\NavService;
use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;

final class ShopController extends AbstractController
{
    #[Route('/account', name: 'account') ]
    public function account(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/account.html.twig', [
            'title' => 'Account',
            'subTitle' => 'Shop',
            'subTitle2' => 'Account',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable6.css'],
            'script' => ['assets/js/vendors/zoom.js'],
        ]);
    }

    #[Route('/cart', name: 'cart') ]
    public function cart(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/cart.html.twig', [
            'title' => 'Cart',
            'subTitle' => 'Home',
            'subTitle2' => 'Cart',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable6.css'],
            'script' => ['assets/js/vendors/zoom.js'],
        ]);
    }

    #[Route('/check-out', name: 'checkOut') ]
    public function checkOut(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/checkOut.html.twig', [
            'title' => 'Checkout',
            'subTitle' => 'Home',
            'subTitle2' => 'Checkout',
            'footer' => 'true',
            'css' => '<link rel="stylesheet" type="text/css" href="/assets/css/variables/variable6.css?v='.$av.'"/>',
            'script' => ['assets/js/vendors/zoom.js'],
        ]);
    }

    #[Route('/full-width-shop', name: 'fullWidthShop') ]
    public function fullWidthShop(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/fullWidthShop.html.twig', [
            'title' => 'Full Width Shop',
            'subTitle' => 'Home',
            'subTitle2' => 'Full Width Shop',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable6.css', 'assets/css/jquery.nstSlider.min.css'],
            'script' => ['assets/js/vendors/zoom.js', 'assets/js/vendors/jquery.nstSlider.min.js'],
        ]);
    }

    #[Route('/kategorie/{slug}', name: 'category_show')]
    public function categoryShow(string $slug, NavService $navService, FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): Response
    {
        // Render the full width shop template for a category slug.
        // Resolve the incoming alias/slug to the internal cat_sort value using NavService.
        $av = $this->getAssetVersion();

        // Ensure the NavService has built (or loaded) the alias->cat_sort map. Some requests
        // hit the category route before any template invoked NavService::getMenu(), so
        // lazily build it here if needed.
        $aliasMap = $navService->getAliasToCatSort();
        if (empty($aliasMap)) {
            // Bypass cache once to ensure fresh mapping during development; in prod this
            // will normally be satisfied by the cached value and this call is cheap.
            $navService->getMenu(null, ['Status' => '1'], 0);
            $aliasMap = $navService->getAliasToCatSort();
        }

        $catSort = $aliasMap[strtolower($slug)] ?? null;

        $products = [];

        if (null === $catSort) {
            // No mapping found: treat as 404 category not found.
            throw $this->createNotFoundException(sprintf('Kategorie "%s" nicht gefunden', $slug));
        }

        // Query FileMaker layout 'sym_Artikel' for products with Category_ID == cat_sort
        try {
            $layout = $layouts->get('artikel', 'sym_Artikel');

            // FileMakerClient::find takes a layout and a criteria array
            // Only show published articles (Published = 1) — numeric field
            $criteria = ['Category_ID' => $catSort, 'Published' => 1];
            $result = $fm->find($layout, $criteria, ['limit' => 200]);

            foreach ($result['response']['data'] ?? [] as $r) {
                $products[] = $r['fieldData'] ?? [];
            }
        } catch (\Throwable $e) {
            // Log FileMaker errors but continue rendering the page with an empty product list
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('Failed to load products for category '.$catSort.': '.$e->getMessage(), ['exception' => $e, 'category' => $catSort]);
        }

        return $this->render('shop/fullWidthShop.html.twig', [
            'title' => 'Kategorie: '.$slug,
            'subTitle' => 'Shop',
            'subTitle2' => 'Kategorie',
            'footer' => 'true',
            'category_slug' => $slug,
            // expose resolved cat_sort for dev/debug views
            'cat_sort' => $catSort,
            'products' => $products,
            'css' => '<link rel="stylesheet" type="text/css" href="/assets/css/variables/variable6.css?v='.$av.'"/> <link rel="stylesheet" type="text/css" href="/assets/css/jquery.nstSlider.min.css?v='.$av.'"/>',
            'script' => ['assets/js/vendors/zoom.js', 'assets/js/vendors/jquery.nstSlider.min.js'],
        ]);
    }

    #[Route('/kategorie', name: 'category_index')]
    public function categoryIndex(): Response
    {
        // Redirect requests to /kategorie (without slug) to the site home page
        return $this->redirectToRoute('home');
    }

    #[Route('/grouped-products', name: 'groupedProducts') ]
    public function groupedProducts(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/groupedProducts.html.twig', [
            'title' => 'Grouped Products',
            'subTitle' => 'Home',
            'subTitle2' => 'Grouped Products',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable6.css'],
        ]);
    }

    #[Route('/product-details', name: 'productDetails') ]
    public function productDetails(): Response
    {
        return $this->render('shop/productDetails.html.twig', [
            'title' => 'Product',
            'subTitle' => 'Home',
            'subTitle2' => 'Product',
            'footer' => 'true',
            'script' => ['assets/js/vendors/zoom.js'],
        ]);
    }

    #[Route('/produkt/{slug}', name: 'product_show')]
    public function productShow(string $slug): Response
    {
        // Minimal handler: render the product details page and pass the alias/slug.
        // In a real implementation this should lookup the product by alias and pass full product data.
        return $this->render('shop/productDetails.html.twig', [
            'title' => 'Product',
            'subTitle' => 'Shop',
            'subTitle2' => 'Product',
            'footer' => 'true',
            'script' => ['assets/js/vendors/zoom.js'],
            'product_alias' => $slug,
        ]);
    }

    #[Route('/kategorie/{category}/{slug}', name: 'category_product_show')]
    public function categoryProductShow(string $category, string $slug, NavService $navService, FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger, \App\Service\WertelistenService $wertelisten): Response
    {
        // Resolve category alias to internal Category_ID (cat_sort)
        $aliasMap = $navService->getAliasToCatSort();
        if (empty($aliasMap)) {
            $navService->getMenu(null, ['Status' => '1'], 0);
            $aliasMap = $navService->getAliasToCatSort();
        }

        $catSort = $aliasMap[strtolower($category)] ?? null;
        if (null === $catSort) {
            throw $this->createNotFoundException(sprintf('Kategorie "%s" nicht gefunden', $category));
        }

        // Query sym_Artikel for Artikel_Alias == $slug and Published = 1 (and Category_ID)
        $product = null;
        try {
            $layout = $layouts->get('artikel', 'sym_Artikel');
            $criteria = ['Artikel_Alias' => $slug, 'Published' => 1, 'Category_ID' => $catSort];
            $result = $fm->find($layout, $criteria, ['limit' => 1]);
            $product = $result['response']['data'][0]['fieldData'] ?? null;
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('Failed to load product '.$slug.' in category '.$catSort.': '.$e->getMessage(), ['exception' => $e, 'category' => $catSort, 'alias' => $slug]);
        }

        if (null === $product) {
            throw $this->createNotFoundException(sprintf('Produkt "%s" nicht gefunden in Kategorie "%s"', $slug, $category));
        }

        // Use PSR-6 cache (filesystem via cache.app in dev) to avoid repeated FM hits while
        // still allowing on-the-fly updates during development. TTL is short (600s).
        $items = [];
        $itemImages = [];
        $itemsMapping = [];
        $ttl = 600; // seconds
        try {
            $skuGroup = $product['SKU_ArtikelGruppe'] ?? $product['Artikel_SKU_Gruppe'] ?? null;
            if (!$skuGroup) {
                throw new \RuntimeException('SKU_ArtikelGruppe not present on product '.$slug);
            }

            // obtain PSR-6 pool (cache.app) from container
            $cachePool = null;
            if ($this->container->has('cache.app')) {
                $cachePool = $this->container->get('cache.app');
            }

            $cacheKey = 'artikel_items_'.preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$skuGroup);
            $cached = null;
            if ($cachePool instanceof \Psr\Cache\CacheItemPoolInterface) {
                $item = $cachePool->getItem($cacheKey);
                if ($item->isHit()) {
                    $cached = $item->get();
                    // lightweight instrumentation to help debugging cache contents
                    try {
                        $logger->info('Cache HIT for key '.$cacheKey, [
                            'is_array' => is_array($cached),
                            'cached_keys' => is_array($cached) ? array_keys($cached) : [],
                        ]);
                    } catch (\Throwable $e) {
                        // swallow logging errors
                    }
                }
            }

            if (is_array($cached)) {
                // support legacy caches that stored items only (backwards compatible)
                if (isset($cached['items'])) {
                    $items = $cached['items'];
                    $itemImages = $cached['item_images'] ?? [];
                    $itemsMapping = $cached['items_mapping'] ?? [];
                } else {
                    $items = $cached;
                }
            } else {
                // Query items by SKU_ArtikelGruppe (limit 250)
                $layoutItems = $layouts->get('artikel_items', 'sym_Artikel_items');
                $resItems = $fm->find($layoutItems, ['SKU_ArtikelGruppe' => $skuGroup], ['limit' => 250]);
                // Prepare colors base path (normalize public/ prefix and ensure web-root relative)
                $colorsPath = null;
                try {
                    $colorsPath = $this->getParameter('COLORS_IMAGES_PATH');
                } catch (\Throwable $eParam) {
                    $colorsPath = getenv('COLORS_IMAGES_PATH') ?: null;
                }
                $colorsPath = $colorsPath ?: '/assets/images/farben';
                if (str_starts_with($colorsPath, '/public/')) {
                    $colorsPath = substr($colorsPath, 8);
                } elseif (str_starts_with($colorsPath, 'public/')) {
                    $colorsPath = substr($colorsPath, 7);
                }
                if (strpos($colorsPath, 'http') !== 0 && strpos($colorsPath, '/') !== 0) {
                    $colorsPath = '/'.$colorsPath;
                }

                // Build items array (only include items with positive Online_Bestand as before)
                $allItemRows = $resItems['response']['data'] ?? [];
                foreach ($allItemRows as $r) {
                    $row = $r['fieldData'] ?? [];
                    $stock = isset($row['Online_Bestand']) ? (int)$row['Online_Bestand'] : 0;
                    // derive color_url from FarbCode_1 when present
                    $colorCode = $row['FarbCode_1'] ?? ($row['FarbCode_1_code'] ?? null);
                    if ($colorCode !== null && trim((string)$colorCode) !== '') {
                        $row['color_url'] = rtrim($colorsPath, '/') . '/Color_' . trim((string)$colorCode) . '.jpg';
                    } else {
                        $row['color_url'] = null;
                    }

                    if ($stock > 0) {
                        $items[] = $row;
                    }
                }

                // Single-query: fetch all sym_Artikel_items_images records for this SKU group
                $layoutItemImages = $layouts->get('artikel_item_image', 'sym_Artikel_items_images');
                $itemImages = [];
                try {
                    $resGroupImgs = $fm->find($layoutItemImages, ['SKU_ArtikelGruppe' => $skuGroup], ['limit' => 250]);
                    foreach ($resGroupImgs['response']['data'] ?? [] as $ri) {
                        $itemImages[] = $ri['fieldData'] ?? [];
                    }
                } catch (\Throwable $eImgGroup) {
                    $logger->warning('Item-images lookup failed for SKU '.$skuGroup.': '.$eImgGroup->getMessage());
                }

                // Create a mapping list from sym_Artikel_items: include both FarbCode_1 (code) and FarbCode_1_descr
                // so templates can resolve mappings using the code (preferred) and fall back to the description.
                // Mapping key remains FarbCode_1_descr|SizeCode_descr for backwards compatibility in views.
                $itemsMapping = [];
                foreach ($allItemRows as $r) {
                    $row = $r['fieldData'] ?? [];
                    $color = $row['FarbCode_1_descr'] ?? '';
                    $colorCode = $row['FarbCode_1'] ?? ($row['FarbCode_1_code'] ?? null);
                    // attach color_url to mapping entries as well
                    if ($colorCode !== null && trim((string)$colorCode) !== '') {
                        $rowColorUrl = rtrim($colorsPath, '/') . '/Color_' . trim((string)$colorCode) . '.jpg';
                    } else {
                        $rowColorUrl = null;
                    }
                    $size = $row['SizeCode_descr'] ?? '';
                    $key = trim($color) . '|' . trim($size);
                    // normalise the status fields
                    $mapping = [
                        'FarbCode_1_descr' => $color,
                        'FarbCode_1' => $colorCode,
                        'SizeCode_descr' => $size,
                        'Online_Bestand' => isset($row['Online_Bestand']) ? (int)$row['Online_Bestand'] : 0,
                        'Online_Lieferzeit' => $row['Online_Lieferzeit'] ?? null,
                        'color_url' => $rowColorUrl,
                    ];
                    // store/replace the mapping entry (if duplicate combos exist, last one wins)
                    $itemsMapping[$key] = $mapping;
                }
                // convert mapping to zero-based list for the template
                $itemsMapping = array_values($itemsMapping);

                // store a composite cache entry (items + item_images + items_mapping)
                if ($cachePool instanceof \Psr\Cache\CacheItemPoolInterface) {
                    try {
                        $cacheItem = $cachePool->getItem($cacheKey);
                        $cacheItem->set([
                            'items' => $items,
                            'item_images' => $itemImages,
                            'items_mapping' => $itemsMapping,
                        ]);
                        $cacheItem->expiresAfter($ttl);
                        $cachePool->save($cacheItem);
                        try {
                            $logger->info('Saved composite cache for key '.$cacheKey, [
                                'items_count' => is_array($items) ? count($items) : 0,
                                'item_images_count' => is_array($itemImages) ? count($itemImages) : 0,
                                'items_mapping_count' => is_array($itemsMapping) ? count($itemsMapping) : 0,
                            ]);
                        } catch (\Throwable $e) {
                            // swallow logging errors
                        }
                    } catch (\Throwable $eCache) {
                        $logger->warning('Failed to save items to cache for key '.$cacheKey.': '.$eCache->getMessage());
                    }
                }
            }

            // If we loaded a legacy cache (items only) ensure we still provide items_mapping
            // and a single item_images query for the template. This keeps the template debug
            // blocks populated even when only items were cached previously.
            if (!isset($itemImages) || !is_array($itemImages) || empty($itemImages)) {
                try {
                    // attempt to fetch item images for the SKU group (best-effort)
                    $layoutItemImages = $layouts->get('artikel_item_image', 'sym_Artikel_items_images');
                    $resGroupImgs = $fm->find($layoutItemImages, ['SKU_ArtikelGruppe' => $skuGroup], ['limit' => 250]);
                    $itemImages = [];
                    foreach ($resGroupImgs['response']['data'] ?? [] as $ri) {
                        $itemImages[] = $ri['fieldData'] ?? [];
                    }
                    try {
                        $logger->info('Post-cache item-images lookup', ['sku' => $skuGroup, 'found' => count($itemImages)]);
                    } catch (\Throwable $e) {
                        // noop
                    }
                } catch (\Throwable $eImgGroup) {
                    $logger->warning('Item-images lookup (post-cache) failed for SKU '.$skuGroup.': '.$eImgGroup->getMessage());
                    $itemImages = $itemImages ?? [];
                }
            }

            // Build itemsMapping from the (possibly cached) items list if not already present
            if (empty($itemsMapping) && !empty($items)) {
                $itemsMapping = [];
                foreach ($items as $row) {
                    $color = $row['FarbCode_1_descr'] ?? '';
                    $colorCode = $row['FarbCode_1'] ?? ($row['FarbCode_1_code'] ?? null);
                    $size = $row['SizeCode_descr'] ?? '';
                    $key = trim($color) . '|' . trim($size);
                    $mapping = [
                        'FarbCode_1_descr' => $color,
                        'FarbCode_1' => $colorCode,
                        'SizeCode_descr' => $size,
                        'Online_Bestand' => isset($row['Online_Bestand']) ? (int)$row['Online_Bestand'] : 0,
                        'Online_Lieferzeit' => $row['Online_Lieferzeit'] ?? null,
                    ];
                    $itemsMapping[$key] = $mapping;
                }
                $itemsMapping = array_values($itemsMapping);
            }

                // no automatic FarbCode resolution here — FarbCode_1 must be present
                // in the sym_Artikel_items data to be used as mapping key.
            
        } catch (\Throwable $e) {
            // In development mode surface errors so they are visible immediately
            $logger->error('Failed to load items/images for product '.$slug.': '.$e->getMessage(), ['exception' => $e, 'alias' => $slug]);
            // Return a 500 response in dev to make the problem visible (change in prod)
            throw $e;
        }

        // Group items by color for the template (avoid using Twig extensions that may be missing)
        $itemsByColor = [];
        foreach ($items as $it) {
            // Prefer the raw FarbCode_1 (code) when grouping by color; fall back to the human readable descriptor
            $color = $it['FarbCode_1'] ?? $it['FarbCode_1_descr'] ?? 'default';
            if (!isset($itemsByColor[$color])) {
                $itemsByColor[$color] = [];
            }

            // normalize images: if images are arrays of fieldData, try to extract a URL/string for each
            $normalizedImages = [];
            foreach (($it['images'] ?? []) as $imgRecord) {
                if (is_array($imgRecord)) {
                    // pick first non-empty scalar value from the image record
                    foreach ($imgRecord as $val) {
                        if (is_string($val) && trim($val) !== '') {
                            $normalizedImages[] = $val;
                            break;
                        }
                    }
                } elseif (is_string($imgRecord) && trim($imgRecord) !== '') {
                    $normalizedImages[] = $imgRecord;
                }
            }

            // decide primary_image for this variant: prefer normalized images from the
            // sym_Artikel_items_images query. Do NOT use the legacy field `FarbCode_1_file`.
            // Color images should be resolved via the Wertelisten mapping in templates
            // (wl_color_image) using the FarbCode value.
            $primaryImage = null;
            if (!empty($normalizedImages)) {
                $primaryImage = $normalizedImages[0];
            }
            // normalize path: if not absolute URL or starting with '/', prefix with assets path
            if (!empty($primaryImage) && !preg_match('#^https?://#i', $primaryImage) && strpos($primaryImage, '/') !== 0) {
                // use public asset folder path (without 'public/' prefix)
                $primaryImage = '/assets/images/produkte/' . ltrim($primaryImage, '/');
            }

            $it['images'] = $normalizedImages;
            $it['primary_image'] = $primaryImage;

            $itemsByColor[$color][] = $it;
        }

        // obtain color map from WertelistenService (Farbcode -> label/image)
        $colorMap = [];
        try {
            $colorMap = $wertelisten->getMap('Farbcode');
        } catch (\Throwable $e) {
            $logger->debug('Failed to load Farbcode wertelisten: '.$e->getMessage());
            $colorMap = [];
        }

        // Render using the variableProducts template as requested
        return $this->render('shop/variableProducts.html.twig', [
            'title' => $product['Artikel_Bezeichnung'] ?? ($product['Title'] ?? 'Product'),
            'subTitle' => 'Shop',
            'subTitle2' => 'Product',
            'footer' => 'true',
            'product' => $product,
            'product_alias' => $slug,
            'category_slug' => $category,
            'items' => $items,
            'items_by_color' => $itemsByColor,
            // expose the item images (single query result) and the simple items mapping for the template
            'item_images' => $itemImages ?? [],
            'items_mapping' => $itemsMapping ?? [],
            // pass the Wertelisten color map so templates can reliably resolve labels and image URLs
            'color_map' => $colorMap,
        ]);
    }

    #[Route('/product-details-2', name: 'productDetails2') ]
    public function productDetails2(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/productDetails2.html.twig', [
            'title' => 'Product',
            'subTitle' => 'Shop',
            'subTitle2' => 'Product',
            'footer' => 'true',
            'css' => '<link rel="stylesheet" type="text/css" href="/assets/css/variables/variable6.css?v='.$av.'"/>',
            'script' => '<script src="/assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/shop', name: 'shop') ]
    public function shop(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/shop.html.twig', [
            'title' => 'Shop',
            'subTitle' => 'Home',
            'subTitle2' => 'Shop',
            'footer' => 'true',
            'css' => ['assets/css/variables/variable6.css'],
            'css2' => ['assets/css/jquery.nstSlider.min.css'],
            'script' => ['assets/js/vendors/zoom.js', 'assets/js/vendors/jquery.nstSlider.min.js'],
        ]);
    }

    #[Route('/sidebar-left', name: 'sidebarLeft') ]
    public function sidebarLeft(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/sidebarLeft.html.twig', [
            'title' => 'Sidebar Left',
            'subTitle' => 'Shop',
            'subTitle2' => 'Sidebar Left',
            'footer' => 'flase',
            'css' => ['assets/css/variables/variable6.css', 'assets/css/jquery.nstSlider.min.css'],
            'script' => ['assets/js/vendors/zoom.js', 'assets/js/vendors/jquery.nstSlider.min.js'],
        ]);
    }

    #[Route('/sidebar-right', name: 'sidebarRight') ]
    public function sidebarRight(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/sidebarRight.html.twig', [
            'title' => 'Sidebar Right',
            'subTitle' => 'Shop',
            'subTitle2' => 'Sidebar Right',
            'css' => '<link rel="stylesheet" type="text/css" href="/assets/css/variables/variable6.css?v='.$av.'"/> <link rel="stylesheet" type="text/css" href="/assets/css/jquery.nstSlider.min.css?v='.$av.'"/>',
            'script' => ['assets/js/vendors/zoom.js', 'assets/js/vendors/jquery.nstSlider.min.js'],
        ]);
    }

    #[Route('/variable-products', name: 'variableProducts') ]
    public function variableProducts(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('shop/variableProducts.html.twig', [
            'title' => 'Variable Products',
            'subTitle' => 'Shop',
            'subTitle2' => 'Variable Products',
            'footer' => 'true',
            'script' => ['assets/js/vendors/zoom.js', 'assets/js/vendors/jquery.nstSlider.min.js'],
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
