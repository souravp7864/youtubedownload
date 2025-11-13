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
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Increased timeout for downloads
        
        // Handle file uploads differently
        if (isset($data['document']) && $data['document'] instanceof CURLFile) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: multipart/form-data"
            ]);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/x-www-form-urlencoded"
            ]);
        }
        
        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            $this->log("cURL Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function downloadYouTube($url, $format = 'video') {
        // Clean the URL first
        $cleanUrl = $this->cleanYouTubeUrl($url);
        if (!$cleanUrl) {
            throw new Exception("Invalid YouTube URL: $url");
        }
        
        $videoId = $this->extractVideoId($cleanUrl);
        if (!$videoId) {
            throw new Exception("Could not extract video ID from: $cleanUrl");
        }
        
        $tempFile = $this->downloadDir . '/' . $videoId . '.' . ($format === 'audio' ? 'mp3' : 'mp4');
        
        // Check if yt-dlp is available
        $ytdlpCheck = shell_exec('which yt-dlp');
        if (empty($ytdlpCheck)) {
            // Try with python3 -m yt_dlp
            $ytdlpCheck = shell_exec('which python3');
            if (empty($ytdlpCheck)) {
                throw new Exception("Python3 not found. Please check Dockerfile installation.");
            }
        }
        
        // Use python3 -m yt_dlp to ensure we're using the pip-installed version
        $cmd = "python3 -m yt_dlp -o " . escapeshellarg($tempFile);
        
        if ($format === 'audio') {
            $cmd .= " -x --audio-format mp3 --audio-quality 192k";
        } else {
            $cmd .= " -f best[height<=720][ext=mp4]"; // Limit to 720p for smaller files
        }
        
        $cmd .= " " . escapeshellarg($cleanUrl);
        $cmd .= " 2>&1";
        
        $this->log("Executing: $cmd");
        
        $output = shell_exec($cmd);
        $this->log("Download output: $output");
        
        // Look for the actual downloaded file
        $files = glob($this->downloadDir . '/' . $videoId . '.*');
        if (empty($files)) {
            // Try to find any recently created files in download directory
            $allFiles = glob($this->downloadDir . '/*');
            $recentFiles = array_filter($allFiles, function($file) {
                return filectime($file) > (time() - 120); // Files created in last 120 seconds
            });
            
            if (!empty($recentFiles)) {
                return current($recentFiles);
            }
            
            throw new Exception("Download failed. Output: " . $output);
        }
        
        return $files[0];
    }
    
    private function cleanYouTubeUrl($url) {
        // Remove tracking parameters and clean the URL
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }
        
        $host = strtolower($parsed['host']);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        
        // Check if it's a YouTube URL
        $isYoutube = strpos($host, 'youtube.com') !== false || 
                    strpos($host, 'youtu.be') !== false ||
                    strpos($host, 'www.youtube.com') !== false;
        
        if (!$isYoutube) {
            return false;
        }
        
        // Handle youtu.be short URLs
        if (strpos($host, 'youtu.be') !== false) {
            $videoId = ltrim($path, '/');
            return "https://www.youtube.com/watch?v=" . $videoId;
        }
        
        // For regular YouTube URLs, keep only the v parameter
        parse_str($query, $params);
        if (isset($params['v'])) {
            return "https://www.youtube.com/watch?v=" . $params['v'];
        }
        
        return $url;
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
                "🎥 <b>Video</b> - MP4 format (up to 720p)\n" .
                "🎧 <b>Audio</b> - MP3 format\n\n" .
                "Just paste a YouTube URL and choose your preferred format!"
            );
        } elseif (strpos($text, 'youtube.com') !== false || strpos($text, 'youtu.be') !== false) {
            // Store URL in temporary file
            file_put_contents($this->dataDir . "/last_url_{$chatId}.txt", $text);
            
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
        
        $this->answerCallbackQuery($queryId, "Processing your request...");
        
        // Get stored URL from file
        $urlFile = $this->dataDir . "/last_url_{$chatId}.txt";
        if (!file_exists($urlFile)) {
            $this->editMessageText($chatId, $messageId, "❌ Error: No URL found. Please send the YouTube link again.");
            return;
        }
        
        $url = trim(file_get_contents($urlFile));
        @unlink($urlFile); // Clean up
        
        $this->editMessageText($chatId, $messageId, "⏳ Downloading... This may take 1-2 minutes...");
        
        try {
            $filePath = $this->downloadYouTube($url, $data);
            
            if (!file_exists($filePath)) {
                throw new Exception("Downloaded file not found");
            }
            
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
            $errorMessage = "❌ Download failed: " . $e->getMessage();
            if (strlen($errorMessage) > 200) {
                $errorMessage = substr($errorMessage, 0, 197) . "...";
            }
            $this->editMessageText($chatId, $messageId, $errorMessage);
        }
    }
    
    public function runPolling() {
        $offset = 0;
        
        $this->log("🤖 Starting Telegram Bot in polling mode...");
        $this->log("Bot Token: " . substr($this->token, 0, 10) . "...");
        
        // Test the token first
        $test = $this->apiRequest('getMe', []);
        if (!$test || !isset($test['result'])) {
            $this->log("❌ Invalid BOT_TOKEN or Telegram API error: " . json_encode($test));
            $this->log("❌ Please check your BOT_TOKEN environment variable");
            return;
        }
        
        $this->log("✅ Bot authenticated successfully: @" . $test['result']['username']);
        
        while (true) {
            try {
                $updates = $this->apiRequest('getUpdates', [
                    'offset' => $offset,
                    'timeout' => 30,
                    'limit' => 100
                ]);
                
                if (isset($updates['result']) && is_array($updates['result'])) {
                    foreach ($updates['result'] as $update) {
                        $offset = $update['update_id'] + 1;
                        $this->handleUpdate($update);
                    }
                } else if (isset($updates['error_code'])) {
                    $this->log("Telegram API Error: " . $updates['description']);
                    sleep(10);
                }
                
                sleep(1);
            } catch (Exception $e) {
                $this->log("Polling error: " . $e->getMessage());
                sleep(5);
            }
        }
    }
}

// Main execution
$botToken = getenv('BOT_TOKEN');

if (empty($botToken)) {
    // Try to get from other sources
    $botToken = $_ENV['BOT_TOKEN'] ?? $_SERVER['BOT_TOKEN'] ?? '8507471476:AAHkLlfP4uZ8DwNsoffhDPQsfh61QoX9aZc';
}

if (empty($botToken)) {
    die("❌ ERROR: BOT_TOKEN environment variable is not set\n");
}

$bot = new TelegramYouTubeBot($botToken);
$bot->runPolling();
?>