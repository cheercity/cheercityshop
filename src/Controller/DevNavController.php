<?php

namespace App\Controller;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DevNavController extends AbstractController
{
    #[Route('/dev/nav', name: 'dev_nav')]
    public function nav(FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): Response
    {
        // Compute the navigation analysis and render it inside a shop-like layout
        $analysis = $this->analyzeNavigation($fm, $layouts, $logger);

        return $this->render('dev/nav.html.twig', [
            // keep template compatible with layout expectations
            'footer' => true,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/dev/nav/inspect', name: 'dev_nav_inspect')]
    public function inspect(FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): Response
    {
        $analysisResult = $this->analyzeNavigation($fm, $layouts, $logger);

        return $this->json($analysisResult);
    }

    public function analyzeNavigation(FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): array
    {
        try {
            $navLayout = $layouts->get('navigation', 'sym_Navigation');
        } catch (\Throwable $e) {
            return ['error' => 'Layout registry: '.$e->getMessage()];
        }

        try {
            $res = $fm->list($navLayout, 2000, 1);
        } catch (\Throwable $e) {
            $logger->error('FileMaker list sym_Navigation failed: '.$e->getMessage());

            return ['error' => 'FileMaker list failed: '.$e->getMessage()];
        }

        $records = $res['response']['data'] ?? [];

        $fields = [];
        $samples = [];
        $byInternalId = [];
        // we ignore Global_ID for navigation structure; use categroy_ID as primary
        $mapCat = [];
        $slugMap = [];
        $linkMap = [];
        $orphans = [];

        foreach ($records as $idx => $rec) {
            $f = $rec['fieldData'] ?? [];
            foreach (array_keys($f) as $k) {
                $fields[$k] = true;
            }

            // prefer categroy_ID as canonical identifier for navigation
            $catId = isset($f['categroy_ID']) ? (string) $f['categroy_ID'] : '';
            $recId = $rec['recordId'] ?? null;

            // choose an internal ID that is stable: prefer categroy_ID, then recordId
            $internalId = '' !== $catId ? $catId : ($recId ?: 'rec_'.$idx);

            $title = trim((string) ($f['cat_title'] ?? $f['titel'] ?? $f['title'] ?? $f['cat_descr_h1'] ?? '')) ?: null;
            $link = $f['cat_link'] ?? $f['cat_link'] ?? $f['lnk'] ?? $f['link'] ?? null;
            if ($link) {
                $linkMap[$link][] = $internalId;
            }
            $slug = $f['alias'] ?? $f['Alias'] ?? null;
            if ($slug) {
                $slugMap[$slug][] = $internalId;
            }

            $byInternalId[$internalId] = [
                'internalId' => $internalId,
                // keep Global_ID as reference but do not use it for structure
                'globalId' => isset($f['Global_ID']) ? (string) $f['Global_ID'] : null,
                'catId' => $catId,
                'recId' => $recId,
                'title' => $title,
                'fields' => $f,
                'parentRaw' => (isset($f['parent_ID']) ? (string) $f['parent_ID'] : (isset($f['parentId']) ? (string) $f['parentId'] : (isset($f['Parent_ID']) ? (string) $f['Parent_ID'] : null))),
            ];

            if ('' !== $catId) {
                $mapCat[$catId] = $internalId;
            }

            if (count($samples) < 20) {
                $samples[] = ['internalId' => $internalId, 'globalId' => $byInternalId[$internalId]['globalId'] ?? null, 'catId' => $catId, 'title' => $title, 'parentRaw' => $byInternalId[$internalId]['parentRaw'] ?? null];
            }
        }

        // Resolve parent references to internalIds using categroy_ID only
        $children = [];
        $unresolvedParents = [];
        foreach ($byInternalId as $iid => &$item) {
            $pRaw = $item['parentRaw'];
            $parentResolved = null;
            if (null === $pRaw || '' === $pRaw || '0' === $pRaw) {
                $parentResolved = null;
            } else {
                // parent is expected to reference categroy_ID (or occasionally an internal id)
                if ('' !== $pRaw && isset($mapCat[$pRaw])) {
                    $parentResolved = $mapCat[$pRaw];
                } elseif (isset($byInternalId[$pRaw])) {
                    $parentResolved = $pRaw;
                } else {
                    // try numeric/string matching against categroy map
                    $found = false;
                    $pStr = (string) $pRaw;
                    if ('' !== $pStr && isset($mapCat[$pStr])) {
                        $parentResolved = $mapCat[$pStr];
                        $found = true;
                    }
                    if (!$found) {
                        $unresolvedParents[$iid] = $pRaw;
                    }
                }
            }
            $item['parentResolved'] = $parentResolved;
            if (null !== $parentResolved) {
                $children[$parentResolved][] = $iid;
            }
        }
        unset($item);

        // build roots (nodes without parentResolved)
        $roots = [];
        foreach ($byInternalId as $iid => $it) {
            if (null === $it['parentResolved']) {
                $roots[] = $iid;
            }
        }

        // helper: only include records that are active in FileMaker (Status = 1) when present
        $isActive = function (array $f): bool {
            $statusKeys = ['Status', 'status', 'active', 'Active', 'cat_status'];
            $found = false;
            foreach ($statusKeys as $k) {
                if (isset($f[$k])) {
                    $found = true;
                    $v = trim((string) $f[$k]);
                    if ('1' === $v || 'true' === strtolower($v) || 'yes' === strtolower($v)) {
                        return true;
                    }

                    return false;
                }
            }

            // if no explicit status field is present, keep the item (backwards-compat)
            return true;
        };

        // Grouping by cat00, cat01, cat02 (only active records)
        $groups = ['cat00' => [], 'cat01' => [], 'cat02' => []];
        foreach ($byInternalId as $iid => $it) {
            $f = $it['fields'];
            if (!$isActive($f)) {
                // skip inactive records
                continue;
            }
            foreach (['cat00', 'cat01', 'cat02'] as $gk) {
                if (isset($f[$gk]) && '' !== trim((string) $f[$gk])) {
                    $groups[$gk][trim((string) $f[$gk])][] = $iid;
                }
            }
        }

        // compute depths by DFS
        $maxDepth = 0;
        $depths = [];
        $cycles = [];
        $visitedGlobal = [];

        $stack = [];
        $dfs = function ($node, $depth, $ancestors) use (&$dfs, &$children, &$maxDepth, &$depths, &$cycles) {
            $depths[$node] = $depth;
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
            $anc = $ancestors;
            $anc[] = $node;
            if (isset($children[$node])) {
                foreach ($children[$node] as $c) {
                    if (in_array($c, $anc, true)) {
                        $cycles[] = array_merge($anc, [$c]);
                        continue;
                    }
                    $dfs($c, $depth + 1, $anc);
                }
            }
        };

        foreach ($roots as $r) {
            $dfs($r, 0, []);
        }

        // duplicates
        $duplicateSlugs = array_filter(array_map(function ($ids) { return count($ids) > 1 ? $ids : null; }, $slugMap));
        $duplicateLinks = array_filter(array_map(function ($ids) { return count($ids) > 1 ? $ids : null; }, $linkMap));

        // produce a small tree structure for UI
        $buildTree = function ($node) use (&$children, &$byInternalId, &$buildTree) {
            $n = $byInternalId[$node];
            $out = ['id' => $node, 'title' => $n['title'] ?? $node, 'children' => []];
            if (isset($children[$node])) {
                foreach ($children[$node] as $c) {
                    $out['children'][] = $buildTree($c);
                }
            }

            return $out;
        };

        $tree = [];
        foreach ($roots as $r) {
            $tree[] = $buildTree($r);
        }

        // helper: slugify titles
        $slugify = function (string $text): string {
            // safe transliteration: try ICU transliterator for Latin; fallback to iconv
            $out = $text;
            if (function_exists('transliterator_transliterate')) {
                try {
                    $tmp = @transliterator_transliterate('Any-Latin; Latin-ASCII', $out);
                    if (null !== $tmp && false !== $tmp) {
                        $out = $tmp;
                    }
                } catch (\Throwable $e) {
                    // ignore and fallback
                }
            } else {
                $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $out);
                if (false !== $tmp && null !== $tmp) {
                    $out = $tmp;
                }
            }
            $out = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $out);
            $out = trim($out, '-');

            return strtolower($out ?: 'n-a');
        };

        // Build grouped tree: cat00 -> cat01 -> cat02
        $groupedTree = [];
        // helper to map ids to titles (small sample)
        $titleFor = function ($id) use ($byInternalId) {
            return $byInternalId[$id]['title'] ?? (string) $id;
        };

        if (isset($groups['cat00'])) {
            foreach ($groups['cat00'] as $val00 => $ids00) {
                // derive representative title for this cat00 value from first internal id
                $repTitle00 = null;
                foreach ($ids00 as $iidTemp) {
                    if (!empty($byInternalId[$iidTemp]['title'])) {
                        $repTitle00 = $byInternalId[$iidTemp]['title'];
                        break;
                    }
                }
                $repTitle00 = $repTitle00 ?? ('cat00-'.$val00);
                $slug00 = $slugify($repTitle00);
                // find cat01 values that intersect with ids00
                $cat01children = [];
                if (isset($groups['cat01'])) {
                    foreach ($groups['cat01'] as $val01 => $ids01global) {
                        $ids01 = array_values(array_intersect($ids00, $ids01global));
                        if (0 === count($ids01)) {
                            continue;
                        }
                        // derive representative title for cat01
                        $repTitle01 = null;
                        foreach ($ids01 as $iidTemp) {
                            if (!empty($byInternalId[$iidTemp]['title'])) {
                                $repTitle01 = $byInternalId[$iidTemp]['title'];
                                break;
                            }
                        }
                        $repTitle01 = $repTitle01 ?? ('cat01-'.$val01);
                        $slug01 = $slugify($repTitle01);
                        // find cat02 under this cat01
                        $cat02children = [];
                        if (isset($groups['cat02'])) {
                            foreach ($groups['cat02'] as $val02 => $ids02global) {
                                $ids02 = array_values(array_intersect($ids01, $ids02global));
                                if (0 === count($ids02)) {
                                    continue;
                                }
                                // rep title for cat02
                                $repTitle02 = null;
                                foreach ($ids02 as $iidTemp) {
                                    if (!empty($byInternalId[$iidTemp]['title'])) {
                                        $repTitle02 = $byInternalId[$iidTemp]['title'];
                                        break;
                                    }
                                }
                                $repTitle02 = $repTitle02 ?? ('cat02-'.$val02);
                                $slug02 = $slugify($repTitle02);
                                $cat02children[] = [
                                    'value' => $repTitle02,
                                    'slug' => $slug02,
                                    'link' => '/kategorie/'.$slug02,
                                    'count' => count($ids02),
                                    'sample' => array_map($titleFor, array_slice($ids02, 0, 5)),
                                    'ids' => $ids02,
                                ];
                            }
                        }
                        // decide link for cat01: if it has cat02 children => /kategorie/{slug01} else /menu/{slug01}
                        $link01 = count($cat02children) > 0 ? ('/kategorie/'.$slug01) : ('/menu/'.$slug01);
                        $cat01children[] = [
                            'value' => $repTitle01,
                            'slug' => $slug01,
                            'link' => $link01,
                            'count' => count($ids01),
                            'sample' => array_map($titleFor, array_slice($ids01, 0, 5)),
                            'ids' => $ids01,
                            'children' => $cat02children,
                        ];
                    }
                }
                // decide link for cat00: if any cat01 child has cat02 children -> /kategorie/{slug00} else /menu/{slug00}
                $hasCat02 = false;
                foreach ($cat01children as $c1) {
                    if (!empty($c1['children'])) {
                        $hasCat02 = true;
                        break;
                    }
                }
                $link00 = $hasCat02 ? ('/kategorie/'.$slug00) : ('/menu/'.$slug00);
                $groupedTree[] = [
                    'value' => $repTitle00,
                    'slug' => $slug00,
                    'link' => $link00,
                    'count' => count($ids00),
                    'sample' => array_map($titleFor, array_slice($ids00, 0, 5)),
                    'ids' => $ids00,
                    'children' => $cat01children,
                ];
            }
        }

        return [
            'recordCount' => count($records),
            'uniqueFieldNames' => array_values(array_keys($fields)),
            'sample' => $samples,
            'mapCatCount' => count($mapCat),
            'unresolvedParents' => $unresolvedParents,
            'orphans' => array_keys($unresolvedParents),
            'duplicateSlugs' => $duplicateSlugs,
            'duplicateLinks' => $duplicateLinks,
            'maxDepth' => $maxDepth,
            'cyclesDetected' => $cycles,
            'tree' => $tree,
            'groups' => $groups,
            'groupedTree' => $groupedTree,
        ];
    }

    #[Route('/dev/nav/tree', name: 'dev_nav_tree')]
    public function tree(FileMakerClient $fm, FileMakerLayoutRegistry $layouts, LoggerInterface $logger): Response
    {
        $analysis = $this->analyzeNavigation($fm, $layouts, $logger);

        // render a simple tree view template
        return $this->render('dev/nav_tree.html.twig', [
            'analysis' => $analysis,
        ]);
    }
}
