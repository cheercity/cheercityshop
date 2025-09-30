<?php

namespace App\Controller;

use App\Service\FileMakerClient;
use App\Service\NavService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class DevNavTreeController extends AbstractController
{
    #[Route('/_dev/nav-tree', name: 'dev_nav_tree')]
    public function tree(Request $request, FileMakerClient $fm, NavService $navService): Response
    {
        try {
            // Get the computed navigation from NavService (bypass cache for fresh results)
            $computed = $navService->getMenu(null, ['Status' => '1'], 0);

            // Also build a raw grouped tree directly from FileMaker for comparison
            $data = $fm->list('sym_Navigation', 2000, 1);
            $rows = $data['response']['data'] ?? [];

            $grouped = [];
            $maxRecords = 300;
            $count = 0;
            foreach ($rows as $r) {
                if ($count >= $maxRecords) break;
                $fd = $r['fieldData'] ?? [];
                // only include active items (Status == 1)
                $status = $fd['Status'] ?? $fd['status'] ?? null;
                if (((string) $status !== '1') && ((int) $status !== 1)) {
                    continue;
                }

                // Respect authoritative layout naming: prefer 'categroy_ID' when present
                $id = (string) ($fd['category_ID'] ?? $fd['categroy_ID'] ?? '');
                $parent = (string) ($fd['parent_ID'] ?? $fd['parentId'] ?? '');
                $alias = isset($fd['alias']) ? (string) $fd['alias'] : (isset($fd['Alias']) ? (string) $fd['Alias'] : '');
                $cat_title = isset($fd['cat_title']) ? (string) $fd['cat_title'] : (isset($fd['title']) ? (string) $fd['title'] : '');
                $cat_banner = isset($fd['cat_banner']) ? (string) $fd['cat_banner'] : '';
                $cat02 = (string) ($fd['cat02'] ?? '');

                $label = $cat_title;
                $href = ($cat02 !== '' ? '/kategorie/' : '/menu/') . $alias;

                $entry = [
                    'id' => $id,
                    'parent_id' => $parent,
                    'alias' => $alias,
                    'label' => $label,
                    'href' => $href,
                    'cat_banner' => $cat_banner,
                    'raw' => $fd,
                ];

                $c00 = (string) ($fd['cat00'] ?? '0');
                $c01 = (string) ($fd['cat01'] ?? '0');

                if (!isset($grouped[$c00])) {
                    $grouped[$c00] = [];
                }
                if (!isset($grouped[$c00][$c01])) {
                    $grouped[$c00][$c01] = [];
                }
                if (!isset($grouped[$c00][$c01][$cat02])) {
                    $grouped[$c00][$c01][$cat02] = [];
                }
                $grouped[$c00][$c01][$cat02][] = $entry;
                $count++;
            }

            // Convert grouped associative array to nested arrays for JSON output
            $tree = [];
            foreach ($grouped as $c00 => $by01) {
                $c00Node = ['cat00' => $c00, 'cat01' => []];
                foreach ($by01 as $c01 => $by02) {
                    $c01Node = ['cat01' => $c01, 'cat02' => []];
                    foreach ($by02 as $c02 => $items) {
                        $c02Node = ['cat02' => $c02, 'items' => $items];
                        $c01Node['cat02'][] = $c02Node;
                    }
                    $c00Node['cat01'][] = $c01Node;
                }
                $tree[] = $c00Node;
            }

            // If client requested JSON, return machine-readable computed+raw payload
            if ('json' === strtolower((string) $request->query->get('format'))) {
                return new JsonResponse(['computed' => $computed, 'raw' => $tree], 200, ['Content-Type' => 'application/json']);
            }

            $computedOut = json_encode($computed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $rawOut = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $html = '<!doctype html><html><head><meta charset="utf-8"><title>Dev Nav Tree</title></head><body>';
            $html .= '<h1>Dev: computed navigation (NavService)</h1>';
            $html .= '<p>Computed navigation from <code>NavService::getMenu()</code> (fresh, cache bypassed)</p>';
            $html .= '<pre style="white-space:pre-wrap;word-break:break-word">'.htmlspecialchars($computedOut).'</pre>';

            $html .= '<h1>Dev: raw FileMaker grouped tree</h1>';
            $html .= '<p>Fields shown: <strong>id (category_ID / categroy_ID)</strong>, <strong>parent_id</strong>, <strong>alias</strong>, <strong>cat_title</strong>, <strong>cat_banner</strong></p>';
            $html .= '<pre style="white-space:pre-wrap;word-break:break-word">'.htmlspecialchars($rawOut).'</pre>';
            $html .= '</body></html>';

            return new Response($html);
        } catch (\Throwable $e) {
            return new Response('Error generating nav tree: '.htmlspecialchars($e->getMessage()), 500);
        }
    }
}
