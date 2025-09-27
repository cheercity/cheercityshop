<?php

// Simple test script to debug footer modules
// Place this in public/ directory and call via web browser

use App\Service\FileMakerClient;
use App\Service\NavService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__.'/../vendor/autoload.php';

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

// Check if we can create the basic services
echo '<h1>Footer Module Debug</h1>';
echo '<pre>';

try {
    // Check environment variables
    echo "Environment Variables:\n";
    echo 'FM_HOST: '.($_ENV['FM_HOST'] ?? 'NOT SET')."\n";
    echo 'FM_DB: '.($_ENV['FM_DB'] ?? 'NOT SET')."\n";
    echo 'FM_USER: '.($_ENV['FM_USER'] ?? 'NOT SET')."\n";
    echo 'FM_PASS: '.(isset($_ENV['FM_PASS']) ? '***HIDDEN***' : 'NOT SET')."\n";
    echo "\n";

    // Try to create cache adapter
    echo "Creating cache adapter...\n";
    $cache = new FilesystemAdapter('app', 0, __DIR__.'/../var/cache');
    echo "Cache adapter created successfully\n\n";

    // Try to create FileMaker client
    echo "Creating FileMaker client...\n";
    $fmClient = new FileMakerClient(
        $_ENV['FM_HOST'] ?? 'localhost',
        $_ENV['FM_DB'] ?? 'test',
        $_ENV['FM_USER'] ?? 'admin',
        $_ENV['FM_PASS'] ?? 'password'
    );
    echo "FileMaker client created successfully\n\n";

    // Try to create NavService
    echo "Creating NavService...\n";
    $navService = new NavService($fmClient, $cache);
    echo "NavService created successfully\n\n";

    // Clear cache first
    echo "Clearing footer modules cache...\n";
    $cacheItem = $cache->getItem('footer_modules');
    if ($cacheItem->isHit()) {
        $cache->delete('footer_modules');
        echo "Cache cleared\n";
    } else {
        echo "No cache found\n";
    }
    echo "\n";

    // Test footer modules
    echo "Testing footer modules (bypass cache)...\n";
    $modules = $navService->getFooterModules(0); // TTL 0 = bypass cache

    echo "Result:\n";
    if (empty($modules)) {
        echo "NO MODULES FOUND!\n";
        echo "This could mean:\n";
        echo "- FileMaker connection failed\n";
        echo "- No records with Published='1' AND Footer_Status='1'\n";
        echo "- sym_Module layout doesn't exist\n";
        echo "- Field names don't match\n";
    } else {
        echo 'Found '.count($modules)." module groups:\n";
        foreach ($modules as $moduleName => $items) {
            echo "\nModule: $moduleName\n";
            echo 'Items: '.count($items)."\n";
            foreach ($items as $item) {
                echo '  - '.($item['titel'] ?? 'NO TITLE')."\n";
                echo '    Link: '.($item['lnk'] ?? 'NO LINK')."\n";
                echo '    Sortorder: '.($item['Sortorder'] ?? 'NO SORT')."\n";
            }
        }
    }
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo 'Exception: '.get_class($e)."\n";
    echo 'Message: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString()."\n";
}

echo '</pre>';
