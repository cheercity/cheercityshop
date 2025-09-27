<?php

// Liste alle verfügbaren Layouts auf
header('Content-Type: application/json');

// .env-Datei manuell laden
$envFile = __DIR__.'/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (0 === strpos($line, '#') || false === strpos($line, '=')) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"');

        if (!empty($key) && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
];

$host = $_ENV['FM_HOST'];
$db = $_ENV['FM_DB'];
$user = $_ENV['FM_USER'];
$pass = $_ENV['FM_PASS'];

// Erst authentifizieren
$auth_url = $host.'/fmi/data/vLatest/databases/'.$db.'/sessions';

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
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (200 != $httpCode) {
    $result['error'] = 'Authentication failed';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$authData = json_decode($response, true);
$token = $authData['response']['token'] ?? null;

if (!$token) {
    $result['error'] = 'No token received';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// Layouts abrufen
$layouts_url = $host.'/fmi/data/vLatest/databases/'.$db.'/layouts';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $layouts_url,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer '.$token,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$result['layouts_request'] = [
    'url' => $layouts_url,
    'http_code' => $httpCode,
    'curl_error' => $curlError ?: null,
];

if ($response && 200 == $httpCode) {
    $data = json_decode($response, true);
    $result['available_layouts'] = $data['response']['layouts'] ?? [];
} else {
    $result['error'] = 'Could not retrieve layouts';
    $result['response'] = $response ? substr($response, 0, 500) : 'No response';
}

echo json_encode($result, JSON_PRETTY_PRINT);
