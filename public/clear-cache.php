<?php

/**
 * Symfony Cache Clear Script.
 *
 * Dieses Script kann über FTP auf den Server hochgeladen werden
 * und über den Browser aufgerufen werden um den Cache zu leeren.
 *
 * Verwendung:
 * 1. Datei auf den Server hochladen (z.B. nach public/clear-cache.php)
 * 2. Im Browser aufrufen: https://sym.cheercity-shop.de/clear-cache.php
 * 3. Nach dem Cache-Leeren die Datei wieder vom Server löschen (Sicherheit!)
 */

// Sicherheitsprüfung: Nur von bestimmten IPs oder mit Passwort erlauben
$allowed_ips = ['127.0.0.1', '::1']; // Fügen Sie Ihre IP hinzu
$secret_key = 'ENaq5IjMd8JF68geNNZ3Y8gi'; // will be replaced during upload with a generated secret

$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$provided_key = $_GET['key'] ?? '';

// Einfache Authentifizierung
if (!in_array($client_ip, $allowed_ips) && $provided_key !== $secret_key) {
    http_response_code(403);
    exit('Access denied. Use: clear-cache.php?key='.$secret_key);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Symfony Cache Clear</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>
<div class='container'>
<h1>🧹 Symfony Cache Clear</h1>
<p><strong>Server:</strong> ".($_SERVER['HTTP_HOST'] ?? 'localhost').'<br>
<strong>Zeit:</strong> '.date('d.m.Y H:i:s')."<br>
<strong>Client-IP:</strong> {$client_ip}</p>";

// Symfony-Projektpfad ermitteln
$project_root = dirname(__DIR__);
$cache_dirs = [
    $project_root.'/var/cache',
    $project_root.'/var/cache/prod',
    $project_root.'/var/cache/dev',
];

echo '<h2>🔍 Cache-Verzeichnisse prüfen</h2>';

$results = [];
$total_deleted = 0;

foreach ($cache_dirs as $cache_dir) {
    echo "<div class='info'><strong>Prüfe:</strong> <code>{$cache_dir}</code></div>";

    if (!is_dir($cache_dir)) {
        echo "<div class='warning'>Verzeichnis existiert nicht: {$cache_dir}</div>";
        continue;
    }

    if (!is_writable($cache_dir)) {
        echo "<div class='error'>Verzeichnis ist nicht schreibbar: {$cache_dir}</div>";
        continue;
    }

    // Cache-Verzeichnis leeren
    $deleted = clearCacheDirectory($cache_dir);
    $total_deleted += $deleted;

    if ($deleted > 0) {
        echo "<div class='success'>✅ {$deleted} Dateien aus {$cache_dir} gelöscht</div>";
    } else {
        echo "<div class='info'>ℹ️ Keine Dateien zum Löschen in {$cache_dir}</div>";
    }

    // Zusätzlich: Komplettes Verzeichnis entfernen und neu erstellen bei Container-Problemen
    if (false !== strpos($cache_dir, '/cache') && is_dir($cache_dir)) {
        $parent_dir = dirname($cache_dir);
        $dir_name = basename($cache_dir);

        // Verzeichnis komplett entfernen
        if (rmdir_recursive($cache_dir)) {
            echo "<div class='success'>🗑️ Verzeichnis {$dir_name} komplett entfernt</div>";

            // Neu erstellen
            if (mkdir($cache_dir, 0755, true)) {
                echo "<div class='success'>📁 Verzeichnis {$dir_name} neu erstellt</div>";
            }
        }
    }
}

echo '<h2>📊 Zusammenfassung</h2>';
if ($total_deleted > 0) {
    echo "<div class='success'>🎉 <strong>Erfolgreich!</strong> Insgesamt {$total_deleted} Cache-Dateien gelöscht.</div>";
} else {
    echo "<div class='info'>ℹ️ Keine Cache-Dateien gefunden oder bereits geleert.</div>";
}

echo "<h2>🔧 Aktionen</h2>
<a href='?' class='btn'>🔄 Seite neu laden</a>
<a href='/' class='btn btn-success'>🏠 Zur Startseite</a>
<a href='/debug' class='btn'>🐛 Debug Center</a>";

echo "<div class='warning'>
<strong>⚠️ Sicherheitshinweis:</strong><br>
Löschen Sie diese Datei nach der Verwendung vom Server, da sie ein Sicherheitsrisiko darstellt!
</div>";

echo '</div></body></html>';

/**
 * Leert rekursiv ein Verzeichnis.
 */
function clearCacheDirectory($dir)
{
    $deleted = 0;

    if (!is_dir($dir)) {
        return 0;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        try {
            if ($file->isFile()) {
                if (unlink($file->getRealPath())) {
                    ++$deleted;
                }
            } elseif ($file->isDir()) {
                rmdir($file->getRealPath());
            }
        } catch (Exception $e) {
            // Fehler ignorieren und weitermachen
        }
    }

    return $deleted;
}

/**
 * Entfernt ein Verzeichnis rekursiv komplett.
 */
function rmdir_recursive($dir)
{
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir.'/'.$file;
        if (is_dir($path)) {
            rmdir_recursive($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}
