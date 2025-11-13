<?php
require_once 'vendor/autoload.php';

// Load environment variables
$botToken = getenv('BOT_TOKEN') ?: '8507471476:AAHkLlfP4uZ8DwNsoffhDPQsfh61QoX9aZc';

header('Content-Type: text/plain');
echo "Telegram YouTube Downloader Bot\n";
echo "Status: Online\n";
echo "Server Time: " . date('Y-m-d H:i:s') . "\n";
echo "Bot Token: " . substr($botToken, 0, 10) . "..." . "\n";

// Check data directory
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if (is_writable($dataDir)) {
    echo "Data directory: Writable\n";
} else {
    echo "Data directory: Not writable\n";
}

// Check users.json
$usersFile = $dataDir . '/users.json';
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true) ?: [];
    echo "Total users: " . count($users) . "\n";
} else {
    echo "Total users: 0\n";
}

// Start bot if accessed via CLI
if (php_sapi_name() === 'cli') {
    require_once 'bot.php';
}
?>