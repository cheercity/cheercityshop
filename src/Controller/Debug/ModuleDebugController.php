<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use App\Service\NavService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class ModuleDebugController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/module', name: 'module')]
    public function index(FileMakerClient $fm, LoggerInterface $logger, NavService $nav): Response
    {
        $moduleGroups = [];
        $error = null;

        // Verschiedene mögliche Layout-Namen testen
        $moduleLayout = $this->layouts->get('module', 'sym_Module');
        $possibleLayouts = [$moduleLayout];

        // Direkt sym_Module verwenden (wir wissen, dass es funktioniert)
        try {
            $result = $fm->list($moduleLayout, 1000);

            if (isset($result['response']['data']) && !empty($result['response']['data'])) {
                $modules = [];

                foreach ($result['response']['data'] as $record) {
                    $data = $record['fieldData'] ?? [];
                    $modules[] = $data;
                }

                // Module verarbeiten
                $moduleGroups = $this->processModules($modules);
            }
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('ModuleDebug error: '.$e->getMessage(), ['exception' => $e]);
            $error = $e->getMessage();
        }

        // Hole Footer-spezifische Gruppierung (für die drei festen Footer-Spalten)
        try {
            $footerModules = $nav->getFooterModules(0);
            // Sicherstellen, dass die drei gewünschten Keys existieren
            $wanted = ['SERVICE & HILFE', 'SHOP ON TOUR', 'CHEERCITY.SHOP'];
            foreach ($wanted as $k) {
                if (isset($footerModules[$k])) {
                    $moduleGroups[$k] = $footerModules[$k];
                } elseif (!isset($moduleGroups[$k])) {
                    $moduleGroups[$k] = [];
                }
            }
        } catch (\Throwable $e) {
            // ignore - template will show 'Keine Einträge'
        }

        // Additionally prepare a full grouped-by-Modul view (for debug inspection):
        $groupedByModul = [];
        try {
            $data = $fm->list($moduleLayout, 1000, 1);
            $rows = [];
            if (isset($data['response']['data']) && is_array($data['response']['data'])) {
                foreach ($data['response']['data'] as $rec) {
                    $rows[] = $rec['fieldData'] ?? [];
                }
            }

            // filter Published = 1
            $rows = array_filter($rows, fn ($r) => '1' === (string) ($r['Published'] ?? ''));

            foreach ($rows as $row) {
                $modul = trim((string) ($row['Modul'] ?? 'Ungrouped')) ?: 'Ungrouped';
                if (!isset($groupedByModul[$modul])) {
                    $groupedByModul[$modul] = [];
                }
                $groupedByModul[$modul][] = $row;
            }

            // sort each group by Sortorder
            foreach ($groupedByModul as &$items) {
                usort($items, function ($a, $b) {
                    return (int) ($a['Sortorder'] ?? 0) <=> (int) ($b['Sortorder'] ?? 0);
                });
            }
            unset($items);
        } catch (\Throwable $e) {
            // ignore - debug view optional
        }

        return $this->render('debug/module/index.html.twig', [
            'moduleGroups' => $moduleGroups,
            'groupedByModul' => $groupedByModul,
            'error' => $error ?? (empty($moduleGroups) ? 'Keine Module in verfügbaren Layouts gefunden.' : null),
        ]);
    }

    #[Route('/module/json', name: 'debug_module_json', methods: ['GET'])]
    public function jsonRaw(FileMakerClient $fm, Request $request): Response
    {
        $limit = (int) $request->query->get('limit', 1000);

        try {
            $moduleLayout = $this->layouts->get('module', 'sym_Module');
            $result = $fm->list($moduleLayout, $limit);

            $rows = [];
            foreach ($result['response']['data'] ?? [] as $rec) {
                $rows[] = [
                    'recordId' => $rec['recordId'] ?? null,
                    'fieldData' => $rec['fieldData'] ?? [],
                ];
            }

            return $this->json([
                'count' => count($rows),
                'rows' => $rows,
                'timestamp' => date('c'),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function processModules(array $modules): array
    {
        // Module nach Gruppen sortieren
        $moduleGroups = [];
        foreach ($modules as $module) {
            $gruppe = $module['Gruppe'] ?? 'Ohne Gruppe';
            if (!isset($moduleGroups[$gruppe])) {
                $moduleGroups[$gruppe] = [];
            }
            $moduleGroups[$gruppe][] = $module;
        }

        // Gruppen alphabetisch sortieren
        ksort($moduleGroups);

        // Module innerhalb der Gruppen nach titel sortieren
        foreach ($moduleGroups as &$group) {
            usort($group, function ($a, $b) {
                return strcasecmp($a['titel'] ?? '', $b['titel'] ?? '');
            });
        }

        return $moduleGroups;
    }
}
