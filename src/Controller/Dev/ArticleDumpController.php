<?php

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;

final class ArticleDumpController extends AbstractController
{
    #[Route('/debug/dump-articles', name: 'debug_dump_articles')]
    public function dump(FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): Response
    {
        $layout = $layouts->get('artikel', 'sym_Artikel');
        $all = [];
        try {
            // Read all published articles (limit high)
            $result = $fm->find($layout, ['Published' => 1], ['limit' => 1000]);
            foreach ($result['response']['data'] ?? [] as $r) {
                $all[] = $r['fieldData'] ?? [];
            }
        } catch (\Throwable $e) {
            $logger->error('Dump articles error: '.$e->getMessage(), ['exception' => $e]);
            return new Response('Error dumping articles: '.$e->getMessage(), 500);
        }

        $dest = $this->getParameter('kernel.project_dir').'/data/sym_Artikel_dump.json';
        file_put_contents($dest, json_encode($all, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        return $this->render('dev/dump_articles.html.twig', [
            'count' => count($all),
            'path' => '/data/sym_Artikel_dump.json',
            'articles' => $all,
        ]);
    }

    #[Route('/produkt-json', name: 'produkt_json')]
    public function showJson(): Response
    {
        $file = $this->getParameter('kernel.project_dir').'/data/sym_Artikel_dump.json';
        if (!file_exists($file)) {
            return new Response('Dump not found. Run /debug/dump-articles first.', 404);
        }
        $content = file_get_contents($file);
        return new Response('<pre>'.htmlspecialchars($content).'</pre>');
    }

    #[Route('/debug/dump-items/{alias}', name: 'debug_dump_items')]
    public function dumpItems(string $alias, \Symfony\Component\HttpFoundation\Request $request, FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): Response
    {
        // allow longer execution for this development dump which may query many records
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        ini_set('max_execution_time', '120');

        // 1) Resolve SKU_ArtikelGruppe using the products JSON dump if available.
        $article = null;
        $skuGroup = null;
        $dumpFile = $this->getParameter('kernel.project_dir').'/data/sym_Artikel_dump.json';
        if (file_exists($dumpFile)) {
            try {
                $content = json_decode(file_get_contents($dumpFile), true);
                if (is_array($content)) {
                    foreach ($content as $entry) {
                        if (isset($entry['Artikel_Alias']) && $entry['Artikel_Alias'] === $alias) {
                            $article = $entry;
                            // sym_Artikel provides SKU_ArtikelGruppe; prefer that (legacy Artikel_SKU_Gruppe accepted)
                            $skuGroup = $entry['SKU_ArtikelGruppe'] ?? $entry['Artikel_SKU_Gruppe'] ?? null;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $logger->warning('Failed to read products JSON for alias '.$alias.': '.$e->getMessage());
            }
        }

        // If we couldn't find the article in the JSON dump, fall back to FileMaker lookup by alias.
        if (null === $article) {
            try {
                $layoutArticle = $layouts->get('artikel', 'sym_Artikel');
                $res = $fm->find($layoutArticle, ['Artikel_Alias' => $alias, 'Published' => 1], ['limit' => 1]);
                $article = $res['response']['data'][0]['fieldData'] ?? null;
            } catch (\Throwable $e) {
                $logger->error('Error loading article '.$alias.': '.$e->getMessage(), ['exception' => $e]);
                return new Response('Error loading article: '.$e->getMessage(), 500);
            }

            if (null === $article) {
                return new Response('Article not found for alias: '.$alias, 404);
            }
            // prefer SKU_ArtikelGruppe (from sym_Artikel), fall back to Artikel_SKU_Gruppe if present
            $skuGroup = $article['SKU_ArtikelGruppe'] ?? $article['Artikel_SKU_Gruppe'] ?? null;
        }
        if (!$skuGroup) {
            return new Response('Artikel_SKU_Gruppe (or SKU_ArtikelGruppe) not set on article '.$alias, 400);
        }

        $items = [];
        try {
            // Use configured layout keys (configured in services.yaml)
            $layoutItems = $layouts->get('artikel_items', 'sym_Artikel_items');

            // Query items for the SKU group using field SKU_ArtikelGruppe as requested. Limit to 250.
            $criteria = ['SKU_ArtikelGruppe' => $skuGroup];
            $resItems = $fm->find($layoutItems, $criteria, ['limit' => 250]);

            foreach ($resItems['response']['data'] ?? [] as $r) {
                $row = $r['fieldData'] ?? [];
                // ensure numeric inventory and only include items with Online_Bestand > 0
                $stock = isset($row['Online_Bestand']) ? (int)$row['Online_Bestand'] : 0;
                // dev helper: allow including zero-stock items via query param
                $includeZero = (bool) $request->query->get('include_zero_stock', false);
                if ($includeZero || $stock > 0) {
                    $items[] = $row;
                }
            }
        } catch (\Throwable $e) {
            $logger->error('Error loading items for SKU group '.$skuGroup.': '.$e->getMessage(), ['exception' => $e]);
            return new Response('Error loading items: '.$e->getMessage(), 500);
        }

        // For each item, load images from sym_Artikel_item_image where Artikel_Item_ID or Artikel_ID matches
        $mapped = [];
        try {
            // correct layout for item images
            $layoutItemImages = $layouts->get('artikel_item_image', 'sym_Artikel_items_images');

            // Attempt to fetch images stored at SKU group level once (many projects store images at group level)
            $imagesBySku = [];
            try {
                $resSkuImgs = $fm->find($layoutItemImages, ['SKU_ArtikelGruppe' => $skuGroup], ['limit' => 250]);
                foreach ($resSkuImgs['response']['data'] ?? [] as $ri) {
                    $imagesBySku[] = $ri['fieldData'] ?? [];
                }
            } catch (\Throwable $eSkuImg) {
                $logger->warning('Group-level item-images lookup failed for SKU '.$skuGroup.': '.$eSkuImg->getMessage());
            }

            // Track unsupported per-layout criteria to avoid repeated FM 102 errors
            $unsupportedCriteria = [];

            foreach ($items as $it) {
                $recID = $it['recID'] ?? ($it['Artikel_Item_ID'] ?? null);
                $farbe = $it['FarbCode_1_descr'] ?? ($it['Farbe_descr'] ?? null);
                $size = $it['SizeCode_descr'] ?? ($it['Groesse_descr'] ?? null);
                $bestand = isset($it['Online_Bestand']) ? (int)$it['Online_Bestand'] : 0;
                $lieferzeit = $it['Online_Lieferzeit'] ?? null;

                // Start with images discovered at SKU level
                $images = $imagesBySku;

                // If none found at group level, attempt to find images linked to the specific item recID
                if (empty($images) && $recID) {
                    // Try a small set of candidate linking fields but avoid repeating candidates that
                    // are known unsupported for this layout in this request.
                    $candidates = ['Artikel_Item_ID', 'Artikel_Item_recID', 'Artikel_ID'];
                    foreach ($candidates as $field) {
                        if (!empty($images)) break;
                        if (!empty($unsupportedCriteria[$field])) {
                            // skip attempts for this candidate field if previously seen as unsupported
                            continue;
                        }

                        try {
                            $resImgs = $fm->find($layoutItemImages, [$field => $recID], ['limit' => 250]);
                            foreach ($resImgs['response']['data'] ?? [] as $ri) {
                                $images[] = $ri['fieldData'] ?? [];
                            }
                        } catch (\Throwable $e) {
                            $msg = $e->getMessage();
                            // If FileMaker says the field is missing (code 102), avoid retrying this
                            // candidate for the remainder of this request to reduce spam and timeouts.
                            if (false !== stripos($msg, 'Field is missing') || false !== stripos($msg, '"code":"102"') || false !== stripos($msg, '"code":102')) {
                                $unsupportedCriteria[$field] = true;
                                $logger->notice('Skipping unsupported image-link field '.$field.' for layout sym_Artikel_items_images: '.$msg);
                            } else {
                                $logger->warning('Item images load failed for recID '.$recID.' / SKU '.$skuGroup.' (field '.$field.'): '.$msg);
                            }
                        }
                    }
                }

                // Map fields
                $mapped[] = [
                    'recID' => $recID,
                    'FarbCode_1_descr' => $farbe,
                    'SizeCode_descr' => $size,
                    'Online_Bestand' => $bestand,
                    'Online_Lieferzeit' => $lieferzeit,
                    'images' => $images,
                ];
            }
        } catch (\Throwable $e) {
            $logger->error('Error mapping items: '.$e->getMessage(), ['exception' => $e]);
            return new Response('Error mapping items: '.$e->getMessage(), 500);
        }

        $dest = $this->getParameter('kernel.project_dir').'/data/sym_Artikel_items_dump_'.$alias.'.json';
        file_put_contents($dest, json_encode($mapped, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        return new Response('<pre>'.htmlspecialchars(json_encode($mapped, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>');
    }
}
