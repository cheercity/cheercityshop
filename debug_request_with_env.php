<?php
// Read .env.local and putenv relevant FM_* variables so container can read them
$envFile = __DIR__ . '/.env.local';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
            $k = $m[1];
            $v = $m[2];
            // strip surrounding quotes if present
            if ((substr($v,0,1) === '"' && substr($v,-1) === '"') || (substr($v,0,1) === "'" && substr($v,-1) === "'")) {
                $v = substr($v,1,-1);
            }
            if (strpos($k, 'FM_') === 0) {
                putenv(sprintf('%s=%s', $k, $v));
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/bootstrap.php';
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
    echo get_class($e) . " - " . $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
}
