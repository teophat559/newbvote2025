<?php
/**
 * Database Security and Schema Validation Script
 * Verifies database structure and security for production deployment
 */

// Prevent direct access except in CLI or debug mode
if (php_sapi_name() !== 'cli' && !isset($_GET['db_check'])) {
    http_response_code(403);
    exit('Access denied. Run via CLI or add ?db_check=1 parameter.');
}

echo "🗄️ Database Security & Schema Check\n";
echo "====================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Load environment and database connection
try {
    require_once 'config/env.php';
    require_once 'config/database.php';
    
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }
    
    echo "✅ Database connection established\n";
    $success[] = "Database connection successful";
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    $errors[] = "Database connection failed: " . $e->getMessage();
    exit(1);
}

/**
 * 1. CHECK DATABASE SECURITY SETTINGS
 */
echo "\n1. Checking database security settings...\n";

try {
    // Check database version
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch()['version'];
    echo "   ℹ️  Database version: $version\n";
    
    // Check if using SSL/TLS
    $stmt = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
    $ssl = $stmt->fetch();
    if ($ssl && !empty($ssl['Value'])) {
        echo "   ✅ SSL/TLS encryption enabled\n";
        $success[] = "Database SSL/TLS enabled";
    } else {
        echo "   ⚠️  SSL/TLS encryption not detected\n";
        $warnings[] = "Database SSL/TLS not enabled";
    }
    
    // Check database user privileges
    $stmt = $pdo->query("SELECT USER(), CURRENT_USER()");
    $users = $stmt->fetch();
    echo "   ℹ️  Connected as: " . $users['CURRENT_USER()'] . "\n";
    
    // Check for dangerous privileges
    try {
        $stmt = $pdo->query("SHOW GRANTS");
        $grants = $stmt->fetchAll();
        $hasDangerousPrivs = false;
        
        foreach ($grants as $grant) {
            $grantText = $grant['Grants for ' . $users['CURRENT_USER()']];
            if (stripos($grantText, 'ALL PRIVILEGES') !== false && 
                stripos($grantText, 'GRANT OPTION') !== false) {
                echo "   ⚠️  Database user has ALL PRIVILEGES with GRANT OPTION\n";
                $warnings[] = "Database user has excessive privileges";
                $hasDangerousPrivs = true;
            }
        }
        
        if (!$hasDangerousPrivs) {
            echo "   ✅ Database user privileges appear limited\n";
            $success[] = "Database user privileges appropriate";
        }
        
    } catch (Exception $e) {
        echo "   ⚠️  Could not check user privileges: " . $e->getMessage() . "\n";
        $warnings[] = "Could not verify database user privileges";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error checking database security: " . $e->getMessage() . "\n";
    $errors[] = "Database security check failed: " . $e->getMessage();
}

/**
 * 2. VERIFY REQUIRED TABLES EXIST
 */
echo "\n2. Checking required database schema...\n";

$requiredTables = [
    'users' => 'User accounts',
    'contests' => 'Contest data', 
    'contestants' => 'Contestant data',
    'votes' => 'Voting records',
    'sessions' => 'User sessions',
    'settings' => 'Application settings',
    'login_requests' => 'Login requests',
    'otp_codes' => 'OTP verification codes'
];

foreach ($requiredTables as $table => $description) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ Table exists: $table ($description)\n";
            $success[] = "Table exists: $table";
        } else {
            echo "   ❌ Table missing: $table ($description)\n";
            $errors[] = "Required table missing: $table";
        }
    } catch (Exception $e) {
        echo "   ❌ Error checking table $table: " . $e->getMessage() . "\n";
        $errors[] = "Error checking table $table: " . $e->getMessage();
    }
}

/**
 * 3. CHECK SENSITIVE DATA ENCRYPTION
 */
echo "\n3. Checking data encryption and security...\n";

