<?php
declare(strict_types=1);
$pageTitle = 'Create Account';
require_once dirname(__DIR__) . '/config/config.php';

if (is_logged_in()) {
    header('Location: /user/dashboard.php');
    exit;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security validation failed. Please reload the form.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');
        $termsAccepted = !empty($_POST['terms']);

        if (!$name || !$email || !$password) {
            $errorMessage = 'Please complete all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $errorMessage = 'Password must be at least 8 characters in length.';
        } elseif ($password !== $passwordConfirm) {
            $errorMessage = 'Password confirmation does not match.';
        } elseif (!$termsAccepted) {
            $errorMessage = 'You must accept the Terms of Service to register.';
        } else {
            try {
                $db = Database::getConnection();

                // Check email uniqueness
                $checkStmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $checkStmt->execute(['email' => $email]);
                if ($checkStmt->fetch()) {
                    $errorMessage = 'An account with this email address already exists.';
                } else {
                    $db->beginTransaction();

                    $uuid = generate_uuid();
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user
                    $stmt = $db->prepare('
                        INSERT INTO users (uuid, name, email, password_hash, role, status, created_at, updated_at)
                        VALUES (:uuid, :name, :email, :hash, "user", "active", NOW(), NOW())
                    ');
                    $stmt->execute([
                        'uuid'  => $uuid,
                        'name'  => $name,
                        'email' => $email,
                        'hash'  => $passwordHash
                    ]);
                    $newUserId = (int)$db->lastInsertId();

                    // Create associated wallet record
                    Wallet::getOrCreate($db, $newUserId);

                    $db->commit();

                    // Automatically establish authenticated session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_role'] = 'user';

                    // Send welcome notification
                    Notifications::sendUser(
                        $newUserId,
                        'system',
                        'Welcome to ' . APP_NAME,
                        'Your account has been created. Add funds to your wallet to activate virtual verification numbers.',
                        '/user/wallet.php'
                    );

                    header('Location: /user/dashboard.php');
                    exit;
                }
            } catch (Throwable $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('[REGISTRATION ERROR] ' . $e->getMessage());
                $errorMessage = 'Registration service encountered an error. Please try again.';
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full space-y-6">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Create Account</h1>
            <p class="mt-2 text-sm text-gray-600">
                Already have an account? <a href="/auth/login.php" class="font-semibold text-[#6D28D9] hover:underline">Login</a>
            </p>
        </div>

        <div class="card-premium p-8 shadow-sm">
            <?php if ($errorMessage): ?>
                <div class="p-3.5 mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span><?= e($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Name</label>
                    <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Email</label>
                    <input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Password</label>
                    <input type="password" name="password" required autocomplete="new-password"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div class="flex items-start pt-1">
                    <input id="terms" name="terms" type="checkbox" required class="h-4 w-4 mt-0.5 text-[#6D28D9] focus:ring-[#6D28D9] border-gray-300 rounded">
                    <label for="terms" class="ml-2 block text-xs text-gray-600 leading-tight">
                        I agree to the <a href="/terms.php" target="_blank" class="text-[#6D28D9] hover:underline font-semibold">Terms of Service</a> and <a href="/privacy.php" target="_blank" class="text-[#6D28D9] hover:underline font-semibold">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full py-3 text-sm mt-2">
                    Register
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
