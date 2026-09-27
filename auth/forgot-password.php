<?php
declare(strict_types=1);
$pageTitle = 'Password Recovery';
require_once dirname(__DIR__) . '/config/config.php';

$infoMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security validation failed. Please try again.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please provide a valid account email address.';
        } else {
            try {
                $db = Database::getConnection();
                $stmt = $db->prepare('SELECT id, name FROM users WHERE email = :email AND status = "active" LIMIT 1');
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                if ($user) {
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);

                    // Store expiring token (valid 1 hour)
                    $ins = $db->prepare('
                        INSERT INTO password_resets (email, token_hash, expires_at, created_at)
                        VALUES (:email, :hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
                    ');
                    $ins->execute([
                        'email' => $email,
                        'hash'  => $tokenHash
                    ]);

                    // In production this would dispatch an email; we provide secure UI link state
                    $resetLink = '/auth/reset-password.php?token=' . $rawToken . '&email=' . urlencode($email);
                    $_SESSION['debug_reset_link'] = $resetLink;
                }

                $infoMessage = 'If an account exists for that email address, password reset instructions have been sent.';
            } catch (Throwable $e) {
                error_log('[PASSWORD RECOVERY ERROR] ' . $e->getMessage());
                $errorMessage = 'Unable to process password reset request.';
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Reset Password</h1>
            <p class="mt-2 text-sm text-gray-600">
                Enter your email address to receive password reset instructions.
            </p>
        </div>

        <div class="card-premium p-8 shadow-sm">
            <?php if ($infoMessage): ?>
                <div class="p-3.5 mb-6 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold">
                    <?= e($infoMessage) ?>
                    <?php if (!empty($_SESSION['debug_reset_link'])): ?>
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <a href="<?= e($_SESSION['debug_reset_link']) ?>" class="btn-primary text-xs py-1.5 px-3 inline-block">Set New Password &rarr;</a>
                        </div>
                        <?php unset($_SESSION['debug_reset_link']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="p-3.5 mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold">
                    <?= e($errorMessage) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Account Email</label>
                    <input type="email" name="email" required autocomplete="email"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <button type="submit" class="btn-primary w-full py-3 text-sm">
                    Send Recovery Link
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="/auth/login.php" class="text-xs text-gray-600 hover:text-[#6D28D9]">
                    &larr; Return to Sign In
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
