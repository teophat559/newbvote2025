<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Response;

class SettingsController
{
    public function auth(): void
    {
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT v FROM settings WHERE k = ? LIMIT 1');
        $stmt->execute(['auth_otp_required']);
        $row = $stmt->fetch();
        $otpRequired = false;
        if ($row && isset($row['v'])) {
            $raw = $row['v'];
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $otpRequired = (bool)($json['otp_required'] ?? $json['value'] ?? false);
            } else {
                $val = strtolower(trim((string)$raw));
                if ($val === '1' || $val === 'true' || $val === 'yes' || $val === 'on') { $otpRequired = true; }
            }
        }
        Response::json(['ok'=>true,'otp_required'=>$otpRequired]);
    }
}
