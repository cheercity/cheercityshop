<?php

// Direkter Test für FileMaker Layouts
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
    'tests' => [],
];

// Test-Layouts (existierende aus der Liste)
$layouts = ['sym_Banner', 'Banner', 'eShop_Banner', 'sym_Module', 'eShop_Module', 'eShop_Module_Admin'];

// Erst authentifizieren
$host = $_ENV['FM_HOST'];
$db = $_ENV['FM_DB'];
$user = $_ENV['FM_USER'];
$pass = $_ENV['FM_PASS'];

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

$result['token'] = substr($token, 0, 20).'...';

// Jetzt die Layouts testen
foreach ($layouts as $layout) {
    $find_url = $host.'/fmi/data/vLatest/databases/'.$db.'/layouts/'.$layout.'/records/_find';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $find_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode((object) []),
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

    $testResult = [
        'layout' => $layout,
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
    ];

    if ($response) {
        $data = json_decode($response, true);
        $testResult['fm_code'] = $data['messages'][0]['code'] ?? 'unknown';
        $testResult['fm_message'] = $data['messages'][0]['message'] ?? 'unknown';

        if ('0' === $testResult['fm_code']) {
            $testResult['status'] = 'success';
            $testResult['record_count'] = count($data['response']['data'] ?? []);

            // Sample-Felder zeigen
            if (!empty($data['response']['data'])) {
                $sampleRecord = $data['response']['data'][0];
                $testResult['available_fields'] = array_keys($sampleRecord['fieldData'] ?? []);
                $testResult['sample_data'] = array_slice($sampleRecord['fieldData'] ?? [], 0, 3, true);
            }
        } else {
            $testResult['status'] = 'layout_error';
        }

        // Für Debug: Zeige auch die rohe Response bei Fehlern
        if ($httpCode >= 400) {
            $testResult['response_preview'] = substr($response, 0, 200);
        }
    } else {
        $testResult['status'] = 'no_response';
    }

    $result['tests'][] = $testResult;
}

echo json_encode($result, JSON_PRETTY_PRINT);
