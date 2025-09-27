<?php

/**
 * Controller Cleanup Script.
 *
 * Entfernt alte Controller-Dateien die bei der Umstrukturierung
 * zum Debug-Namespace zurückgeblieben sind.
 *
 * WICHTIG: Nach dem Ausführen diese Datei vom Server löschen!
 */

// Sicherheitsschlüssel - ändern Sie diesen!
$secret_key = 'cleanup-controllers-2024';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $secret_key) {
    http_response_code(403);
    exit('Access denied. Use: cleanup-controllers.php?key='.$secret_key);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Controller Cleanup</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        .file-list { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
<h1>🧹 Controller Cleanup</h1>
<p><strong>Server:</strong> ".($_SERVER['HTTP_HOST'] ?? 'localhost').'<br>
<strong>Zeit:</strong> '.date('d.m.Y H:i:s').'</p>';

// Symfony-Projektpfad ermitteln
$project_root = dirname(__DIR__);
$controller_dir = $project_root.'/src/Controller';

echo '<h2>🔍 Alte Controller-Dateien suchen</h2>';
echo "<div class='info'>Suche in: <code>{$controller_dir}</code></div>";

// Liste der zu entfernenden Controller-Dateien
$files_to_remove = [
    'BannerDebugController.php',
    'ModuleDebugController.php',
    'NavigationDebugController.php',
    'ContentDebugController.php',
    'ProductDebugController.php',
    'UserDebugController.php',
];

// Liste der zu entfernenden Service-Dateien
$service_files_to_remove = [
    'FileMakerClientNative.php',
];

echo '<h3>🧹 Controller-Dateien bereinigen</h3>';

$removed_files = [];
$not_found_files = [];
$error_files = [];

foreach ($files_to_remove as $filename) {
    $file_path = $controller_dir.'/'.$filename;

    echo "<div class='info'>Prüfe: <code>{$filename}</code></div>";

    if (!file_exists($file_path)) {
        echo "<div class='success'>✅ Datei existiert bereits nicht: {$filename}</div>";
        $not_found_files[] = $filename;
        continue;
    }

    // Datei-Inhalt prüfen um sicherzustellen, dass es sich um den richtigen Controller handelt
    $content = file_get_contents($file_path);
    if (false !== strpos($content, 'namespace App\\Controller;')) {
        // Das ist ein alter Controller im Root-Namespace, kann entfernt werden
        if (unlink($file_path)) {
            echo "<div class='success'>🗑️ Erfolgreich gelöscht: {$filename}</div>";
            $removed_files[] = $filename;
        } else {
            echo "<div class='error'>❌ Fehler beim Löschen: {$filename}</div>";
            $error_files[] = $filename;
        }
    } else {
        echo "<div class='warning'>⚠️ Datei übersprungen (unerwarteter Inhalt): {$filename}</div>";
        $error_files[] = $filename;
    }
}

echo '<h3>🧹 Service-Dateien bereinigen</h3>';

$service_dir = $project_root.'/src/Service';
foreach ($service_files_to_remove as $filename) {
    $file_path = $service_dir.'/'.$filename;

    echo "<div class='info'>Prüfe Service: <code>{$filename}</code></div>";

    if (!file_exists($file_path)) {
        echo "<div class='success'>✅ Datei existiert bereits nicht: {$filename}</div>";
        $not_found_files[] = $filename;
        continue;
    }

    // Prüfen ob es die richtige Datei ist
    $content = file_get_contents($file_path);
    if (false !== strpos($content, 'FileMakerClientNative') || false !== strpos($content, 'class FileMakerClient')) {
        if (unlink($file_path)) {
            echo "<div class='success'>🗑️ Service-Datei erfolgreich gelöscht: {$filename}</div>";
            $removed_files[] = $filename;
        } else {
            echo "<div class='error'>❌ Fehler beim Löschen der Service-Datei: {$filename}</div>";
            $error_files[] = $filename;
        }
    } else {
        echo "<div class='warning'>⚠️ Service-Datei übersprungen (unerwarteter Inhalt): {$filename}</div>";
    }
}

// Prüfe auch auf mögliche Backup-Dateien
$backup_patterns = ['*.bak', '*.backup', '*.old', '*~'];
foreach ($backup_patterns as $pattern) {
    $backup_files = glob($controller_dir.'/'.$pattern);
    foreach ($backup_files as $backup_file) {
        $filename = basename($backup_file);
        if (in_array(str_replace(['.bak', '.backup', '.old', '~'], '', $filename), $files_to_remove)) {
            if (unlink($backup_file)) {
                echo "<div class='success'>🗑️ Backup-Datei gelöscht: {$filename}</div>";
                $removed_files[] = $filename;
            }
        }
    }
}

echo '<h2>📊 Zusammenfassung</h2>';

if (!empty($removed_files)) {
    echo "<div class='success'><strong>✅ Erfolgreich entfernt ({count($removed_files)} Dateien):</strong><br>";
    foreach ($removed_files as $file) {
        echo "- {$file}<br>";
    }
    echo '</div>';
}

if (!empty($not_found_files)) {
    echo "<div class='info'><strong>ℹ️ Bereits nicht vorhanden ({count($not_found_files)} Dateien):</strong><br>";
    foreach ($not_found_files as $file) {
        echo "- {$file}<br>";
    }
    echo '</div>';
}

if (!empty($error_files)) {
    echo "<div class='error'><strong>❌ Probleme ({count($error_files)} Dateien):</strong><br>";
    foreach ($error_files as $file) {
        echo "- {$file}<br>";
    }
    echo '</div>';
}

// Prüfe Debug-Verzeichnis
echo '<h2>🔍 Debug-Controller prüfen</h2>';
$debug_dir = $controller_dir.'/Debug';
if (is_dir($debug_dir)) {
    $debug_files = scandir($debug_dir);
    $debug_controllers = array_filter($debug_files, function ($file) {
        return '.php' === substr($file, -4);
    });

    echo "<div class='success'><strong>✅ Debug-Controller vorhanden ({count($debug_controllers)} Dateien):</strong><br>";
    foreach ($debug_controllers as $file) {
        echo "- {$file}<br>";
    }
    echo '</div>';
} else {
    echo "<div class='error'>❌ Debug-Verzeichnis nicht gefunden: {$debug_dir}</div>";
}

// Cache-Clear empfehlen
echo '<h2>🧹 Nächste Schritte</h2>';
echo "<div class='warning'>
<strong>📝 Empfohlene nächste Schritte:</strong><br>
1. Cache leeren über: <code>/debug/cache-clear</code><br>
2. Diese Cleanup-Datei vom Server entfernen<br>
3. Website testen: <code>/</code> und <code>/debug</code>
</div>";

echo "<h2>🔧 Aktionen</h2>
<a href='/debug/cache-clear' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🧹 Cache leeren</a>
<a href='/debug' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🐛 Debug Center</a>
<a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🏠 Startseite</a>";

echo "<div class='error'>
<strong>⚠️ Sicherheitshinweis:</strong><br>
Löschen Sie diese Datei <code>cleanup-controllers.php</code> nach der Verwendung vom Server!
</div>";

echo '</div></body></html>';
