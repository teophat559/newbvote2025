<?php
namespace App\Services;

final class SystemStatusService
{
    public static function publish(string $status = 'ok', array $extra = []): void
    {
        $payload = array_merge([
            'status' => $status,
            'time' => date('c'),
        ], $extra);
        try {
            (new RealtimePublisher())->publish('system.status.update', $payload);
        } catch (\Throwable $e) { /* ignore */ }
    }

    public static function ok(array $extra = []): void { self::publish('ok', $extra); }
    public static function degraded(array $extra = []): void { self::publish('degraded', $extra); }
    public static function down(array $extra = []): void { self::publish('down', $extra); }
}
