<?php

// FileMaker Layout Test - sym_Module

use App\Service\FileMakerClient;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__.'/../vendor/autoload.php';

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

echo '<h1>FileMaker Layout Test: sym_Module</h1>';
echo '<pre>';

try {
    $fmClient = new FileMakerClient(
        $_ENV['FM_HOST'] ?? 'localhost',
        $_ENV['FM_DB'] ?? 'test',
        $_ENV['FM_USER'] ?? 'admin',
        $_ENV['FM_PASS'] ?? 'password'
    );

    echo "FileMaker Config:\n";
    echo 'Host: '.($_ENV['FM_HOST'] ?? 'NOT SET')."\n";
    echo 'Database: '.($_ENV['FM_DB'] ?? 'NOT SET')."\n";
    echo 'User: '.($_ENV['FM_USER'] ?? 'NOT SET')."\n\n";

    // Test 1: Get all records from sym_Module (no filter)
    echo "=== Test 1: All records from sym_Module ===\n";
    $allRecords = $fmClient->find('sym_Module', [], ['limit' => 10]);
    echo 'Found '.count($allRecords)." total records\n";

    if (!empty($allRecords)) {
        echo "\nFirst record fields:\n";
        $firstRecord = $allRecords[0]['fieldData'] ?? [];
        foreach ($firstRecord as $field => $value) {
            echo "  $field: ".(is_string($value) ? substr($value, 0, 100) : json_encode($value))."\n";
        }
    }
    echo "\n";

    // Test 2: Get records with Published = '1'
    echo "=== Test 2: Records with Published = '1' ===\n";
    $publishedRecords = $fmClient->find('sym_Module', ['Published' => '1'], ['limit' => 10]);
    echo 'Found '.count($publishedRecords)." published records\n\n";

    // Test 3: Get records with Footer_Status = '1'
    echo "=== Test 3: Records with Footer_Status = '1' ===\n";
    $footerRecords = $fmClient->find('sym_Module', ['Footer_Status' => '1'], ['limit' => 10]);
    echo 'Found '.count($footerRecords)." footer status records\n\n";

    // Test 4: Get records with both conditions (like in NavService)
    echo "=== Test 4: Records with Published = '1' AND Footer_Status = '1' ===\n";
    $bothRecords = $fmClient->find('sym_Module', ['Published' => '1', 'Footer_Status' => '1'], ['limit' => 10]);
    echo 'Found '.count($bothRecords)." records with both conditions\n";

    if (!empty($bothRecords)) {
        echo "\nFooter records:\n";
        foreach ($bothRecords as $record) {
            $data = $record['fieldData'] ?? [];
            echo '  Module: '.($data['Modul'] ?? 'NO MODULE')."\n";
            echo '  Title: '.($data['titel'] ?? 'NO TITLE')."\n";
            echo '  Link: '.($data['lnk'] ?? 'NO LINK')."\n";
            echo '  Sortorder: '.($data['Sortorder'] ?? 'NO SORT')."\n";
            echo '  Published: '.($data['Published'] ?? 'NO PUBLISHED')."\n";
            echo '  Footer_Status: '.($data['Footer_Status'] ?? 'NO FOOTER_STATUS')."\n";
            echo "  ---\n";
        }
    }
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo 'Exception: '.get_class($e)."\n";
    echo 'Message: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";

    if (false !== strpos($e->getMessage(), 'Layout is missing')) {
        echo "\n** LAYOUT 'sym_Module' DOES NOT EXIST **\n";
        echo "This is likely the main problem!\n";
    }

    if (false !== strpos($e->getMessage(), 'Field is missing')) {
        echo "\n** ONE OF THE FIELDS IS MISSING **\n";
        echo "Check if these fields exist in the layout:\n";
        echo "- Modul\n";
        echo "- titel\n";
        echo "- lnk\n";
        echo "- Sortorder\n";
        echo "- Published\n";
        echo "- Footer_Status\n";
    }

    echo "\nStack trace:\n";
    echo $e->getTraceAsString()."\n";
}

echo '</pre>';
echo '<p><a href="footer-cache-clear.php">Clear Cache</a> | <a href="footer-debug.php">Debug Footer Modules</a></p>';
