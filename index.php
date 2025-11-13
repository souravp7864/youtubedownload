<?php
header('Content-Type: text/plain');
echo "Telegram Bot is running!\n";
echo "Status: Online\n";
echo "Last updated: " . date('Y-m-d H:i:s') . "\n";

// Check if data directory is writable
$dataDir = '/var/www/html/data';
if (is_writable($dataDir)) {
    echo "Data directory: Writable\n";
} else {
    echo "Data directory: Not writable\n";
}

// Check if users.json exists
$usersFile = $dataDir . '/users.json';
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
    echo "Total users: " . count($users) . "\n";
} else {
    echo "Total users: 0\n";
}
?>