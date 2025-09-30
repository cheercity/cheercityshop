<?php
// One-off script to dump Wertelisten map for 'Farbcode'
use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
/** @var \App\Service\WertelistenService $w */
$w = $container->get(\App\Service\WertelistenService::class);
$map = $w->getMap('Farbcode');
echo json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
