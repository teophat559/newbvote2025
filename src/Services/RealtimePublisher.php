<?php
namespace App\Services;

class RealtimePublisher
{
    private string $url;

    public function __construct(?string $host = null, ?int $port = null, bool $secure = false)
    {
        $h = $host ?? (defined('WS_HOST') ? WS_HOST : '127.0.0.1');
        $p = $port ?? (defined('WS_PORT') ? WS_PORT : 8090);
        $scheme = $secure ? 'wss' : 'ws';
        $this->url = sprintf('%s://%s:%d/ws', $scheme, $h, $p);
    }

    public function publish(string $type, array $payload): void
    {
        // Minimal, best-effort WebSocket text frame sender for ws:// only
        try {
            $parts = parse_url($this->url);
            if (!$parts || !isset($parts['host'], $parts['port'], $parts['path'])) { return; }
            if (($parts['scheme'] ?? 'ws') !== 'ws') { return; }
            $host = $parts['host']; $port = (int)$parts['port']; $path = $parts['path'];
            $fp = @fsockopen($host, $port, $errno, $errstr, 1.5);
            if (!$fp) { return; }
            $key = base64_encode(random_bytes(16));
            $headers = "GET {$path} HTTP/1.1\r\n" .
                       "Host: {$host}:{$port}\r\n" .
                       "Upgrade: websocket\r\n" .
                       "Connection: Upgrade\r\n" .
                       "Sec-WebSocket-Key: {$key}\r\n" .
                       "Sec-WebSocket-Version: 13\r\n\r\n";
            fwrite($fp, $headers);
            stream_set_timeout($fp, 1);
            $resp = fread($fp, 2048); // ignore verification for brevity
            $msg = json_encode(array_merge(['type' => $type], $payload));
            $frame = $this->encodeFrame($msg);
            fwrite($fp, $frame);
            fclose($fp);
        } catch (\Throwable $e) { /* ignore */ }
    }

    private function encodeFrame(string $payload): string
    {
        // Client-to-server frames MUST be masked
        $finOpcode = 0x81; // FIN=1, text frame
        $data = $payload;
        $len = strlen($data);
        $maskBit = 0x80; // set mask bit
        $maskKey = random_bytes(4);
        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $data[$i] ^ $maskKey[$i % 4];
        }
        if ($len <= 125) {
            $header = pack('CC', $finOpcode, $maskBit | $len);
        } elseif ($len <= 65535) {
            $header = pack('CCn', $finOpcode, $maskBit | 126, $len);
        } else {
            // 64-bit length in network byte order
            $lenHi = $len >> 32;         // high 32 bits
            $lenLo = $len & 0xFFFFFFFF;  // low 32 bits
            $header = pack('CCNN', $finOpcode, $maskBit | 127, $lenHi, $lenLo);
        }
        return $header . $maskKey . $masked;
    }
}
