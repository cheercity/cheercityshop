<?php
// Temporary script to dump NavService alias->cat_sort map
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Boot the kernel manually
$env = getenv('APP_ENV') ?: 'dev';
$debug = (bool) ($env !== 'prod');

if (class_exists(Dotenv::class)) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$kernelClass = require dirname(__DIR__).'/config/bootstrap.php';
$kernel = new $kernelClass($env, $debug);
$kernel->boot();
$container = $kernel->getContainer();

if (!$container->has('App\\Service\\NavService')) {
    echo "NavService not found in container\n";
    exit(1);
}

/** @var \App\Service\NavService $nav */
$nav = $container->get('App\\Service\\NavService');
// Force fresh fetch (ttl=0) to bypass cache and ensure aliasMap gets built
$menu = $nav->getMenu(null, ['Status' => '1'], 0);
$map = $nav->getAliasToCatSort();

echo json_encode(['count' => count($map), 'map' => $map], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";

$kernel->shutdown();
