<?php
namespace App\Core;

class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $wrapped = $data + [
            'request_id' => self::requestId(),
            'server_time' => gmdate('c'),
        ];
        echo json_encode($wrapped);
    }

    public static function requestId(): string
    {
        if (empty($_SERVER['X_REQUEST_ID'])) { $_SERVER['X_REQUEST_ID'] = bin2hex(random_bytes(12)); }
        return $_SERVER['X_REQUEST_ID'];
    }
}
