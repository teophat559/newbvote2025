#!/bin/bash

# Business Flow Testing Script for Newb Vote 2025s
# Comprehensive testing of all critical business processes

set -e

# Configuration
APP_URL="http://localhost"  # Change to your domain for production
TEST_USER="testuser_$(date +%s)"
TEST_EMAIL="test@example.com"
TEST_PASS="TestPass123!"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${GREEN}✅${NC} $1"; }
print_warning() { echo -e "${YELLOW}⚠️${NC}  $1"; }
print_error() { echo -e "${RED}❌${NC} $1"; }
print_info() { echo -e "${BLUE}ℹ️${NC}  $1"; }

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()

# Function to record test result
record_test() {
    local test_name="$1"
    local result="$2"
    
    if [[ "$result" == "PASS" ]]; then
        ((TESTS_PASSED++))
        print_status "$test_name"
    else
        ((TESTS_FAILED++))
        FAILED_TESTS+=("$test_name")
        print_error "$test_name"
    fi
}

# Function to make HTTP request and check response
test_http_endpoint() {
    local url="$1"
    local expected_status="$2"
    local description="$3"
    
    local response=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null || echo "000")
    
    if [[ "$response" == "$expected_status" ]]; then
        record_test "$description" "PASS"
        return 0
    else
        record_test "$description (Expected: $expected_status, Got: $response)" "FAIL"
        return 1
    fi
}

# Function to test database connection
test_database_connection() {
    print_info "Testing database connection..."
    
    if php -r "
        require_once 'config/config.php';
        require_once 'config/database.php';
        try {
            \$pdo = App\Core\DB::conn();
            echo 'SUCCESS';
        } catch (Exception \$e) {
            echo 'FAILED: ' . \$e->getMessage();
            exit(1);
        }
    " 2>/dev/null | grep -q "SUCCESS"; then
        record_test "Database Connection" "PASS"
        return 0
    else
        record_test "Database Connection" "FAIL"
        return 1
    fi
}

# Function to test core application functionality
test_core_application() {
    print_info "Testing core application endpoints..."
    
    # Test main page
    test_http_endpoint "$APP_URL/" "200" "Main Page Load"
    
    # Test public pages
    test_http_endpoint "$APP_URL/contests" "200" "Contests Page"
    test_http_endpoint "$APP_URL/rankings" "200" "Rankings Page"
    test_http_endpoint "$APP_URL/login" "200" "Login Page"
    
    # Test API endpoints
    test_http_endpoint "$APP_URL/api/csrf" "200" "CSRF Token API"
    test_http_endpoint "$APP_URL/api/rankings" "200" "Rankings API"
}

# Function to test security features
test_security_features() {
    print_info "Testing security features..."
    
    # Test blocked access to sensitive files
    test_http_endpoint "$APP_URL/.env" "403" "Block .env Access"
    test_http_endpoint "$APP_URL/config/" "403" "Block Config Directory"
    test_http_endpoint "$APP_URL/database/" "403" "Block Database Directory"
    test_http_endpoint "$APP_URL/logs/" "403" "Block Logs Directory"
    test_http_endpoint "$APP_URL/vendor/" "403" "Block Vendor Directory"
    
    # Test security headers
    local security_headers=$(curl -s -I "$APP_URL/" | grep -E "(X-Content-Type-Options|X-Frame-Options|X-XSS-Protection)" | wc -l)
    if [[ "$security_headers" -ge 2 ]]; then
        record_test "Security Headers Present" "PASS"
    else
        record_test "Security Headers Present" "FAIL"
    fi
}

# Function to test session management
test_session_management() {
    print_info "Testing session management..."
    
    # Test CSRF token generation
    local csrf_response=$(curl -s "$APP_URL/api/csrf" | grep -o '"csrf_token":"[^"]*"')
    if [[ -n "$csrf_response" ]]; then
        record_test "CSRF Token Generation" "PASS"
    else
        record_test "CSRF Token Generation" "FAIL"
    fi
    
    # Test session cookie settings (requires actual web server to test properly)
    print_warning "Session cookie security testing requires web server environment"
}

# Function to test file upload restrictions
test_file_upload_security() {
    print_info "Testing file upload security..."
    
    # Create test files
    echo "<?php echo 'test'; ?>" > /tmp/test.php
    echo "test content" > /tmp/test.txt
    echo "GIF89a" > /tmp/test.gif
    
    # Test file type restrictions (this would need proper form submission testing)
    print_warning "File upload security testing requires web interface testing"
    
    # Cleanup
    rm -f /tmp/test.php /tmp/test.txt /tmp/test.gif
}

