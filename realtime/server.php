<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App as RatchetApp;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/env.php';
@set_time_limit(0);

echo "[WS] file entry\n";
echo "[WS] autoload+env loaded\n";
echo "[WS] Starting...\n";

// Basic logging to file
$logFile = dirname(__DIR__) . '/logs/ws.log';
if (!is_dir(dirname($logFile))) { @mkdir(dirname($logFile), 0777, true); }
@ini_set('log_errors', '1');
@ini_set('error_log', $logFile);
error_reporting(E_ALL);
@error_log("[WS] Booting Ratchet server host=" . (defined('WS_HOST')?WS_HOST:'?') . " port=" . (defined('WS_PORT')?WS_PORT:'?'));
echo "[WS] Booting host=" . (defined('WS_HOST')?WS_HOST:'?') . " port=" . (defined('WS_PORT')?WS_PORT:'?') . "\n";

class LoginRealtime implements MessageComponentInterface {
    protected $clients;
    public function __construct() { $this->clients = new \SplObjectStorage; }
    public function onOpen(ConnectionInterface $conn) { $this->clients->attach($conn); }
    public function onClose(ConnectionInterface $conn) { $this->clients->detach($conn); }
    public function onError(ConnectionInterface $conn, \Exception $e) { $conn->close(); }
    public function onMessage(ConnectionInterface $from, $msg) {
        // Broadcast nguyên văn. Khuyến nghị: gửi JSON dạng {type, request_id, ...}
        foreach ($this->clients as $client) { $client->send($msg); }
    }
}

// Bind trên 0.0.0.0 để client khác máy có thể kết nối nếu cần
try {
    $host = defined('WS_HOST') ? WS_HOST : '127.0.0.1';
    $port = defined('WS_PORT') ? WS_PORT : 8090;
    $app = new RatchetApp($host, (int)$port, '0.0.0.0');
    $app->route('/ws', new LoginRealtime, ['*']);
    @error_log("[WS] Listening on ws://" . $host . ":" . $port . "/ws (0.0.0.0 bind)");
    echo "[WS] Listening on ws://" . $host . ":" . $port . "/ws (0.0.0.0 bind)\n";
    $app->run();
} catch (\Throwable $e) {
    @error_log('[WS][FATAL] ' . $e->getMessage());
    fwrite(STDERR, "[WS][FATAL] " . $e->getMessage() . "\n");
    throw $e;
}
