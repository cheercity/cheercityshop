<?php

// Direkter Test ohne Symfony-Controller
header('Content-Type: application/json');

// .env-Datei manuell laden
$envFile = __DIR__.'/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (0 === strpos($line, '#') || false === strpos($line, '=')) {
            continue; // Kommentare und ungültige Zeilen überspringen
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"'); // Anführungszeichen entfernen

        if (!empty($key) && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'working',
    'message' => 'Direct PHP test endpoint with .env loading',
    'environment_check' => [],
];

// Environment-Variablen prüfen
$envVars = ['FM_HOST', 'FM_DB', 'FM_USER', 'FM_PASS'];
foreach ($envVars as $var) {
    $value = $_ENV[$var] ?? null;
    $result['environment_check'][$var] = [
        'exists' => !empty($value),
        'value_preview' => $value ? (strlen($value) > 20 ? substr($value, 0, 20).'...' : $value) : 'NOT SET',
    ];
}

// Teste einfache Auth direkt
if (!empty($_ENV['FM_HOST']) && !empty($_ENV['FM_DB']) && !empty($_ENV['FM_USER']) && !empty($_ENV['FM_PASS'])) {
    $host = $_ENV['FM_HOST'];
    $db = $_ENV['FM_DB'];
    $user = $_ENV['FM_USER'];
    $pass = $_ENV['FM_PASS'];

    $auth_url = $host.'/fmi/data/vLatest/databases/'.$db.'/sessions';

    // Debug: Zeige die Credentials (verschleiert)
    $result['debug_info'] = [
        'user' => $user,
        'pass_length' => strlen($pass),
        'pass_first_3' => substr($pass, 0, 3),
        'pass_last_3' => substr($pass, -3),
        'auth_string_length' => strlen(base64_encode($user.':'.$pass)),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $auth_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode((object) []),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode($user.':'.$pass),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_VERBOSE => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result['auth_test'] = [
        'url' => $auth_url,
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
        'response_data' => json_decode($response, true),
    ];

    if (!$curlError && 200 == $httpCode) {
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['response']['token'])) {
            $result['auth_test']['status'] = 'success';
            $result['auth_test']['token_preview'] = substr($responseData['response']['token'], 0, 20).'...';
        }
    }
} else {
    $result['auth_test'] = [
        'status' => 'skipped',
        'message' => 'Environment variables not complete',
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
