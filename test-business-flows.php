<?php
// Business Flow Test Script
// Run this after deployment to test critical functionality

echo "🧪 Business Flow Testing\n";
echo "========================\n";

$tests = [
    'config_load' => 'Configuration Loading',
    'database_connect' => 'Database Connection', 
    'session_management' => 'Session Management',
    'csrf_protection' => 'CSRF Protection',
    'user_authentication' => 'User Authentication Flow',
    'admin_authentication' => 'Admin Authentication Flow',
    'voting_system' => 'Voting System',
    'api_endpoints' => 'API Endpoints'
];

$passed = 0;
$total = count($tests);

foreach ($tests as $test => $description) {
    echo "Testing: $description... ";
    
    switch ($test) {
        case 'config_load':
            try {
                require_once 'config/config.php';
                echo "✅ PASS\n";
                $passed++;
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'database_connect':
            try {
                require_once 'config/database.php';
                if (isset($pdo)) {
                    $pdo->query('SELECT 1');
                    echo "✅ PASS\n";
                    $passed++;
                } else {
                    echo "❌ FAIL: PDO not available\n";
                }
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'session_management':
            try {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['test'] = 'value';
                if (isset($_SESSION['test'])) {
                    echo "✅ PASS\n";
                    $passed++;
                } else {
                    echo "❌ FAIL: Session not working\n";
                }
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'csrf_protection':
            try {
                require_once 'src/Core/Security.php';
                $token = App\Core\Security::csrfToken();
                if (!empty($token)) {
                    echo "✅ PASS\n";
                    $passed++;
                } else {
                    echo "❌ FAIL: CSRF token empty\n";
                }
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        default:
            echo "⚠️  MANUAL TEST REQUIRED\n";
    }
}

echo "\n========================\n";
echo "Automated Tests: $passed/$total passed\n";
echo "Manual tests required for: authentication flows, voting, API endpoints\n";
echo "========================\n";