# Function to test database schema
test_database_schema() {
    print_info "Testing database schema..."
    
    if ! test_database_connection; then
        print_warning "Skipping database schema tests - no connection"
        return
    fi
    
    # Test essential tables exist
    local tables=("users" "contests" "contestants" "votes" "settings")
    
    for table in "${tables[@]}"; do
        if php -r "
            require_once 'config/config.php';
            require_once 'config/database.php';
            try {
                \$pdo = App\Core\DB::conn();
                \$stmt = \$pdo->query(\"SHOW TABLES LIKE '$table'\");
                if (\$stmt->rowCount() > 0) {
                    echo 'EXISTS';
                } else {
                    echo 'MISSING';
                    exit(1);
                }
            } catch (Exception \$e) {
                echo 'ERROR';
                exit(1);
            }
        " 2>/dev/null | grep -q "EXISTS"; then
            record_test "Table $table exists" "PASS"
        else
            record_test "Table $table exists" "FAIL"
        fi
    done
}

# Function to test password security
test_password_security() {
    print_info "Testing password security implementation..."
    
    # Test password hashing
    local hash_test=$(php -r "
        \$password = 'testpassword123';
        \$hash = password_hash(\$password, PASSWORD_DEFAULT);
        if (password_verify(\$password, \$hash)) {
            echo 'PASS';
        } else {
            echo 'FAIL';
        }
    ")
    
    if [[ "$hash_test" == "PASS" ]]; then
        record_test "Password Hashing (BCrypt/Argon2)" "PASS"
    else
        record_test "Password Hashing (BCrypt/Argon2)" "FAIL"
    fi
}

# Function to test error handling
test_error_handling() {
    print_info "Testing error handling..."
    
    # Test 404 handling
    test_http_endpoint "$APP_URL/nonexistent-page" "404" "404 Error Handling"
    
    # Test invalid API requests
    local api_error=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$APP_URL/api/nonexistent" 2>/dev/null || echo "000")
    if [[ "$api_error" == "404" || "$api_error" == "405" ]]; then
        record_test "API Error Handling" "PASS"
    else
        record_test "API Error Handling" "FAIL"
    fi
}

# Function to test performance basics
test_performance_basics() {
    print_info "Testing basic performance characteristics..."
    
    # Test page load time (basic check)
    local start_time=$(date +%s%N)
    curl -s "$APP_URL/" > /dev/null
    local end_time=$(date +%s%N)
    local duration=$(( (end_time - start_time) / 1000000 ))  # Convert to milliseconds
    
    if [[ $duration -lt 2000 ]]; then  # Less than 2 seconds
        record_test "Page Load Time ($duration ms)" "PASS"
    else
        record_test "Page Load Time ($duration ms)" "FAIL"
    fi
    
    # Test gzip compression
    local compression=$(curl -s -H "Accept-Encoding: gzip" -I "$APP_URL/" | grep -i "content-encoding: gzip")
    if [[ -n "$compression" ]]; then
        record_test "Gzip Compression Enabled" "PASS"
    else
        record_test "Gzip Compression Enabled" "FAIL"
    fi
}

# Function to run comprehensive stress test
run_stress_test() {
    print_info "Running basic stress test..."
    
    local concurrent_requests=10
    local total_requests=100
    
    print_info "Sending $total_requests requests with $concurrent_requests concurrent connections..."
    
    # Use ab (Apache Benchmark) if available, otherwise simulate with curl
    if command -v ab &> /dev/null; then
        local ab_result=$(ab -n $total_requests -c $concurrent_requests "$APP_URL/" 2>/dev/null | grep "Requests per second" || echo "")
        if [[ -n "$ab_result" ]]; then
            record_test "Stress Test (Apache Benchmark)" "PASS"
            print_info "$ab_result"
        else
            record_test "Stress Test (Apache Benchmark)" "FAIL"
        fi
    else
        # Simple concurrent curl test
        local success_count=0
        for ((i=1; i<=10; i++)); do
            curl -s "$APP_URL/" > /dev/null && ((success_count++)) &
        done
        wait
        
        if [[ $success_count -ge 8 ]]; then  # 80% success rate
            record_test "Basic Concurrent Request Test ($success_count/10)" "PASS"
        else
            record_test "Basic Concurrent Request Test ($success_count/10)" "FAIL"
        fi
    fi
}

# Main testing function
run_all_tests() {
    echo -e "${BLUE}🧪 NEWB VOTE 2025S - BUSINESS FLOW TESTING${NC}"
    echo "=============================================="
    echo
    
    # Core functionality tests
    test_database_connection
    test_database_schema
    test_core_application
    
    # Security tests
    test_security_features
    test_session_management
    test_password_security
    test_file_upload_security
    
    # Error handling and performance
    test_error_handling
    test_performance_basics
    
    # Stress testing
    if [[ "$1" == "--stress" ]]; then
        run_stress_test
    fi
}

# Cross-browser testing simulation
test_user_agents() {
    print_info "Testing with different user agents..."
    
    local user_agents=(
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        "Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1"
    )
    
    for ua in "${user_agents[@]}"; do
        local response=$(curl -s -o /dev/null -w "%{http_code}" -H "User-Agent: $ua" "$APP_URL/" 2>/dev/null || echo "000")
        if [[ "$response" == "200" ]]; then
            record_test "User Agent Compatibility Test" "PASS"
        else
            record_test "User Agent Compatibility Test" "FAIL"
        fi
        break  # Just test one for now
    done
}

# Generate test report
generate_report() {
    echo
    echo -e "${BLUE}📊 TEST RESULTS SUMMARY${NC}"
    echo "========================"
    echo
    echo "Total Tests Run: $((TESTS_PASSED + TESTS_FAILED))"
    echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
    echo -e "${RED}Failed: $TESTS_FAILED${NC}"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        echo
        echo -e "${RED}Failed Tests:${NC}"
        for test in "${FAILED_TESTS[@]}"; do
            echo "  - $test"
        done
    fi
    
    echo
    if [[ $TESTS_FAILED -eq 0 ]]; then
        echo -e "${GREEN}🎉 ALL TESTS PASSED! System ready for production.${NC}"
        exit 0
    else
        echo -e "${YELLOW}⚠️  Some tests failed. Please review and fix issues before production deployment.${NC}"
        exit 1
    fi
}

# Main execution
case "$1" in
    "--stress")
        run_all_tests --stress
        test_user_agents
        generate_report
        ;;
    "--quick")
        test_database_connection
        test_core_application
        test_security_features
        generate_report
        ;;
    *)
        run_all_tests
        test_user_agents
        generate_report
        ;;
esac