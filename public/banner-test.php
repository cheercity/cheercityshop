<?php

// Direkter Banner-Test
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

// Manueller FileMaker Client Test für Banner
$host = $_ENV['FM_HOST'];
$db = $_ENV['FM_DB'];
$user = $_ENV['FM_USER'];
$pass = $_ENV['FM_PASS'];

// Authentifizieren
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
curl_close($ch);

$authData = json_decode($response, true);
$token = $authData['response']['token'] ?? null;

if (!$token) {
    echo json_encode(['error' => 'No token'], JSON_PRETTY_PRINT);
    exit;
}

// Banner abrufen
$records_url = $host.'/fmi/data/vLatest/databases/'.$db.'/layouts/sym_Banner/records?_limit=10';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $records_url,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer '.$token,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'fm_code' => $data['messages'][0]['code'] ?? 'unknown',
    'fm_message' => $data['messages'][0]['message'] ?? 'unknown',
    'record_count' => count($data['response']['data'] ?? []),
    'banners' => [],
];

if ($data['response']['data'] ?? []) {
    foreach ($data['response']['data'] as $record) {
        $banner = $record['fieldData'] ?? [];
        $result['banners'][] = [
            'recordId' => $record['recordId'] ?? 'N/A',
            'description' => $banner['description'] ?? 'N/A',
            'position' => $banner['position'] ?? 'N/A',
            'active' => $banner['Aktiv'] ?? 'N/A',
            'fields' => array_keys($banner),
        ];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
