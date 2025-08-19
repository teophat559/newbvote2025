<?php
echo "=== MySQL Test ===\n";

// Check if MySQL extension is available
if (extension_loaded('pdo_mysql')) {
    echo "✅ pdo_mysql extension is loaded\n";
} else {
    echo "❌ pdo_mysql extension is NOT loaded\n";
    exit;
}

// Try to connect to MySQL
try {
    // First, try to connect without specifying database
    $pdo = new PDO(
        "mysql:host=127.0.0.1;charset=utf8mb4",
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "✅ MySQL connection successful (without database)\n";

    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE 'newb_db'");
    $dbExists = $stmt->fetch();

    if ($dbExists) {
        echo "✅ Database 'newb_db' exists\n";
    } else {
        echo "⚠️  Database 'newb_db' does not exist, creating...\n";
        $pdo->exec("CREATE DATABASE newb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Database 'newb_db' created successfully\n";
    }

    // Now connect to the specific database
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=newb_db;charset=utf8mb4",
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "✅ MySQL connection to 'newb_db' successful\n";

    // Test creating a simple table
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_mysql (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100))");
    $pdo->exec("INSERT INTO test_mysql (name) VALUES ('test_value')");

    // Query the table
    $stmt = $pdo->query("SELECT * FROM test_mysql");
    $result = $stmt->fetch();

    echo "✅ Test table created and data inserted\n";
    echo "✅ Query result: " . json_encode($result) . "\n";

    // Clean up
    $pdo->exec("DROP TABLE test_mysql");
    echo "✅ Test table cleaned up\n";

} catch (Exception $e) {
    echo "❌ MySQL test failed: " . $e->getMessage() . "\n";

    // Try to provide helpful information
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "\n💡 Possible solutions:\n";
        echo "1. Check if MySQL service is running\n";
        echo "2. Verify MySQL root user password\n";
        echo "3. Try connecting with different credentials\n";
        echo "4. Check MySQL user permissions\n";
    }
}

echo "\n=== End MySQL Test ===\n";
?>
