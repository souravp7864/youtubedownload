<?php
class TelegramYouTubeBot {
    private $token;
    private $apiUrl;
    private $dataDir;
    private $usersFile;
    private $downloadDir;
    
    public function __construct($token) {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
        $this->dataDir = __DIR__ . '/data';
        $this->usersFile = $this->dataDir . '/users.json';
        $this->downloadDir = $this->dataDir . '/downloads';
        
        // Create directories
        if (!is_dir($this->dataDir)) mkdir($this->dataDir, 0777, true);
        if (!is_dir($this->downloadDir)) mkdir($this->downloadDir, 0777, true);
        
        // Log file
        $this->logFile = $this->dataDir . '/bot.log';
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
    
    private function loadUsers() {
        if (file_exists($this->usersFile)) {
            $data = file_get_contents($this->usersFile);
            return json_decode($data, true) ?: [];
        }
        return [];
    }
    
    private function saveUser($userId, $userData) {
        $users = $this->loadUsers();
        $users[$userId] = array_merge($users[$userId] ?? [], $userData);
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
    }
    
    public function sendMessage($chatId, $text, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->apiRequest('sendMessage', $data);
    }
    
    public function sendDocument($chatId, $documentPath, $caption = '') {
        if (!file_exists($documentPath)) {
            throw new Exception("File not found: $documentPath");
        }
        
        $data = [
            'chat_id' => $chatId,
            'document' => new CURLFile($documentPath),
            'caption' => $caption
        ];
        
        return $this->apiRequest('sendDocument', $data);
    }
    
    public function answerCallbackQuery($callbackQueryId, $text = '') {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text
        ];
        
        return $this->apiRequest('answerCallbackQuery', $data);
    }
    
    public function editMessageText($chatId, $messageId, $text, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->apiRequest('editMessageText', $data);
    }
    
    private function apiRequest($method, $data) {
        $url = $this->apiUrl . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // For file uploads
        if (isset($data['document'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: multipart/form-data"
            ]);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $this->log("cURL Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function downloadYouTube($url, $format = 'video') {
        $videoId = $this->extractVideoId($url);
        if (!$videoId) {
            throw new Exception("Invalid YouTube URL");
        }
        
        $tempFile = $this->downloadDir . '/' . $videoId . '.' . ($format === 'audio' ? 'mp3' : 'mp4');
        
        // Use yt-dlp via shell command (included in Docker image)
        $cmd = escapeshellcmd("yt-dlp") . " -o " . escapeshellarg($tempFile);
        
        if ($format === 'audio') {
            $cmd .= " -x --audio-format mp3 --audio-quality 0";
        } else {
            $cmd .= " -f best[ext=mp4]";
        }
        
        $cmd .= " " . escapeshellarg($url);
        $cmd .= " 2>&1";
        
        $this->log("Executing: $cmd");
        
        $output = shell_exec($cmd);
        $this->log("Download output: $output");
        
        // Find the actual file that was created
        $files = glob($this->downloadDir . '/' . $videoId . '.*');
        if (empty($files)) {
            throw new Exception("Download failed - no file created");
        }
        
        return $files[0];
    }
    
    private function extractVideoId($url) {
        $patterns = [
            '/youtube\.com\/watch\?v=([^&]+)/',
            '/youtu\.be\/([^?]+)/',
            '/youtube\.com\/embed\/([^?]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    public function handleUpdate($update) {
        $this->log("Received update: " . json_encode($update));
        
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }
    
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Save user info
        $this->saveUser($userId, [
            'username' => $message['from']['username'] ?? '',
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'last_active' => date('Y-m-d H:i:s')
        ]);
        
        if ($text === '/start') {
            $this->sendMessage($chatId, 
                "🎉 <b>Welcome to YouTube Downloader Bot!</b>\n\n" .
                "Send me any YouTube link and I'll download it for you.\n\n" .
                "Supported formats:\n" .
                "🎥 <b>Video</b> - MP4 format\n" .
                "🎧 <b>Audio</b> - MP3 format\n\n" .
                "Just paste a YouTube URL and choose your preferred format!"
            );
        } elseif (strpos($text, 'youtube.com') !== false || strpos($text, 'youtu.be') !== false) {
            // Store URL in session (simplified)
            $_SESSION['last_url'] = $text;
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎥 Download Video (MP4)', 'callback_data' => 'video'],
                        ['text' => '🎧 Download Audio (MP3)', 'callback_data' => 'audio']
                    ]
                ]
            ];
            
            $this->sendMessage($chatId, "Select download format:", $keyboard);
        } else {
            $this->sendMessage($chatId, "Please send a valid YouTube URL or use /start");
        }
    }
    
    private function handleCallbackQuery($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $data = $callbackQuery['data'];
        $queryId = $callbackQuery['id'];
        
        $this->answerCallbackQuery($queryId);
        
        // Get stored URL
        $url = $_SESSION['last_url'] ?? '';
        if (!$url) {
            $this->editMessageText($chatId, $messageId, "❌ Error: No URL found. Please send the YouTube link again.");
            return;
        }
        
        $this->editMessageText($chatId, $messageId, "⏳ Downloading... Please wait...");
        
        try {
            $filePath = $this->downloadYouTube($url, $data);
            $fileSize = filesize($filePath);
            
            if ($fileSize > 50 * 1024 * 1024) { // 50MB limit
                unlink($filePath);
                $this->editMessageText($chatId, $messageId, "❌ File too large (>50MB). Telegram limits file size.");
                return;
            }
            
            $this->sendDocument($chatId, $filePath, 
                $data === 'audio' ? "🎧 Audio Download Complete!" : "🎥 Video Download Complete!"
            );
            
            $this->editMessageText($chatId, $messageId, "✅ Download complete!");
            
            // Clean up
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
        } catch (Exception $e) {
            $this->log("Download error: " . $e->getMessage());
            $this->editMessageText($chatId, $messageId, "❌ Download failed: " . $e->getMessage());
        }
    }
    
    public function run() {
        // Start session for storing user data temporarily
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $input = file_get_contents('php://input');
        $this->log("Raw input: " . $input);
        
        if ($input) {
            $update = json_decode($input, true);
            if ($update) {
                $this->handleUpdate($update);
            }
        } else {
            // For polling method (not recommended for web, but works)
            $this->log("No input received - running in polling mode");
            $this->runPolling();
        }
    }
    
    private function runPolling() {
        $offset = 0;
        
        while (true) {
            $updates = $this->apiRequest('getUpdates', [
                'offset' => $offset,
                'timeout' => 30
            ]);
            
            if (isset($updates['result']) && is_array($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    $offset = $update['update_id'] + 1;
                    $this->handleUpdate($update);
                }
            }
            
            sleep(1);
        }
    }
}

// Main execution
$botToken = getenv('BOT_TOKEN') ?: '8507471476:AAHkLlfP4uZ8DwNsoffhDPQsfh61QoX9aZc';

if (empty($botToken) || $botToken === '8507471476:AAHkLlfP4uZ8DwNsoffhDPQsfh61QoX9aZc') {
    die("❌ ERROR: Please set BOT_TOKEN environment variable with your actual bot token\n");
}

$bot = new TelegramYouTubeBot($botToken);

// Check if running via CLI (polling) or web (webhook)
if (php_sapi_name() === 'cli') {
    echo "🤖 Starting Telegram Bot in polling mode...\n";
    echo "Bot Token: " . substr($botToken, 0, 10) . "...\n";
    $bot->run();
} else {
    // Webhook mode
    $bot->run();
}
?>