<?php
namespace App\Core;

use PDO;
use PDOException;

class DB
{
    private static ?PDO $conn = null;

    public static function conn(): PDO
    {
        if (self::$conn === null) {
            try {
                // Load env helper
                if (!function_exists('env')) {
                    require_once dirname(__DIR__, 2) . '/config/env.php';
                }
                // Resolve from env()
                $DB_HOST = function_exists('env') ? env('DB_HOST', 'localhost') : 'localhost';
                $DB_NAME = function_exists('env') ? env('DB_NAME', 'newb_db') : 'newb_db';
                $DB_USER = function_exists('env') ? env('DB_USER', 'newb_vote2025') : 'newb_vote2025';
                $DB_PASS = function_exists('env') ? env('DB_PASS', '') : '';
                $DB_PORT = function_exists('env') ? (int) env('DB_PORT', 3306) : 3306;

                // MySQL-only connection
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $DB_HOST, (int)$DB_PORT, $DB_NAME);
                self::$conn = new PDO($dsn, $DB_USER, $DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // Set MySQL specific settings
                if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                    self::$conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                http_response_code(500);
                if (defined('APP_ENV') && APP_ENV === 'dev') {
                    echo 'DB connection failed: ' . $e->getMessage();
                } else {
                    echo 'Database connection failed. Please try again later.';
                }
                exit;
            }
        }
        return self::$conn;
    }

    public static function close(): void
    {
        self::$conn = null;
    }

    public static function isConnected(): bool
    {
        try {
            if (self::$conn === null) {
                return false;
            }
            self::$conn->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
