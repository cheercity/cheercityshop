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
                // Use alias field (exact match) for slug lookups
                $res = $fm->find('sym_Navigation', [
                    ['alias' => '==' . $slug],
                ], ['limit' => 1]);
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
        foreach (['categroy_ID', 'category_ID', 'categoryId', 'category_id', 'Category_ID'] as $k) {
            if (isset($fields[$k]) && '' !== (string) $fields[$k]) {
                $categoryId = (string) $fields[$k];
                break;
            }
        }

        // Extract alias (used for routing). Try common variants and fall back to slug if present.
        $alias = null;
        foreach (['alias', 'Alias', 'direct_link_SEO', 'direct_link_seo', 'cat_alias', 'cat_title'] as $k) {
            if (isset($fields[$k]) && '' !== (string) $fields[$k]) {
                $alias = (string) $fields[$k];
                break;
            }
        }
        if (null === $alias && $slug) {
            $alias = $slug;
        }

        // Collect cat_descr_h1 values for all records that have a parent_ID (or variants)
        $childrenHeadlines = [];
        try {
            // Attempt to retrieve many records from sym_Navigation and filter locally.
            // Note: if the FileMaker API supports server-side "not empty" criteria,
            // we could push that down; to be robust across installations we fetch
            // a reasonable page size and filter here.
            $allRes = $fm->find('sym_Navigation', [], ['limit' => 1000]);
            $allRows = $allRes['response']['data'] ?? [];
            foreach ($allRows as $r) {
                $fd = $r['fieldData'] ?? [];
                // look for parent id in known variants
                $hasParent = false;
                foreach (['parent_ID', 'parentId', 'parentid', 'Parent_ID', 'parent'] as $k) {
                    if (isset($fd[$k]) && '' !== (string) $fd[$k]) {
                        $hasParent = true;
                        break;
                    }
                }
                if (!$hasParent) {
                    continue;
                }

                // prefer the canonical field name for the headline
                $headline = trim((string) ($fd['cat_descr_h1'] ?? ''));
                if ($headline === '') {
                    // try a couple of common alternates just in case
                    $headline = trim((string) ($fd['cat_descr_h'] ?? ''));
                }
                if ($headline !== '') {
                    $childrenHeadlines[] = $headline;
                }
            }
            // unique and reindex
            $childrenHeadlines = array_values(array_unique($childrenHeadlines));
        } catch (\Throwable $e) {
            // ignore and leave list empty
            $childrenHeadlines = [];
        }

        return $this->render('menu/menu.html.twig', [
            'title' => $slug ? 'Menu: ' . $slug : 'Menu',
            'footer' => true,
            'slug' => $slug,
            'cat_banner' => $banner,
            'cat_descr_h1' => $headline,
            'nav_fields' => $fields,
            // explicit convenience variable for templates
            'parent_id' => $parentId,
            'category_id' => $categoryId,
            'alias' => $alias,
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
