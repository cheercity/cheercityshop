<?php

namespace App\Controller;

use App\Service\FileMakerClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class MenuController extends AbstractController
{
    #[Route('/menu/{slug}', name: 'menu_show', defaults: ['slug' => null])]
    public function show(?string $slug, FileMakerClient $fm): Response
    {
        // Fetch the sym_Navigation record matching the slug (alias) when provided
        $record = null;
        $fields = [];
        try {
            if ($slug) {
                // Slug must match the FileMaker 'alias' field exclusively (case tolerant)
                $slugCandidates = ['alias', 'Alias'];
                $res = ['response' => ['data' => []]];
                foreach ($slugCandidates as $sfield) {
                    try {
                        $candidateRes = $fm->find('sym_Navigation', [[$sfield => '=='.$slug]], ['limit' => 1]);
                    } catch (\Throwable $inner) {
                        // skip and continue to next candidate
                        continue;
                    }
                    $candidateRows = $candidateRes['response']['data'] ?? [];
                    // filter rows to only include records where Status == 1
                    $filtered = [];
                    foreach ($candidateRows as $row) {
                        $fd = $row['fieldData'] ?? [];
                        $status = $fd['Status'] ?? $fd['status'] ?? null;
                        if ((string) $status === '1' || (int) $status === 1) {
                            $filtered[] = $row;
                        }
                    }
                    if (!empty($filtered)) {
                        $candidateRes['response']['data'] = $filtered;
                        $res = $candidateRes;
                        // record found for this slug field; stop searching
                        @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Slug lookup: matched field={$sfield} for slug={$slug} (status=1)".PHP_EOL, FILE_APPEND | LOCK_EX);
                        break;
                    }
                }
                // If none matched, log for diagnostics
                if (empty($res['response']['data'])) {
                    @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Slug lookup: no match for slug={$slug} (tried: ".implode(',', $slugCandidates).')'.PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            } else {
                // no slug -> do not fetch a specific record
                $res = ['response' => ['data' => []]];
            }
            $rows = $res['response']['data'] ?? [];
            if (!empty($rows) && isset($rows[0]['fieldData'])) {
                $fields = $rows[0]['fieldData'];
            }
        } catch (\Throwable $e) {
            // swallow and continue – the page should still render
            $fields = [];
        }

        // Map the expected fields
        $banner = trim((string) ($fields['cat_banner'] ?? '')) ?: null;
        $headline = trim((string) ($fields['cat_descr_h1'] ?? '')) ?: null;

        // Normalize and extract parent id from possible FileMaker field name variants
        $parentId = null;
        foreach (['parent_ID', 'parentId', 'parentid', 'Parent_ID', 'parent'] as $k) {
            if (isset($fields[$k]) && '' !== (string) $fields[$k]) {
                $parentId = (string) $fields[$k];
                break;
            }
        }

        // Normalize and extract category id (several possible spellings in FM)
        $categoryId = null;
        // try the legacy misspelled field first to avoid FileMaker 'Field is missing' errors
        foreach (['categroy_ID', 'category_ID', 'categoryId', 'category_id', 'Category_ID'] as $k) {
            if (isset($fields[$k]) && '' !== (string) $fields[$k]) {
                $categoryId = (string) $fields[$k];
                break;
            }
        }

        // If a parentId exists, interpret it as a category key and load all
        // navigation records where category_ID (or variants) == parentId.
        // This reflects the data model: category_ID = parent_ID (the value we loaded above).
        $parentRecords = [];
        $parentFields = [];
        if (null !== $parentId && '' !== $parentId) {
            $triedCandidates = [];
            // prefer the legacy (actual) field name used in the FileMaker layout
            $categoryCandidates = ['categroy_ID', 'category_ID', 'categoryId', 'category_id', 'Category_ID'];
            foreach ($categoryCandidates as $field) {
                try {
                    $res = $fm->find('sym_Navigation', [[$field => '=='.$parentId]], ['limit' => 50]);
                } catch (\Throwable $inner) {
                    // log and continue
                    @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Parent find error for field={$field} parentId={$parentId}: ".$inner->getMessage().PHP_EOL, FILE_APPEND | LOCK_EX);
                    continue;
                }
                $rows = $res['response']['data'] ?? [];
                $triedCandidates[] = $field;
                if (!empty($rows)) {
                    foreach ($rows as $r) {
                        if (!isset($r['fieldData'])) {
                            continue;
                        }
                        $fd = $r['fieldData'];
                        $status = $fd['Status'] ?? $fd['status'] ?? null;
                        if ((string) $status !== '1' && (int) $status !== 1) {
                            // skip inactive records
                            continue;
                        }
                        $parentRecords[] = $fd;
                    }
                    // log which candidate returned results
                    @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Parent find: matched field={$field} for parentId={$parentId}, found=".count($parentRecords)." (status=1 filtered)".PHP_EOL, FILE_APPEND | LOCK_EX);
                    break;
                }
            }

            // If no parent records found by category field, fall back to attempting getRecord
            if (empty($parentRecords)) {
                try {
                    $parentRes = $fm->getRecord('sym_Navigation', (string) $parentId);
                    $pRows = $parentRes['response']['data'] ?? [];
                    foreach ($pRows as $r) {
                        if (!isset($r['fieldData'])) {
                            continue;
                        }
                        $fd = $r['fieldData'];
                        $status = $fd['Status'] ?? $fd['status'] ?? null;
                        if ((string) $status !== '1' && (int) $status !== 1) {
                            continue;
                        }
                        $parentRecords[] = $fd;
                    }
                    if (!empty($parentRecords)) {
                        @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Parent getRecord matched parentId={$parentId}".PHP_EOL, FILE_APPEND | LOCK_EX);
                    } else {
                        @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Parent lookup: no match for parentId={$parentId} (tried category fields: ".implode(',', $triedCandidates).')'.PHP_EOL, FILE_APPEND | LOCK_EX);
                    }
                } catch (\Throwable $e) {
                    @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Parent getRecord error for parentId={$parentId}: ".$e->getMessage().PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            }

            // for backward compatibility expose the first matching record as parent_fields
            if (!empty($parentRecords)) {
                // Normalize image fields for templates: ensure a single 'cat_image' string per parent
                foreach ($parentRecords as &$pr) {
                    if (!is_array($pr)) {
                        continue;
                    }
                    $img = null;
                    // direct field
                    if (!empty($pr['cat_image'])) {
                        $img = (string) $pr['cat_image'];
                    }
                    // try plural field or other variants
                    if (null === $img && !empty($pr['cat_images'])) {
                        $imgs = $pr['cat_images'];
                        if (is_string($imgs)) {
                            $trim = trim($imgs);
                            // JSON list?
                            if (str_starts_with($trim, '[') || str_starts_with($trim, '{')) {
                                $decoded = json_decode($trim, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                                    $img = (string) reset($decoded);
                                }
                            }
                            // fallback: comma-separated list
                            if (null === $img) {
                                $parts = preg_split('/\s*,\s*/', $trim);
                                if (!empty($parts)) {
                                    $img = (string) $parts[0];
                                }
                            }
                        } elseif (is_array($imgs) && !empty($imgs)) {
                            $img = (string) reset($imgs);
                        }
                    }
                    // final assignment if we found an image
                    if ($img) {
                        $pr['cat_image'] = $img;
                    }
                }
                unset($pr);

                $parentFields = $parentRecords[0];
            }
        }

        // (redundant extraction kept for safety) Ensure we still prefer legacy field first
        $categoryId = null;
        foreach (['categroy_ID', 'category_ID', 'categoryId', 'category_id', 'Category_ID'] as $k) {
            if (isset($fields[$k]) && '' !== (string) $fields[$k]) {
                $categoryId = (string) $fields[$k];
                break;
            }
        }

        // Extract alias (used for routing). Use the FileMaker 'alias' field exclusively (case tolerant).
        $alias = null;
        foreach (['alias', 'Alias'] as $k) {
            if (isset($fields[$k]) && '' !== (string) $fields[$k]) {
                $alias = (string) $fields[$k];
                break;
            }
        }

        // Collect cat_descr_h1 values from child records where parent_ID == categoryId
        // Also collect full child records so templates can display images and links
        $childrenHeadlines = [];
        $childrenRecords = [];
        if ($categoryId) {
            try {
                // try matching the parent field exactly against categoryId
                $parentFieldCandidates = ['parent_ID', 'parentId', 'parentid', 'Parent_ID', 'parent'];
                $found = false;
                foreach ($parentFieldCandidates as $pf) {
                    try {
                        $res = $fm->find('sym_Navigation', [[$pf => '=='.$categoryId]], ['limit' => 200]);
                    } catch (\Throwable $inner) {
                        continue;
                    }
                    $rows = $res['response']['data'] ?? [];
                    if (!empty($rows)) {
                        foreach ($rows as $r) {
                            $fd = $r['fieldData'] ?? [];
                            if (empty($fd)) {
                                continue;
                            }
                            $status = $fd['Status'] ?? $fd['status'] ?? null;
                            if ((string) $status !== '1' && (int) $status !== 1) {
                                // skip inactive child records
                                continue;
                            }
                            // collect full child record for templates
                            $childrenRecords[] = $fd;
                            $headline = trim((string) ($fd['cat_descr_h1'] ?? ''));
                            if ('' === $headline) {
                                $headline = trim((string) ($fd['cat_descr_h'] ?? ''));
                            }
                            if ('' !== $headline) {
                                $childrenHeadlines[] = $headline;
                            }
                        }
                        $found = true;
                        @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Children find: matched parent field={$pf} for categoryId={$categoryId}, found=".count($childrenHeadlines).PHP_EOL, FILE_APPEND | LOCK_EX);
                        break;
                    }
                }
                if (!$found) {
                    @file_put_contents(__DIR__.'/../../var/log/legacy-debug-fm.log', date('c')." Children find: no children for categoryId={$categoryId} (tried: ".implode(',', $parentFieldCandidates).')'.PHP_EOL, FILE_APPEND | LOCK_EX);
                }
                $childrenHeadlines = array_values(array_unique($childrenHeadlines));
                // Normalize child images similar to parents: ensure a single 'cat_image' per child
                if (!empty($childrenRecords)) {
                    foreach ($childrenRecords as &$cr) {
                        if (!is_array($cr)) {
                            continue;
                        }
                        $img = null;
                        if (!empty($cr['cat_image'])) {
                            $img = (string) $cr['cat_image'];
                        }
                        if (null === $img && !empty($cr['cat_images'])) {
                            $imgs = $cr['cat_images'];
                            if (is_string($imgs)) {
                                $trim = trim($imgs);
                                if (str_starts_with($trim, '[') || str_starts_with($trim, '{')) {
                                    $decoded = json_decode($trim, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                                        $img = (string) reset($decoded);
                                    }
                                }
                                if (null === $img) {
                                    $parts = preg_split('/\s*,\s*/', $trim);
                                    if (!empty($parts)) {
                                        $img = (string) $parts[0];
                                    }
                                }
                            } elseif (is_array($imgs) && !empty($imgs)) {
                                $img = (string) reset($imgs);
                            }
                        }
                        if ($img) {
                            $cr['cat_image'] = $img;
                        }
                    }
                    unset($cr);
                }
            } catch (\Throwable $e) {
                $childrenHeadlines = [];
            }
        }

        return $this->render('menu/menu.html.twig', [
            'title' => $slug ? 'Menu: '.$slug : 'Menu',
            'footer' => true,
            'slug' => $slug,
            'cat_banner' => $banner,
            'cat_descr_h1' => $headline,
            'nav_fields' => $fields,
            // explicit convenience variable for templates
            'parent_id' => $parentId,
            'parent_fields' => $parentFields,
            'parent_records' => $parentRecords,
            'category_id' => $categoryId,
            'alias' => $alias,
            'children_records' => $childrenRecords,
            // list of cat_descr_h1 for records that have a parent_ID
            'children_headlines' => $childrenHeadlines,
        ]);
    }

    #[Route('/menu', name: 'menu_index')]
    public function index(FileMakerClient $fm): Response
    {
        return $this->show(null, $fm);
    }
}
