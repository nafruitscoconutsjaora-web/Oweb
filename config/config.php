<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * SMS Verification & Virtual Number Reseller Platform
 * Central Configuration File (PHP 8.2+)
 * ==============================================================================
 */

// Force UTC timezone across all server date/time computations
date_default_timezone_set('UTC');

// Error reporting settings: Production secure default (errors logged, never printed to client)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

// Base Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PROVIDERS_PATH', ROOT_PATH . '/providers');

// Application Environment & URL
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_NAME', getenv('APP_NAME') ?: 'Verifex SMS Hub');
define('APP_URL', rtrim(getenv('APP_URL') ?: '', '/'));

// Database Credentials (Environment variables preferred, fallbacks for server setup)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'sms_platform');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Security & Encryption Secret
define('APP_KEY', getenv('APP_KEY') ?: 'vfx_secret_key_89324789324798327498234');

// Session Hardening
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Financial Standards
define('DEFAULT_CURRENCY', 'USD');
define('DEFAULT_CURRENCY_SYMBOL', '$');
define('MIN_DEPOSIT_AMOUNT', 5.00);
define('MAX_DEPOSIT_AMOUNT', 1000.00);
define('DEFAULT_ACTIVATION_TIMEOUT_SECONDS', 1200); // 20 minutes

// Autoload helper classes and database singleton
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/security.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/wallet.php';
require_once INCLUDES_PATH . '/pricing.php';
require_once INCLUDES_PATH . '/audit.php';
require_once INCLUDES_PATH . '/notifications.php';
require_once INCLUDES_PATH . '/refund.php';
