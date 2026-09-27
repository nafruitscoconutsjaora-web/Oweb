<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Security & Sanitization Helpers
 * ==============================================================================
 */

/**
 * Escape HTML output to prevent Cross-Site Scripting (XSS)
 */
function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate or retrieve the active session CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input tag
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token using timing-safe comparison
 */
function verify_csrf(?string $submittedToken): bool
{
    if (empty($submittedToken) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals((string)$_SESSION['csrf_token'], $submittedToken);
}

/**
 * Get client IP address with proxy header validation
 */
function get_client_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', (string)$_SERVER[$header]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '127.0.0.1';
}

/**
 * Encrypt sensitive provider secrets or API keys
 */
function encrypt_secret(string $plaintext): string
{
    $cipher = 'aes-256-gcm';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $tag = '';
    $key = hash('sha256', APP_KEY, true);

    $encrypted = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) {
        throw new RuntimeException('Encryption cipher failure');
    }

    return base64_encode($iv . $tag . $encrypted);
}

/**
 * Decrypt sensitive provider secrets or API keys
 */
function decrypt_secret(string $encryptedPayload): string
{
    $data = base64_decode($encryptedPayload, true);
    if ($data === false) {
        return '';
    }

    $cipher = 'aes-256-gcm';
    $ivLength = openssl_cipher_iv_length($cipher);
    $tagLength = 16;

    if (strlen($data) < ($ivLength + $tagLength)) {
        return '';
    }

    $iv = substr($data, 0, $ivLength);
    $tag = substr($data, $ivLength, $tagLength);
    $ciphertext = substr($data, $ivLength + $tagLength);
    $key = hash('sha256', APP_KEY, true);

    $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $decrypted === false ? '' : $decrypted;
}

/**
 * Format currency amount with financial accuracy
 */
function format_currency(float|string $amount, string $symbol = DEFAULT_CURRENCY_SYMBOL): string
{
    $val = (float)$amount;
    return $symbol . number_format($val, 2, '.', ',');
}

/**
 * Generate cryptographically secure UUIDv4
 */
function generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Generate human-readable transaction/order reference
 */
function generate_ref(string $prefix = 'VFX'): string
{
    return sprintf('%s-%s-%s', strtoupper($prefix), strtoupper(bin2hex(random_bytes(4))), date('ymd'));
}

/**
 * Send JSON API response and terminate
 */
function send_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send strict security headers
 */
function set_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
