<?php

namespace App\Controller\Debug;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class DebugController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $modules = [
            [
                'name' => 'Auth Debug',
                'description' => 'FileMaker Authentifizierung Schritt-für-Schritt testen',
                'route' => 'debug_auth_debug',
                'icon' => 'fas fa-key',
                'color' => 'warning',
            ],
            [
                'name' => 'Runtime Debug',
                'description' => 'Laufzeitdaten (Session, Customer, Cart – Flags steuerbar)',
                'route' => 'debug_runtime_index',
                'icon' => 'fas fa-microchip',
                'color' => 'danger',
            ],
            [
                'name' => 'Module Debug',
                'description' => 'Zeigt alle Module aus sym_Module nach Gruppen sortiert',
                'route' => 'debug_module',
                'icon' => 'fas fa-puzzle-piece',
                'color' => 'primary',
            ],
            [
                'name' => 'Banner Debug',
                'description' => 'Zeigt alle aktiven Banner aus sym_Banner nach Position gruppiert',
                'route' => 'debug_banner',
                'icon' => 'fas fa-image',
                'color' => 'success',
            ],
            [
                'name' => 'Navigation Debug',
                'description' => 'Zeigt die Navigation-Struktur aus sym_Navigation',
                'route' => 'debug_nav',
                'icon' => 'fas fa-sitemap',
                'color' => 'info',
            ],
            [
                'name' => 'Dev: Dump Articles',
                'description' => 'Erzeugt eine JSON-Dump-Datei aller Artikel (dev helper)',
                'route' => 'debug_dump_articles',
                'icon' => 'fas fa-file-alt',
                'color' => 'primary',
            ],
            [
                'name' => 'Dev: Dump Items',
                'description' => 'Erzeugt eine JSON-Dump-Datei der Artikel-Items (dev helper, use include_zero_stock=1)',
                'route' => 'debug_dump_items',
                'icon' => 'fas fa-table',
                'color' => 'secondary',
            ],
            [
                'name' => 'Content Debug',
                'description' => 'Zeigt alle Inhalte aus sym_Content',
                'route' => 'debug_content',
                'icon' => 'fas fa-file-alt',
                'color' => 'secondary',
            ],
            [
                'name' => 'Product Debug',
                'description' => 'Zeigt alle Produkte aus sym_Product',
                'route' => 'debug_products',
                'icon' => 'fas fa-shopping-bag',
                'color' => 'success',
            ],
            [
                'name' => 'Artikel JSON',
                'description' => 'Gibt sym_Artikel als einfaches JSON (Published = 1) aus',
                'route' => 'debug_sym_artikel_json',
                'icon' => 'fas fa-file-csv',
                'color' => 'primary',
            ],
            [
                'name' => 'User Debug',
                'description' => 'Zeigt alle User aus sym_Users',
                'route' => 'debug_users',
                'icon' => 'fas fa-users',
                'color' => 'info',
            ],
            [
                'name' => 'Cache Debug',
                'description' => 'Cache-Management und Informationen',
                'route' => 'debug_cache_info',
                'icon' => 'fas fa-memory',
                'color' => 'dark',
            ],
        ];

        return $this->render('debug/index.html.twig', [
            'modules' => $modules,
        ]);
    }
}
