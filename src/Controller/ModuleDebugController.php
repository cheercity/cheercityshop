<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileMakerClient;

class ModuleDebugController extends AbstractController
{
    #[Route('/module-debug', name: 'module_debug')]
    public function index(FileMakerClient $fm): Response
    {
        // Alle Datensätze aus sym_Module laden
    // FileMaker erwartet: ['query' => [{}]] für alle Datensätze
    // FileMaker erwartet: ['query' => [(object)[]]] für alle Datensätze
    // Nur veröffentlichte Module (Published = 1)
    $records = $fm->find('sym_Module', ['Published' => '1', 'Footer_Status' => '1'], ['limit' => 1000]);
        $rows = array_map(fn($r) => $r['fieldData'] ?? [], $records);
        // Gruppieren nach "Modul" in ein assoziatives Array
        $moduleGroups = [];
        foreach ($rows as $row) {
            $modul = $row['Modul'] ?? 'Sonstige';
            if (!isset($moduleGroups[$modul])) {
                $moduleGroups[$modul] = [];
            }
            $moduleGroups[$modul][] = $row;
        }
        // Sortiere jede Gruppe nach Sortorder (aufsteigend)
        foreach ($moduleGroups as &$items) {
            usort($items, function($a, $b) {
                return (int)($a['Sortorder'] ?? 0) <=> (int)($b['Sortorder'] ?? 0);
            });
        }
        unset($items);
        // Beispiel: Zugriff im Template: moduleGroups['SERVICE & HILFE']
        return $this->render('module_debug/index.html.twig', [
            'rows' => $rows,
            'moduleGroups' => $moduleGroups,
        ]);
    }
}
