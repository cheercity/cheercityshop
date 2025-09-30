<?php
require __DIR__ . '/vendor/autoload.php';
use Symfony\Component\HttpFoundation\Request;
use App\Kernel;
try {
    $kernel = new Kernel('dev', true);
    $kernel->boot();
    $request = Request::create('/kategorie/cheerleader-tank-tops-fuer-women/tank-top-you-had-me-at-cheer');
    $response = $kernel->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo substr($response->getContent(), 0, 8000);
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo get_class($e) . " - " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
