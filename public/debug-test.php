<?php

// Einfacher FileMaker-Test ohne Symfony
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h1>FileMaker Debug Test</h1>';

// FileMaker-Credentials aus .env
$fm_host = 'https://fms.cheercity-shop.de';
$fm_db = 'CHEERCITYshop';
$fm_user = 'eshop';
$fm_pass = 'PjZtvq%XNQ@§4_$';

echo '<h2>1. Authentication Test</h2>';

// 1. Authentication
$auth_url = $fm_host.'/fmi/data/vLatest/databases/'.$fm_db.'/sessions';
$auth_data = json_encode((object) []); // Leeres Objekt statt Array!

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $auth_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $auth_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic '.base64_encode($fm_user.':'.$fm_pass),
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
if ($error) {
    echo "<p><strong>cURL Error:</strong> $error</p>";
}
echo '<p><strong>Response:</strong></p>';
echo '<pre>'.htmlspecialchars($response).'</pre>';

$auth_result = json_decode($response, true);
$token = $auth_result['response']['token'] ?? null;

if (!$token) {
    echo "<p style='color: red;'>❌ Kein Token erhalten - Test abgebrochen</p>";
    exit;
}

echo "<p style='color: green;'>✅ Token erhalten: ".substr($token, 0, 20).'...</p>';

echo '<h2>2. Layout Discovery Test</h2>';

$layouts = ['sym_Users', 'Users', 'Kunden', 'Customer'];

foreach ($layouts as $layout) {
    echo "<h3>Testing Layout: $layout</h3>";

    $find_url = $fm_host.'/fmi/data/vLatest/databases/'.$fm_db.'/layouts/'.$layout.'/records/_find';
    $find_data = json_encode(['limit' => 1]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $find_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $find_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer '.$token,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
    if ($error) {
        echo "<p><strong>cURL Error:</strong> $error</p>";
    }

    $result = json_decode($response, true);

    if ($result) {
        $code = $result['messages'][0]['code'] ?? 'unknown';
        $message = $result['messages'][0]['message'] ?? 'unknown';

        echo "<p><strong>FM Code:</strong> $code</p>";
        echo "<p><strong>FM Message:</strong> $message</p>";

        if ('0' === $code) {
            $recordCount = count($result['response']['data'] ?? []);
            echo "<p style='color: green;'>✅ Layout exists - $recordCount records found</p>";

            if ($recordCount > 0) {
                echo '<p><strong>Sample record structure:</strong></p>';
                echo '<pre>'.json_encode($result['response']['data'][0], JSON_PRETTY_PRINT).'</pre>';
            }
        } else {
            echo "<p style='color: orange;'>⚠️ Layout error: $message</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Invalid JSON response</p>";
        echo '<pre>'.htmlspecialchars($response).'</pre>';
    }

    echo '<hr>';
}

echo '<p><em>Test completed at '.date('Y-m-d H:i:s').'</em></p>';
