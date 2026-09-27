<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Authentication & Authorization Controller (User & Admin RBAC)
 * ==============================================================================
 */

/**
 * Get current authenticated user profile
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && $cachedUser['id'] === (int)$_SESSION['user_id']) {
        return $cachedUser;
    }

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT u.*, w.balance AS wallet_balance, ug.name AS group_name, ug.discount_percentage
            FROM users u
            LEFT JOIN wallets w ON w.user_id = u.id
            LEFT JOIN user_groups ug ON ug.id = u.user_group_id
            WHERE u.id = :id AND u.status = "active"
            LIMIT 1
        ');
        $stmt->execute(['id' => (int)$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            unset($_SESSION['user_id'], $_SESSION['user_role']);
            return null;
        }

        $cachedUser = $user;
        return $user;
    } catch (Throwable $e) {
        error_log('[AUTH ERROR] ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if session has active authenticated user
 */
function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Check if active session is an administrative staff member
 */
function is_admin(): bool
{
    $user = current_user();
    return $user !== null && ($user['role'] === 'admin');
}

/**
 * Require user login or redirect to login page
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /auth/login.php?return=' . $returnUrl);
        exit;
    }
}

/**
 * Check if the admin has a specific permission
 */
function has_permission(string $permissionSlug): bool
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        return false;
    }

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT r.slug AS role_slug, p.slug AS permission_slug
            FROM admins a
            JOIN roles r ON r.id = a.role_id
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            LEFT JOIN permissions p ON p.id = rp.permission_id
            WHERE a.user_id = :user_id AND a.is_active = 1
        ');
        $stmt->execute(['user_id' => $user['id']]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            if ($row['role_slug'] === 'super_admin' || $row['permission_slug'] === $permissionSlug) {
                return true;
            }
        }

        return false;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Require administrator privilege and optional specific permission
 */
function require_admin(?string $permission = null): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>Access Denied</h2><p>Administrative privileges are required to access this resource.</p><a href="/user/dashboard.php">Return to Dashboard</a></body></html>';
        exit;
    }

    if ($permission !== null && !has_permission($permission)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>Permission Denied</h2><p>You do not have the required permission (' . e($permission) . ') for this operation.</p><a href="/admin/index.php">Return to Admin Overview</a></body></html>';
        exit;
    }
}

/**
 * Attempt authentication with rate limiting protection
 */
function attempt_login(string $email, string $password): array
{
    $email = trim(strtolower($email));
    $ip = get_client_ip();
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    try {
        $db = Database::getConnection();

        // 1. Rate limiting check: max 5 failed attempts in past 15 minutes per IP/email
        $rateStmt = $db->prepare('
            SELECT COUNT(*) AS failed_count
            FROM login_activity
            WHERE (email = :email OR ip_address = :ip)
              AND status = "failed"
              AND created_at >= (NOW() - INTERVAL 15 MINUTE)
        ');
        $rateStmt->execute(['email' => $email, 'ip' => $ip]);
        $failedCount = (int)$rateStmt->fetchColumn();

        if ($failedCount >= 5) {
            return [
                'success' => false,
                'message' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'
            ];
        }

        // 2. Fetch user
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Log failed attempt
            $logStmt = $db->prepare('INSERT INTO login_activity (user_id, email, ip_address, user_agent, status) VALUES (:user_id, :email, :ip, :ua, "failed")');
            $logStmt->execute([
                'user_id' => $user['id'] ?? null,
                'email'   => $email,
                'ip'      => $ip,
                'ua'      => $userAgent
            ]);

            return [
                'success' => false,
                'message' => 'Invalid email address or password.'
            ];
        }

        if ($user['status'] === 'suspended') {
            return [
                'success' => false,
                'message' => 'Your account has been suspended. Please contact customer support.'
            ];
        }

        // 3. Success: Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = $user['role'];

        // Update login stats
        $upd = $db->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id');
        $upd->execute(['ip' => $ip, 'id' => $user['id']]);

        // Log successful login
        $logStmt = $db->prepare('INSERT INTO login_activity (user_id, email, ip_address, user_agent, status) VALUES (:user_id, :email, :ip, :ua, "success")');
        $logStmt->execute([
            'user_id' => $user['id'],
            'email'   => $email,
            'ip'      => $ip,
            'ua'      => $userAgent
        ]);

        return [
            'success' => true,
            'user'    => $user
        ];
    } catch (Throwable $e) {
        error_log('[LOGIN ERROR] ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Authentication service currently unavailable.'
        ];
    }
}

/**
 * Terminate user session securely
 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
