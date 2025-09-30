<?php

namespace App\Controller;

use App\Service\FileMakerClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class DebugMenuController extends AbstractController
{
    #[Route('/_debug/menu-fields/{slug}', name: 'debug_menu_fields')]
    public function fields(string $slug = '', FileMakerClient $fm): JsonResponse
    {
        $out = ['slug' => $slug, 'found' => false, 'rows' => [], 'normalized' => []];
        try {
            if ('' === $slug) {
                return new JsonResponse(['error' => 'provide slug in path, e.g. /_debug/menu-fields/women'], 400);
            }

            // exact match for slug using alias field
            $res = $fm->find('sym_Navigation', [['alias' => '=='.$slug]], ['limit' => 5]);
            $rows = $res['response']['data'] ?? [];
            foreach ($rows as $r) {
                $out['rows'][] = $r['fieldData'] ?? [];
            }

            if (!empty($out['rows'])) {
                $out['found'] = true;
                // normalize the first row into convenient keys
                $f = $out['rows'][0];
                $norm = [];
                $norm['parent_id'] = $f['parent_ID'] ?? $f['parentId'] ?? $f['parentid'] ?? $f['parent'] ?? null;
                $norm['category_id'] = $f['categroy_ID'] ?? $f['category_ID'] ?? $f['categoryId'] ?? null;
                $norm['cat_banner'] = $f['cat_banner'] ?? $f['catBanner'] ?? null;
                $norm['cat_descr_h1'] = $f['cat_descr_h1'] ?? $f['catDescrH1'] ?? null;
                $norm['raw_keys'] = array_values(array_keys($f));
                $out['normalized'] = $norm;
            }
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        return new JsonResponse($out);
    }
}
