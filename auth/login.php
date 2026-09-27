<?php
declare(strict_types=1);
$pageTitle = 'Sign In';
require_once dirname(__DIR__) . '/config/config.php';

if (is_logged_in()) {
    header('Location: /user/dashboard.php');
    exit;
}

$errorMessage = '';
$returnUrl = $_GET['return'] ?? '/user/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security session expired. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (!$email || !$password) {
            $errorMessage = 'Please provide both your email address and password.';
        } else {
            $authResult = attempt_login($email, $password);
            if ($authResult['success']) {
                $target = filter_var($returnUrl, FILTER_SANITIZE_URL) ?: '/user/dashboard.php';
                header('Location: ' . $target);
                exit;
            } else {
                $errorMessage = $authResult['message'];
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full space-y-6">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Login</h1>
            <p class="mt-2 text-sm text-gray-600">
                Don't have an account? <a href="/auth/register.php" class="font-semibold text-[#6D28D9] hover:underline">Register</a>
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

            <form method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Email</label>
                    <input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Password</label>
                        <a href="/auth/forgot-password.php" class="text-xs text-[#6D28D9] hover:underline font-medium">Forgot password?</a>
                    </div>
                    <input type="password" name="password" required autocomplete="current-password"
                        class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div class="flex items-center">
                    <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-[#6D28D9] focus:ring-[#6D28D9] border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-xs text-gray-600">Remember me</label>
                </div>

                <button type="submit" class="btn-primary w-full py-3 text-sm">
                    Login
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
