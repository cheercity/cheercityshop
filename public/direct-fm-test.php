<?php

// Symfony Autoloader laden
require_once __DIR__.'/../vendor/autoload.php';

// .env manuell laden
$envFile = __DIR__.'/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (0 === strpos(trim($line), '#')) {
            continue;
        }
        if (false !== strpos($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// .env.local laden falls vorhanden
$envLocalFile = __DIR__.'/.env.local';
if (file_exists($envLocalFile)) {
    $lines = file($envLocalFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (0 === strpos(trim($line), '#')) {
            continue;
        }
        if (false !== strpos($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

echo "=== FileMaker Client Direct Test ===\n";

try {
    // FileMakerClient direkt instanziieren
    $client = new App\Service\FileMakerClient(
        $_ENV['FM_HOST'] ?? 'unknown',
        $_ENV['FM_DB'] ?? 'unknown',
        $_ENV['FM_USER'] ?? 'unknown',
        $_ENV['FM_PASS'] ?? 'unknown'
    );

    echo "✅ FileMakerClient created successfully\n";
    echo 'Host: '.($_ENV['FM_HOST'] ?? 'unknown')."\n";
    echo 'DB: '.($_ENV['FM_DB'] ?? 'unknown')."\n";
    echo 'User: '.($_ENV['FM_USER'] ?? 'unknown')."\n";

    // Test Authentication
    echo "\n=== Testing Authentication ===\n";
    $reflection = new ReflectionClass($client);
    $ensureTokenMethod = $reflection->getMethod('ensureToken');
    $ensureTokenMethod->setAccessible(true);

    $ensureTokenMethod->invoke($client);
    echo "✅ Authentication successful\n";

    $tokenProperty = $reflection->getProperty('token');
    $tokenProperty->setAccessible(true);
    $token = $tokenProperty->getValue($client);
    echo 'Token length: '.strlen($token ?? '')."\n";

    // Test list method
    echo "\n=== Testing list method ===\n";
    $result = $client->list('sym_Banner', 5);
    echo "✅ List method successful\n";
    echo 'Records found: '.count($result['response']['data'] ?? [])."\n";

    if (!empty($result['response']['data'])) {
        $firstRecord = $result['response']['data'][0];
        echo 'First record fields: '.implode(', ', array_keys($firstRecord['fieldData'] ?? []))."\n";
    }
} catch (Throwable $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    echo 'File: '.basename($e->getFile()).' Line: '.$e->getLine()."\n";
    echo "Trace:\n";
    foreach (array_slice($e->getTrace(), 0, 5) as $i => $trace) {
        echo "  #{$i} ".($trace['file'] ?? 'unknown').':'.($trace['line'] ?? '?').
             ' '.($trace['class'] ?? '').($trace['type'] ?? '').($trace['function'] ?? '')."\n";
    }
}
