<?php
declare(strict_types=1);
$pageTitle = 'Set New Password';
require_once dirname(__DIR__) . '/config/config.php';

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Session validation error. Please try again.';
    } else {
        $token = trim($_POST['token'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');

        if (!$token || !$email || !$password) {
            $errorMessage = 'Please complete all required fields.';
        } elseif (strlen($password) < 8) {
            $errorMessage = 'Password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $errorMessage = 'Password confirmation does not match.';
        } else {
            try {
                $db = Database::getConnection();
                $tokenHash = hash('sha256', $token);

                $stmt = $db->prepare('
                    SELECT * FROM password_resets
                    WHERE email = :email AND token_hash = :hash AND expires_at > NOW()
                    ORDER BY id DESC LIMIT 1
                ');
                $stmt->execute(['email' => $email, 'hash' => $tokenHash]);
                $reset = $stmt->fetch();

                if (!$reset) {
                    $errorMessage = 'This password recovery token is invalid or has expired.';
                } else {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $db->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE email = :email');
                    $upd->execute(['hash' => $newHash, 'email' => $email]);

                    // Invalidate used reset tokens
                    $del = $db->prepare('DELETE FROM password_resets WHERE email = :email');
                    $del->execute(['email' => $email]);

                    $successMessage = 'Your password has been successfully updated. You may now log in.';
                }
            } catch (Throwable $e) {
                error_log('[RESET PASSWORD ERROR] ' . $e->getMessage());
                $errorMessage = 'Unable to complete password reset.';
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Create new password</h2>
            <p class="mt-2 text-sm text-gray-600">Please choose a strong, secure password for your account.</p>
        </div>

        <div class="card-premium p-8 shadow-sm">
            <?php if ($successMessage): ?>
                <div class="p-3.5 mb-6 rounded-xl bg-green-50 border border-green-200 text-green-800 text-xs font-semibold">
                    <?= e($successMessage) ?>
                    <div class="mt-3">
                        <a href="/auth/login.php" class="btn-primary text-xs py-2 px-4 inline-block">Proceed to Sign In &rarr;</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($errorMessage): ?>
                    <div class="p-3.5 mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold">
                        <?= e($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="email" value="<?= e($email) ?>">

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">New Password (Min 8 Characters)</label>
                        <input type="password" name="password" required autocomplete="new-password"
                            class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Confirm New Password</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                            class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                    </div>

                    <button type="submit" class="btn-primary w-full py-3 text-sm mt-2">
                        Update Password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