// Check if passwords are properly hashed
try {
    $stmt = $pdo->query("SELECT id, password FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    $properlyHashed = 0;
    $total = count($users);
    
    foreach ($users as $user) {
        // Check if password looks like bcrypt or argon2 hash
        if (preg_match('/^\$2[ayb]\$.{56}$/', $user['password']) || 
            preg_match('/^\$argon2i?\$/', $user['password'])) {
            $properlyHashed++;
        }
    }
    
    if ($total > 0) {
        $percentage = round(($properlyHashed / $total) * 100);
        if ($percentage == 100) {
            echo "   ✅ All user passwords properly hashed ($properlyHashed/$total)\n";
            $success[] = "User passwords properly encrypted";
        } else {
            echo "   ❌ Some user passwords not properly hashed ($properlyHashed/$total = $percentage%)\n";
            $errors[] = "User passwords not properly encrypted";
        }
    } else {
        echo "   ℹ️  No users found to check password encryption\n";
    }
    
} catch (Exception $e) {
    echo "   ⚠️  Could not check password encryption: " . $e->getMessage() . "\n";
    $warnings[] = "Could not verify password encryption";
}

// Check for sensitive data exposure in logs or temporary tables
try {
    $sensitivePatterns = [
        'password',
        'secret',
        'token',
        'key'
    ];
    
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($allTables as $table) {
        if (stripos($table, 'log') !== false || stripos($table, 'temp') !== false) {
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll();
            
            foreach ($columns as $column) {
                foreach ($sensitivePatterns as $pattern) {
                    if (stripos($column['Field'], $pattern) !== false) {
                        echo "   ⚠️  Potentially sensitive column in $table: " . $column['Field'] . "\n";
                        $warnings[] = "Sensitive column in $table: " . $column['Field'];
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "   ⚠️  Could not check for sensitive data exposure: " . $e->getMessage() . "\n";
    $warnings[] = "Could not check for sensitive data exposure";
}

/**
 * 4. CHECK INDEXES AND PERFORMANCE
 */
echo "\n4. Checking database performance optimization...\n";

$performanceTables = ['users', 'votes', 'contests', 'sessions'];

foreach ($performanceTables as $table) {
    try {
        $stmt = $pdo->query("SHOW INDEX FROM `$table`");
        $indexes = $stmt->fetchAll();
        
        if (count($indexes) > 0) {
            echo "   ✅ Table $table has " . count($indexes) . " index(es)\n";
            $success[] = "Table $table has indexes";
        } else {
            echo "   ⚠️  Table $table has no indexes (may affect performance)\n";
            $warnings[] = "Table $table missing indexes";
        }
        
    } catch (Exception $e) {
        echo "   ⚠️  Could not check indexes for $table: " . $e->getMessage() . "\n";
        $warnings[] = "Could not check indexes for $table";
    }
}

/**
 * 5. TEST BASIC OPERATIONS
 */
echo "\n5. Testing basic database operations...\n";

try {
    // Test SELECT operation
    $pdo->query("SELECT 1")->fetch();
    echo "   ✅ SELECT operation successful\n";
    $success[] = "Database SELECT operation works";
    
    // Test transaction support
    $pdo->beginTransaction();
    $pdo->rollback();
    echo "   ✅ Transaction support available\n";
    $success[] = "Database transaction support works";
    
    // Test prepared statements
    $stmt = $pdo->prepare("SELECT 1 WHERE 1 = ?");
    $stmt->execute([1]);
    $result = $stmt->fetch();
    if ($result) {
        echo "   ✅ Prepared statements working\n";
        $success[] = "Database prepared statements work";
    }
    
} catch (Exception $e) {
    echo "   ❌ Database operation test failed: " . $e->getMessage() . "\n";
    $errors[] = "Database operation test failed: " . $e->getMessage();
}

/**
 * FINAL SUMMARY
 */
echo "\n" . str_repeat("=", 50) . "\n";
echo "DATABASE SECURITY SUMMARY\n";
echo str_repeat("=", 50) . "\n";

echo "\n✅ Success: " . count($success) . " items\n";
if (!empty($success)) {
    foreach ($success as $item) {
        echo "   • $item\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  Warnings: " . count($warnings) . " items\n";
    foreach ($warnings as $item) {
        echo "   • $item\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ Errors: " . count($errors) . " items\n";
    foreach ($errors as $item) {
        echo "   • $item\n";
    }
}

echo "\n📋 RECOMMENDATIONS:\n";
echo "1. Enable SSL/TLS encryption for database connections\n";
echo "2. Use database user with minimal required privileges\n";
echo "3. Ensure all passwords are hashed with bcrypt or argon2\n";
echo "4. Add indexes to frequently queried columns\n";
echo "5. Regular security audits and backups\n";
echo "6. Monitor database logs for suspicious activity\n";

if (empty($errors)) {
    echo "\n🎉 DATABASE SECURITY CHECK PASSED\n";
    exit(0);
} else {
    echo "\n🚫 DATABASE SECURITY ISSUES FOUND\n";
    echo "Please address the " . count($errors) . " error(s) before production deployment.\n";
    exit(1);
}
?>