<?php

/**
 * System Status Check.
 *
 * Überprüft den aktuellen Zustand der Symfony-Installation
 * und zeigt mögliche Probleme an.
 */
$secret_key = 'status-check-2024';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $secret_key) {
    http_response_code(403);
    exit('Access denied. Use: status-check.php?key='.$secret_key);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Status Check</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
        th { background: #f8f9fa; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔍 System Status Check</h1>
<p><strong>Server:</strong> ".($_SERVER['HTTP_HOST'] ?? 'localhost').'<br>
<strong>Zeit:</strong> '.date('d.m.Y H:i:s').'</p>';

// Basis-Pfade
$project_root = dirname(__DIR__);
$src_dir = $project_root.'/src';
$controller_dir = $src_dir.'/Controller';
$debug_controller_dir = $controller_dir.'/Debug';

echo '<h2>📁 Verzeichnisstruktur</h2>';
echo '<table>';
echo '<tr><th>Verzeichnis</th><th>Status</th><th>Berechtigungen</th></tr>';

$directories = [
    'Projekt-Root' => $project_root,
    'src/' => $src_dir,
    'src/Controller/' => $controller_dir,
    'src/Controller/Debug/' => $debug_controller_dir,
    'var/cache/' => $project_root.'/var/cache',
    'var/log/' => $project_root.'/var/log',
    'public/' => $project_root.'/public',
];

foreach ($directories as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists ? is_writable($path) : false;

    echo '<tr>';
    echo "<td><code>{$name}</code><br><small>{$path}</small></td>";
    echo '<td>'.($exists ? "<span class='status-ok'>✅ Existiert</span>" : "<span class='status-error'>❌ Fehlt</span>").'</td>';
    echo '<td>'.($writable ? "<span class='status-ok'>✅ Schreibbar</span>" : "<span class='status-warning'>⚠️ Nur lesbar</span>").'</td>';
    echo '</tr>';
}
echo '</table>';

echo '<h2>🎛️ Controller-Status</h2>';

// Alte Controller prüfen (sollten NICHT existieren)
$old_controllers = [
    'BannerDebugController.php',
    'ModuleDebugController.php',
    'NavigationDebugController.php',
    'ContentDebugController.php',
    'ProductDebugController.php',
    'UserDebugController.php',
];

echo '<h3>Alte Controller (Root-Namespace) - sollten NICHT existieren:</h3>';
echo '<table>';
echo '<tr><th>Controller</th><th>Status</th><th>Aktion</th></tr>';

$old_controllers_found = 0;
foreach ($old_controllers as $controller) {
    $path = $controller_dir.'/'.$controller;
    $exists = file_exists($path);
    if ($exists) {
        ++$old_controllers_found;
    }

    echo '<tr>';
    echo "<td><code>{$controller}</code></td>";
    echo '<td>'.($exists ? "<span class='status-error'>❌ Existiert noch</span>" : "<span class='status-ok'>✅ Nicht vorhanden</span>").'</td>';
    echo '<td>'.($exists ? 'Muss gelöscht werden!' : 'OK').'</td>';
    echo '</tr>';
}
echo '</table>';

// Debug-Controller prüfen (sollten existieren)
echo '<h3>Debug-Controller (Debug-Namespace) - sollten existieren:</h3>';
echo '<table>';
echo '<tr><th>Controller</th><th>Status</th><th>Namespace</th></tr>';

$debug_controllers_expected = [
    'DebugController.php',
    'ModuleDebugController.php',
    'BannerDebugController.php',
    'NavigationDebugController.php',
    'ContentDebugController.php',
    'ProductDebugController.php',
    'UserDebugController.php',
    'CacheController.php',
];

$debug_controllers_found = 0;
foreach ($debug_controllers_expected as $controller) {
    $path = $debug_controller_dir.'/'.$controller;
    $exists = file_exists($path);
    if ($exists) {
        ++$debug_controllers_found;
    }

    $namespace_ok = false;
    if ($exists) {
        $content = file_get_contents($path);
        $namespace_ok = false !== strpos($content, 'namespace App\\Controller\\Debug;');
    }

    echo '<tr>';
    echo "<td><code>{$controller}</code></td>";
    echo '<td>'.($exists ? "<span class='status-ok'>✅ Existiert</span>" : "<span class='status-error'>❌ Fehlt</span>").'</td>';
    echo '<td>'.($namespace_ok ? "<span class='status-ok'>✅ Korrekt</span>" : "<span class='status-error'>❌ Falsch</span>").'</td>';
    echo '</tr>';
}
echo '</table>';

echo '<h2>⚙️ Symfony-Konfiguration</h2>';

// .env Datei prüfen
$env_file = $project_root.'/.env';
$env_local_file = $project_root.'/.env.local';

echo '<table>';
echo '<tr><th>Konfigurationsdatei</th><th>Status</th><th>Größe</th></tr>';
echo '<tr>';
echo '<td><code>.env</code></td>';
echo '<td>'.(file_exists($env_file) ? "<span class='status-ok'>✅ Existiert</span>" : "<span class='status-error'>❌ Fehlt</span>").'</td>';
echo '<td>'.(file_exists($env_file) ? filesize($env_file).' Bytes' : '-').'</td>';
echo '</tr>';
echo '<tr>';
echo '<td><code>.env.local</code></td>';
echo '<td>'.(file_exists($env_local_file) ? "<span class='status-ok'>✅ Existiert</span>" : "<span class='status-warning'>⚠️ Optional</span>").'</td>';
echo '<td>'.(file_exists($env_local_file) ? filesize($env_local_file).' Bytes' : '-').'</td>';
echo '</tr>';
echo '</table>';

echo '<h2>📊 Gesamtstatus</h2>';

$total_issues = $old_controllers_found;
$missing_debug = count($debug_controllers_expected) - $debug_controllers_found;
$total_issues += $missing_debug;

if (0 == $total_issues) {
    echo "<div class='success'><strong>🎉 Alles OK!</strong><br>
    Das System scheint korrekt konfiguriert zu sein. Keine Probleme gefunden.</div>";
} else {
    echo "<div class='error'><strong>⚠️ Probleme gefunden ({$total_issues}):</strong><br>";
    if ($old_controllers_found > 0) {
        echo "- {$old_controllers_found} alte Controller müssen entfernt werden<br>";
    }
    if ($missing_debug > 0) {
        echo "- {$missing_debug} Debug-Controller fehlen<br>";
    }
    echo '</div>';
}

echo '<h2>🔧 Empfohlene Aktionen</h2>';

if ($old_controllers_found > 0) {
    echo "<div class='warning'>
    <strong>1. Alte Controller entfernen:</strong><br>
    Verwenden Sie das Cleanup-Script: <code>cleanup-controllers.php</code>
    </div>";
}

echo "<div class='info'>
<strong>2. Cache leeren:</strong><br>
Nach Änderungen immer den Cache leeren: <code>/debug/cache-clear</code>
</div>";

echo "<div class='info'>
<strong>3. System testen:</strong><br>
- Startseite testen: <code>/</code><br>
- Debug-Center testen: <code>/debug</code>
</div>";

echo "<h2>🔧 Aktionen</h2>
<a href='cleanup-controllers.php?key=cleanup-controllers-2024' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🧹 Controller aufräumen</a>
<a href='/debug/cache-clear' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🗑️ Cache leeren</a>
<a href='/debug' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🐛 Debug Center</a>
<a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px;'>🏠 Startseite testen</a>";

echo "<div class='error'>
<strong>⚠️ Sicherheitshinweis:</strong><br>
Löschen Sie alle diese PHP-Dateien nach der Verwendung vom Live-Server!
</div>";

echo '</div></body></html>';
