<?php
// Session configuration - must be set before session_start()

// Session security settings (MUST be set before session_start())
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Will be overridden by env if available
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_lifetime', 0); // Session cookie

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>