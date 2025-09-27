<?php

/**
 * FileMaker Service Cleanup Script.
 *
 * Entfernt die problematische FileMakerClientNative.php Datei
 * die einen Klassenkonflikt verursacht.
 */
$secret_key = 'filemaker-cleanup-2024';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $secret_key) {
    http_response_code(403);
    exit('Access denied. Use: filemaker-cleanup.php?key='.$secret_key);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>FileMaker Service Cleanup</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>
<h1>🧹 FileMaker Service Cleanup</h1>
<p><strong>Server:</strong> ".($_SERVER['HTTP_HOST'] ?? 'localhost').'<br>
<strong>Zeit:</strong> '.date('d.m.Y H:i:s').'</p>';

// Basis-Pfade
$project_root = dirname(__DIR__);
$service_dir = $project_root.'/src/Service';

echo '<h2>🔍 FileMaker Service-Dateien prüfen</h2>';

// Problematische Dateien
$problematic_files = [
    'FileMakerClientNative.php',
    'FileMakerClient.backup.php',
    'FileMakerClient.bak',
    'FileMakerClient.old',
];

$results = [];
$removed_count = 0;

foreach ($problematic_files as $filename) {
    $file_path = $service_dir.'/'.$filename;

    echo "<div class='info'>Prüfe: <code>{$filename}</code></div>";

    if (!file_exists($file_path)) {
        echo "<div class='success'>✅ Datei existiert nicht: {$filename} - OK</div>";
        continue;
    }

    // Datei-Info anzeigen
    $size = filesize($file_path);
    $modified = date('d.m.Y H:i:s', filemtime($file_path));
    echo "<div class='warning'>⚠️ Gefunden: {$filename} ({$size} Bytes, geändert: {$modified})</div>";

    // Datei-Inhalt kurz prüfen
    $content = file_get_contents($file_path);
    $has_class_conflict = false !== strpos($content, 'class FileMakerClient');

    if ($has_class_conflict) {
        echo "<div class='error'>❌ Klassenkonflikt gefunden in {$filename}</div>";

        // Datei löschen
        if (unlink($file_path)) {
            echo "<div class='success'>🗑️ Erfolgreich gelöscht: {$filename}</div>";
            ++$removed_count;
        } else {
            echo "<div class='error'>❌ Fehler beim Löschen: {$filename}</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ Kein direkter Klassenkonflikt in {$filename}</div>";
    }
}

// Prüfe aktuelle FileMakerClient.php
echo '<h2>🔍 Aktuelle FileMaker Client prüfen</h2>';
$current_fm_file = $service_dir.'/FileMakerClient.php';

if (file_exists($current_fm_file)) {
    $content = file_get_contents($current_fm_file);
    $size = filesize($current_fm_file);

    echo "<div class='success'>✅ FileMakerClient.php existiert ({$size} Bytes)</div>";

    // Prüfe auf cURL usage (neue Version)
    if (false !== strpos($content, 'curl_init()')) {
        echo "<div class='success'>✅ Neue cURL-basierte Version erkannt</div>";
    } elseif (false !== strpos($content, 'HttpClientInterface')) {
        echo "<div class='warning'>⚠️ Alte HTTP Client Version erkannt - sollte aktualisiert werden</div>";
    }

    // Prüfe Syntax
    $syntax_check = shell_exec("php -l {$current_fm_file} 2>&1");
    if (false !== strpos($syntax_check, 'No syntax errors')) {
        echo "<div class='success'>✅ Syntax-Check bestanden</div>";
    } else {
        echo "<div class='error'>❌ Syntax-Fehler: {$syntax_check}</div>";
    }
} else {
    echo "<div class='error'>❌ FileMakerClient.php nicht gefunden!</div>";
}

// Autoloader-Cache prüfen
echo '<h2>🔄 Autoloader-Status</h2>';
$vendor_autoload = $project_root.'/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    echo "<div class='success'>✅ Composer Autoloader gefunden</div>";
    $autoload_modified = date('d.m.Y H:i:s', filemtime($vendor_autoload));
    echo "<div class='info'>ℹ️ Letzte Aktualisierung: {$autoload_modified}</div>";
} else {
    echo "<div class='error'>❌ Composer Autoloader nicht gefunden</div>";
}

echo '<h2>📊 Zusammenfassung</h2>';

if ($removed_count > 0) {
    echo "<div class='success'><strong>✅ {$removed_count} problematische Datei(en) entfernt</strong></div>";
} else {
    echo "<div class='info'><strong>ℹ️ Keine problematischen Dateien gefunden</strong></div>";
}

echo '<h2>🔧 Empfohlene nächste Schritte</h2>';
echo "<div class='warning'>
<strong>1. Cache leeren:</strong><br>
Nach dem Cleanup sollten Sie den Cache leeren: <code>/debug/cache-clear</code> oder <code>/clear-cache.php</code><br><br>

<strong>2. Composer Autoloader aktualisieren (falls möglich):</strong><br>
<code>composer dump-autoload --optimize</code><br><br>

<strong>3. Website testen:</strong><br>
- Startseite: <code>/</code><br>
- Debug Center: <code>/debug</code><br>
- FileMaker Module: <code>/debug/module</code>
</div>";

echo "<h2>🔧 Aktionen</h2>
<a href='/clear-cache.php?key=your-secret-key-2024' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🧹 Cache leeren</a>
<a href='/debug/cache-clear' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🗑️ Symfony Cache</a>
<a href='/debug' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🐛 Debug Center</a>
<a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🏠 Startseite testen</a>";

echo "<div class='error'>
<strong>⚠️ Sicherheitshinweis:</strong><br>
Löschen Sie diese Datei <code>filemaker-cleanup.php</code> nach der Verwendung vom Server!
</div>";

echo '</div></body></html>';
