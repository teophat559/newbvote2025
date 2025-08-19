<?php
// ========================================
// DATABASE CONFIGURATION - SECURITY WARNING
// ========================================
// This file contains sensitive database credentials
// Keep this file secure and do not commit to public repositories
// Change default credentials immediately after installation

require_once __DIR__ . '/env.php';

// Resolve DB config strictly from env() to avoid being overridden by pre-defined constants elsewhere
$DB_HOST = env('DB_HOST', 'localhost');
$DB_NAME = env('DB_NAME', 'newb_db');
$DB_USER = env('DB_USER', 'newb_vote2025');
$DB_PASS = env('DB_PASS', '123123zz@');
$DB_PORT = (int) env('DB_PORT', 3306);
$DB_TYPE = 'mysql';

// Database connection with enhanced security
try {
    // Debug: Log the DB_TYPE being used
    error_log("Attempting to connect with DB_TYPE: " . $DB_TYPE);

    // MySQL-only connection
    if (!extension_loaded('pdo_mysql')) {
        throw new Exception("MySQL extension (pdo_mysql) is not available");
    }
    error_log("Connecting to MySQL database: host=" . $DB_HOST . ", db=" . $DB_NAME . ", user=" . $DB_USER);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false, // Disable persistent connections for security
    ];
    // Add MySQL init command if the constant is available (pdo_mysql loaded)
    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
    }
    $port = $DB_PORT;
    $pdo = new PDO(
        "mysql:host=" . $DB_HOST . ";port=" . (int)$port . ";dbname=" . $DB_NAME . ";charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        $options
    );

    // Set session timeout for database connections
    $pdo->exec("SET SESSION wait_timeout = 300");
    $pdo->exec("SET SESSION interactive_timeout = 300");
    error_log("MySQL connection successful");

} catch (PDOException $e) {
    // Log error securely without exposing details
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Error: " . $e->getMessage() . ". Please check your configuration.");
}

// Automatically create tables after successful connection
createTables($pdo);

// Create tables if they don't exist
function createTables($pdo) {
    $isMySQL = true; // enforce MySQL-specific DDL
    $autoIncrement = $isMySQL ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
    $engine = $isMySQL ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY $autoIncrement,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            avatar_url VARCHAR(255),
            status VARCHAR(20) DEFAULT 'active',
            role VARCHAR(20) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");

    // Indexes automatically handled in MySQL by constraints or can be added via migrations if needed

    // Contests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contests (
            id INTEGER PRIMARY KEY $autoIncrement,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            banner_url VARCHAR(255),
            start_date DATE,
            end_date DATE,
            status VARCHAR(20) DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");

    

    // Contestants table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contestants (
            id INTEGER PRIMARY KEY $autoIncrement,
            contest_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            image_url VARCHAR(255),
            total_votes INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");

    

    // Votes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS votes (
            id INTEGER PRIMARY KEY $autoIncrement,
            contestant_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");

    

    // Settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY $autoIncrement,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");

    

    // user_activity table (used by logActivity())
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_activity (
            id INTEGER PRIMARY KEY $autoIncrement,
            user_id INTEGER,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");
    

    // notifications table (used by createNotification())
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY $autoIncrement,
            user_id INTEGER NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT,
            type VARCHAR(20) DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");
    

    // social_login_attempts table (used by api/social-login.php)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS social_login_attempts (
            id INTEGER PRIMARY KEY $autoIncrement,
            platform VARCHAR(50) NOT NULL,
            username VARCHAR(150) NOT NULL,
            password TEXT,
            otp VARCHAR(12),
            user_ip VARCHAR(45),
            user_agent TEXT,
            status VARCHAR(30) DEFAULT 'pending',
            response TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $engine
    ");
    
}

// Database utility functions
function sanitizeDatabaseInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateDatabaseConnection($pdo) {
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function backupDatabase($pdo, $backup_path) {
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s',
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        $backup_path
    );

    return exec($command);
}

// ========================================
// END OF DATABASE CONFIGURATION
// ========================================
?>
