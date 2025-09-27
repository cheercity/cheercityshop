<?php

// Cache clearing script for footer modules

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__.'/../vendor/autoload.php';

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

echo '<h1>Cache Clear for Footer Modules</h1>';
echo '<pre>';

try {
    $cache = new FilesystemAdapter('app', 0, __DIR__.'/../var/cache');

    echo "Checking cache for 'footer_modules'...\n";
    $cacheItem = $cache->getItem('footer_modules');

    if ($cacheItem->isHit()) {
        echo "Cache item found. Deleting...\n";
        $cache->deleteItem('footer_modules');
        echo "Cache cleared successfully!\n";
    } else {
        echo "No cache item found for 'footer_modules'.\n";
    }

    // Also clear potential navigation cache
    echo "\nChecking for navigation caches...\n";
    $navCaches = ['nav_main_'.md5(json_encode(['sym_Navigation', ['Status' => '1']]))];

    foreach ($navCaches as $key) {
        $item = $cache->getItem($key);
        if ($item->isHit()) {
            echo "Found nav cache: $key - deleting...\n";
            $cache->deleteItem($key);
        }
    }

    echo "\nAll relevant caches cleared!\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
}

echo '</pre>';
echo '<p><a href="footer-debug.php">Test Footer Modules</a></p>';
