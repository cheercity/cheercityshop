<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

final class NavService
{
    /**
     * Last built alias->cat_sort map (kept for quick access after getMenu() runs)
     * @var array<string,string>
     */
    private array $lastAliasMap = [];

    public function __construct(
        private FileMakerClient $fm,
        private CacheItemPoolInterface $cache,
        private string $navigationLayout = 'sym_Navigation',
        private string $moduleLayout = 'sym_Module',
    ) {
    }

    public function getMenu(
        ?string $layout = null,
        array $filter = ['Status' => '1'],
        int $ttl = 1800,
    ): array {
        $layout = $layout ?: $this->navigationLayout;
        $cacheKey = 'nav_main_'.md5(json_encode([$layout, $filter]));
        $item = null;
        // When ttl > 0 use cache; when ttl === 0 bypass cache to force fresh fetch (dev use-case)
        if ($ttl > 0) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $val = $item->get();
                // Backwards-compatible: older cache entries stored the tree directly.
                if (is_array($val) && isset($val['tree']) && isset($val['aliasMap'])) {
                    // store aliasMap for getter convenience and return tree as before
                    $this->lastAliasMap = $val['aliasMap'];
                    return $val['tree'];
                }
                return $val;
            }
        }

        $res = $this->fm->find($layout, $filter, ['limit' => 300]);
        // FileMakerClient::find() returns the full FM response array. Extract the records list if present.
        $records = [];
        if (isset($res['response']) && isset($res['response']['data']) && is_array($res['response']['data'])) {
            $records = $res['response']['data'];
        } elseif (is_array($res)) {
            // Defensive: if find() returned a plain array of records, accept it.
            $records = $res;
        }

    $flat = array_map(fn ($r) => $r['fieldData'] ?? [], $records);
        // Ensure we only work with active navigation items (Status == 1)
        $flat = array_values(array_filter($flat, function ($x) {
            $status = $x['Status'] ?? $x['status'] ?? null;
            return ((string) $status === '1' || (int) $status === 1);
        }));

        // Detect if records use legacy cat_sort (AA BB CC) scheme
        $usesCatSort = false;
        foreach ($flat as $r) {
            if (!empty($r['cat_sort'])) {
                $usesCatSort = true;
                break;
            }
        }

        // Build alias -> cat_sort map (we only need alias and cat_sort per your spec)
        // If cat_sort is missing, fall back to any available Category ID variant so
        // slugs without explicit cat_sort in FileMaker can still be resolved.
        $aliasMap = [];
        foreach ($flat as $r) {
            $alias = trim((string) ($r['alias'] ?? $r['Alias'] ?? ''));
            if ('' === $alias) {
                continue;
            }
            // primary: cat_sort
            $catSortRaw = trim((string) ($r['cat_sort'] ?? ''));
            if ($catSortRaw !== '') {
                $aliasMap[strtolower($alias)] = $catSortRaw;
                continue;
            }

            // fallback 1: any Category ID variant (Category_ID / categroy_ID / categoryId)
            $categoryFallback = trim((string) ($r['Category_ID'] ?? $r['category_ID'] ?? $r['categroy_ID'] ?? $r['categoryId'] ?? ''));
            if ($categoryFallback !== '') {
                $aliasMap[strtolower($alias)] = $categoryFallback;
                continue;
            }

            // fallback 2: sometimes the hierarchical fields (cat00/cat01/cat02) carry the
            // grouping code — prefer the most specific non-empty (cat02 -> cat01 -> cat00)
            $cat02 = trim((string) ($r['cat02'] ?? ''));
            $cat01 = trim((string) ($r['cat01'] ?? ''));
            $cat00 = trim((string) ($r['cat00'] ?? ''));
            if ($cat02 !== '') {
                $aliasMap[strtolower($alias)] = $cat02;
                continue;
            }
            if ($cat01 !== '') {
                $aliasMap[strtolower($alias)] = $cat01;
                continue;
            }
            if ($cat00 !== '') {
                $aliasMap[strtolower($alias)] = $cat00;
                continue;
            }
            // if all fail: do not set a mapping (leave null)
        }
        // remember for getter if we end up returning immediately
        $this->lastAliasMap = $aliasMap;

        if ($usesCatSort) {
            // Build by cat_sort code (AA BB CC -> level 0/1/2)
            $byCode = [];
            foreach ($flat as $x) {
                $catSortRaw = (string) ($x['cat_sort'] ?? '');
                $catSort = str_pad(preg_replace('/[^0-9]/', '', $catSortRaw), 6, '0', STR_PAD_RIGHT);
                $aa = substr($catSort, 0, 2);
                $bb = substr($catSort, 2, 2);
                $cc = substr($catSort, 4, 2);
                $code = $aa.$bb.$cc; // 6-digit code

                // Label: use cat_title (explicit) as navigation text
                $label = trim((string) ($x['cat_title'] ?? $x['title'] ?? $x['cat_descr_h1'] ?? '')) ?: '(ohne Titel)';
                $rawLink = trim((string) ($x['cat_link'] ?? $x['link'] ?? ''));
                // Prefer alias-based internal menu links for internal navigation
                $alias = trim((string) ($x['alias'] ?? $x['Alias'] ?? ''));

                // ID: prefer the FileMaker field 'category_ID' (canonical) then fallback to legacy 'categroy_ID'
                $id = (string) ($x['category_ID'] ?? $x['categroy_ID'] ?? $code ?: uniqid('nav_', true));

                // Build href according to mapping: if cat02 is non-empty -> /kategorie/{alias} else /menu/{alias}
                $cat02 = trim((string) ($x['cat02'] ?? ''));
                if ('' !== $alias) {
                    // Note: cat02 non-empty -> /kategorie, empty -> /menu
                    $href = ($cat02 !== '' ? '/kategorie/' : '/menu/') . $alias;
                } elseif ('' !== $rawLink) {
                    $href = $rawLink;
                } else {
                    $href = '/';
                }
                // If rawLink is an absolute external URL (http(s)://), respect it instead of alias
                if (!empty($rawLink) && preg_match('#^https?://#i', $rawLink)) {
                    $href = $rawLink;
                }
                $order = (int) $catSort;

                $level = 0;
                if ('00' !== $bb && '00' === $cc) {
                    $level = 1;
                }
                if ('00' !== $cc) {
                    $level = 2;
                }

                $byCode[$code] = [
                    'id' => $id,
                    'code' => $code,
                    'level' => $level,
                    'label' => $label,
                    // expose alias/slug so templates can build /menu/{slug} links
                    'alias' => $alias,
                    'slug' => $alias ?: null,
                    'cat_descr_h1' => isset($x['cat_descr_h1']) ? (string) $x['cat_descr_h1'] : null,
                    // keep both href (full computed link) and link for compatibility
                    'href' => $href,
                    'link' => $href,
                    'target' => '_self',
                    'order' => $order,
                    'children' => [],
                ];
            }

            // Attach children based on code hierarchy
            $root = [];
            foreach ($byCode as $code => &$node) {
                $aa = substr($code, 0, 2);
                $bb = substr($code, 2, 2);
                $cc = substr($code, 4, 2);
                if (2 === $node['level']) {
                    $parentCode = $aa.$bb.'00';
                    if (isset($byCode[$parentCode])) {
                        $byCode[$parentCode]['children'][] = &$node;
                    } else {
                        $root[] = &$node;
                    }
                } elseif (1 === $node['level']) {
                    $parentCode = $aa.'0000';
                    if (isset($byCode[$parentCode])) {
                        $byCode[$parentCode]['children'][] = &$node;
                    } else {
                        $root[] = &$node;
                    }
                } else {
                    // level 0
                    $root[] = &$node;
                }
            }

            // sort recursively by 'order'
            $sortChildren = function (&$nodes) use (&$sortChildren) {
                usort($nodes, fn ($a, $b) => $a['order'] <=> $b['order']);
                foreach ($nodes as &$n) {
                    if (!empty($n['children'])) {
                        $sortChildren($n['children']);
                    }
                }
            };
            $sortChildren($root);

            // Save to cache, write diagnose log and return
            // Normalize legacy short codes coming from FileMaker (e.g. 'cat_00', 'cat_01', 'cat_02')
            $normalizeHref = function (&$nodes) use (&$normalizeHref) {
                foreach ($nodes as &$n) {
                    if (isset($n['href'])) {
                        $h = trim((string) $n['href']);
                            if (preg_match('/^cat_01$/', $h)) {
                            $n['href'] = '/menu';
                        } elseif (preg_match('/^cat_02$/', $h)) {
                            $n['href'] = '/kategorie';
                        }
                    }
                    if (!empty($n['children'])) {
                        $normalizeHref($n['children']);
                    }
                }
                unset($n);
            };
            $normalizeHref($root);

            if ($ttl > 0 && null !== $item) {
                // Store tree and aliasMap together for future reads
                $item->set(['tree' => $root, 'aliasMap' => $aliasMap])->expiresAfter($ttl);
                $this->cache->save($item);
            }
            $this->writeDiagnoseLog($layout, $filter, $ttl, $root);

            // persist aliasMap for runtime getter
            $this->lastAliasMap = $aliasMap;

            return $root;
        }

        // Fallback: parent_id based normalization
        $norm = array_map(function (array $x) {
            // Label: use cat_title (explicit) as navigation text
            $label = trim((string) ($x['cat_title'] ?? $x['title'] ?? $x['cat_descr_h1'] ?? '')) ?: '(ohne Titel)';
            $rawLink = trim((string) ($x['cat_link'] ?? $x['link'] ?? ''));
            $alias = trim((string) ($x['alias'] ?? $x['Alias'] ?? ''));

            // id = category_ID preferred then fallback to categroy_ID
            $id = (string) ($x['category_ID'] ?? $x['categroy_ID'] ?? '');
            $parent_id = (string) ($x['parent_ID'] ?? $x['parentId'] ?? '');

            $cat02 = trim((string) ($x['cat02'] ?? ''));
            if ('' !== $alias) {
                // Note: cat02 non-empty -> /kategorie, empty -> /menu
                    $href = ($cat02 !== '' ? '/kategorie/' : '/menu/') . $alias;
            } elseif ('' !== $rawLink) {
                $href = $rawLink;
            } else {
                $href = '/';
            }
            if (!empty($rawLink) && preg_match('#^https?://#i', $rawLink)) {
                $href = $rawLink;
            }

            $target = '_self';

            return [
                'id' => $id,
                'parent_id' => $parent_id,
                'label' => $label,
                // expose alias/slug so templates can build /menu/{slug} links
                'alias' => $alias,
                'slug' => $alias ?: null,
                'cat_descr_h1' => isset($x['cat_descr_h1']) ? (string) $x['cat_descr_h1'] : null,
                'href' => $href,
                'link' => $href,
                'target' => $target,
                'order' => (int) ($x['cat_sort'] ?? 0),
            ];
        }, $flat);

        usort($norm, fn ($a, $b) => $a['order'] <=> $b['order']);

        $byId = [];
        foreach ($norm as $n) {
            if ('' === trim((string) $n['label'])) {
                continue;
            }
            $n['children'] = [];
            $key = $n['id'] ?: uniqid('noid_', true);
            $byId[$key] = $n;
        }

        $root = [];
        foreach ($byId as $id => &$n) {
            $pid = (string) ($n['parent_id'] ?? '');
            if ('' !== $pid && '0' !== $pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$n;
            } else {
                $root[] = &$n;
            }
        }

        $sortChildren = function (&$nodes) use (&$sortChildren) {
            usort($nodes, fn ($a, $b) => $a['order'] <=> $b['order']);
            foreach ($nodes as &$n) {
                if (!empty($n['children'])) {
                    $sortChildren($n['children']);
                }
            }
        };
        $sortChildren($root);

        // Normalize legacy short codes coming from FileMaker in the fallback tree as well
        $normalizeHref = function (&$nodes) use (&$normalizeHref) {
            foreach ($nodes as &$n) {
                    if (isset($n['href'])) {
                    $h = trim((string) $n['href']);
                    if (preg_match('/^cat_0[01]$/', $h)) {
                        $n['href'] = '/menu';
                    } elseif (preg_match('/^cat_02$/', $h)) {
                        $n['href'] = '/kategorie';
                    }
                }
                if (!empty($n['children'])) {
                    $normalizeHref($n['children']);
                }
            }
            unset($n);
        };

        $normalizeHref($root);

        if ($ttl > 0 && null !== $item) {
            $item->set(['tree' => $root, 'aliasMap' => $aliasMap])->expiresAfter($ttl);
            $this->cache->save($item);
        }
        $this->writeDiagnoseLog($layout, $filter, $ttl, $root);

        // persist aliasMap for runtime getter
        $this->lastAliasMap = $aliasMap;

        return $root;
    }

    /**
     * Return alias => cat_sort mapping built during the last getMenu() run (or loaded from cache).
     * Keys are normalized to lowercase for robust lookup.
     * @return array<string,string>
     */
    public function getAliasToCatSort(): array
    {
        return $this->lastAliasMap;
    }

    private function writeDiagnoseLog(string $layout, array $filter, int $ttl, array $tree): void
    {
        try {
            $stats = [
                'total' => 0,
                'max_depth' => 0,
                'missing_id' => 0,
                'missing_href' => 0,
            ];
            $walk = function (array $nodes, int $depth = 0) use (&$walk, &$stats) {
                $stats['max_depth'] = max($stats['max_depth'], $depth);
                foreach ($nodes as $n) {
                    ++$stats['total'];
                    if (empty($n['id'])) {
                        ++$stats['missing_id'];
                    }
                    if (empty($n['href'])) {
                        ++$stats['missing_href'];
                    }
                    if (!empty($n['children'])) {
                        $walk($n['children'], $depth + 1);
                    }
                }
            };
            $walk($tree, 0);

            $logPath = dirname(__DIR__, 2).'/var/log/nav_diagnose.log';
            if (!is_dir(dirname($logPath))) {
                @mkdir(dirname($logPath), 0775, true);
            }

            $json = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $maxLen = 20000;
            if (false === $json) {
                $sample = 'unable to encode tree';
            } else {
                $sample = mb_substr($json, 0, $maxLen);
                if (mb_strlen($json) > $maxLen) {
                    $sample .= "\n... (truncated) ";
                }
            }

            $entry = sprintf("[%s] nav_diagnose layout=%s ttl=%d stats=%s\nSAMPLE_JSON:\n%s\n\n", date('c'), $layout, $ttl, json_encode($stats), $sample);
            @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }

    public function getFooterModules(int $ttl = 300): array
    {
        // bump cache key to ensure layout changes are reflected when deploying
        $cacheKey = 'footer_modules_v2';
        $item = null;
        // If ttl > 0 use cache; if ttl === 0 we explicitly bypass cache for fresh results
        if ($ttl > 0) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }
        }

        try {
            // Only require Published = 1 as requested. Use list() to get records and extract fieldData.
            $data = $this->fm->list('sym_Module', 1000, 1);
            $rows = [];
            if (isset($data['response']['data']) && is_array($data['response']['data'])) {
                foreach ($data['response']['data'] as $rec) {
                    $rows[] = $rec['fieldData'] ?? [];
                }
            }
            // We only want exactly these three footer containers (headlines)
            // Keep keys in the desired display order: SERVICE & HILFE, SHOP ON TOUR, CHEERCITY.SHOP
            $wanted = ['SERVICE & HILFE', 'SHOP ON TOUR', 'CHEERCITY.SHOP'];
            $footerCols = array_fill_keys($wanted, []);

            // Map each row to exactly one of the three footer columns using normalized substring matching.
            // This is more tolerant than strict equality but still deterministic and avoids duplicates.
            foreach ($rows as $row) {
                // Only include published rows
                $publishedRaw = strtolower(trim((string) ($row['Published'] ?? $row['published'] ?? $row['PUBLISHED'] ?? '')));
                if (!in_array($publishedRaw, ['1', 'true', 'yes'], true)) {
                    continue;
                }

                $modulRaw = trim((string) ($row['Modul'] ?? ''));
                $linkRaw = trim((string) ($row['link'] ?? $row['lnk'] ?? $row['cat_link'] ?? ''));

                // Normalize: lowercase and remove punctuation to allow fuzzy substring checks
                $modulNorm = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $modulRaw));
                $linkNorm = strtolower(preg_replace('/[^a-z0-9\s\.\-]/i', ' ', $linkRaw));
                $hay = trim($modulNorm.' '.$linkNorm);

                $placed = false;

                // Priority: CHEERCITY.SHOP
                // Match explicit 'cheercity' or 'cheercity-shop' variants. Avoid matching generic 'cheer' which may appear in other domains.
                if (preg_match('/cheercity(?:[\s\-]?shop)?/i', $hay) || preg_match('/cheercity-shop/i', $hay)) {
                    $footerCols['CHEERCITY.SHOP'][] = $row;
                    $placed = true;
                }

                // Next: SHOP ON TOUR
                if (!$placed && preg_match('/\b(on[\s\-]*tour|shop[\s\-]*on[\s\-]*tour|\btour\b)/i', $hay)) {
                    $footerCols['SHOP ON TOUR'][] = $row;
                    $placed = true;
                }

                // Next: SERVICE & HILFE
                if (!$placed && preg_match('/service|hilfe|support|kontakt|help|kunden/i', $hay)) {
                    $footerCols['SERVICE & HILFE'][] = $row;
                    $placed = true;
                }

                // If not placed, ignore to avoid polluting columns
            }

            // Sort each column by Sortorder
            foreach ($footerCols as $k => $items) {
                usort($items, function ($a, $b) {
                    return (int) ($a['Sortorder'] ?? 0) <=> (int) ($b['Sortorder'] ?? 0);
                });
                $footerCols[$k] = $items;
            }

            // Only write to cache when ttl > 0
            if ($ttl > 0 && null !== $item) {
                $item->set($footerCols);
                $item->expiresAfter($ttl);
                $this->cache->save($item);
            }

            return $footerCols;
        } catch (\Throwable $e) {
            // Bei Fehlern leeres Array zurückgeben
            return [];
        }
    }

    /**
     * Return sym_Module rows grouped by the Modul field (Published = 1)
     * Each group's items are sorted by Sortorder.
     * This mirrors the debug view's grouping logic.
     */
    public function getFooterGroupsByModul(int $ttl = 300): array
    {
        try {
            $data = $this->fm->list('sym_Module', 1000, 1);
            $rows = [];
            if (isset($data['response']['data']) && is_array($data['response']['data'])) {
                foreach ($data['response']['data'] as $rec) {
                    $rows[] = $rec['fieldData'] ?? [];
                }
            }

            $groups = [];
            foreach ($rows as $row) {
                $publishedRaw = strtolower(trim((string) ($row['Published'] ?? $row['published'] ?? $row['PUBLISHED'] ?? '')));
                if (!in_array($publishedRaw, ['1', 'true', 'yes'], true)) {
                    continue;
                }
                $modul = trim((string) ($row['Modul'] ?? '(kein Modul)'));
                if ('' === $modul) {
                    $modul = '(kein Modul)';
                }
                if (!isset($groups[$modul])) {
                    $groups[$modul] = [];
                }
                $groups[$modul][] = $row;
            }

            // Sort each group by Sortorder (numeric)
            foreach ($groups as $k => $items) {
                usort($items, function ($a, $b) {
                    return (int) ($a['Sortorder'] ?? 0) <=> (int) ($b['Sortorder'] ?? 0);
                });
                $groups[$k] = $items;
            }

            return $groups;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
