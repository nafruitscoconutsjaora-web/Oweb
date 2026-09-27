<?php
declare(strict_types=1);
$pageTitle = 'Account Profile & Security';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

$successMsg = '';
$errorMsg = '';

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Session validation expired.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['new_password_confirmation'] ?? '');

        if (!$currentPassword || !$newPassword) {
            $errorMsg = 'Please complete all password fields.';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $errorMsg = 'Current password entered is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $errorMsg = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMsg = 'New password confirmation does not match.';
        } else {
            try {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $db->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
                $upd->execute(['hash' => $newHash, 'id' => $userId]);

                AuditLogger::log('user.password_change', 'user', (string)$userId, null, null, 'user');
                $successMsg = 'Your password has been successfully updated.';
            } catch (Throwable $e) {
                error_log('[PASSWORD UPDATE ERROR] ' . $e->getMessage());
                $errorMsg = 'Unable to update password.';
            }
        }
    }
}

// Fetch Login History
$loginHistory = [];
try {
    $lhStmt = $db->prepare('
        SELECT * FROM login_activity
        WHERE email = :email
        ORDER BY created_at DESC
        LIMIT 10
    ');
    $lhStmt->execute(['email' => $user['email']]);
    $loginHistory = $lhStmt->fetchAll();
} catch (Throwable $e) {
    error_log('[LOGIN HISTORY ERROR] ' . $e->getMessage());
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="max-w-3xl mb-8">
        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Account Profile & Security</h1>
        <p class="text-xs text-gray-500 mt-1">Manage your account profile and security settings.</p>
    </div>

    <?php if ($successMsg): ?>
        <div class="card-premium p-4 mb-6 bg-green-50 border-green-200 text-green-800 text-xs font-semibold">
            <?= e($successMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="card-premium p-4 mb-6 bg-red-50 border-red-200 text-red-700 text-xs font-semibold">
            <?= e($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Profile Overview Card -->
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">Profile Details</h3>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="text-gray-400 block mb-0.5">Account ID (UUID)</label>
                    <div class="font-mono font-bold text-gray-800"><?= e($user['uuid']) ?></div>
                </div>

                <div>
                    <label class="text-gray-400 block mb-0.5">Full Name</label>
                    <div class="font-bold text-gray-900 text-sm"><?= e($user['name']) ?></div>
                </div>

                <div>
                    <label class="text-gray-400 block mb-0.5">Registered Email</label>
                    <div class="font-bold text-gray-900"><?= e($user['email']) ?></div>
                </div>

                <div>
                    <label class="text-gray-400 block mb-0.5">Membership Tier</label>
                    <div class="mt-1">
                        <span class="badge-purple font-semibold"><?= e($user['group_name'] ?? 'Retail Customer') ?></span>
                    </div>
                </div>

                <div>
                    <label class="text-gray-400 block mb-0.5">Account Status</label>
                    <span class="badge-success text-xs capitalize"><?= e($user['status']) ?></span>
                </div>

                <div>
                    <label class="text-gray-400 block mb-0.5">Registration Date</label>
                    <div class="text-gray-600 font-mono"><?= date('F j, Y, H:i', strtotime($user['created_at'])) ?> UTC</div>
                </div>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">Security: Update Password</h3>

            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Current Password</label>
                    <input type="password" name="current_password" required
                        class="w-full px-3.5 py-2.5 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">New Password (Min 8 Chars)</label>
                    <input type="password" name="new_password" required
                        class="w-full px-3.5 py-2.5 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Confirm New Password</label>
                    <input type="password" name="new_password_confirmation" required
                        class="w-full px-3.5 py-2.5 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>

                <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold mt-2">
                    Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- Login Activity Log -->
    <div class="card-premium p-6">
        <h3 class="font-bold text-gray-900 text-sm mb-4">Recent Account Activity</h3>

        <?php if (empty($loginHistory)): ?>
            <div class="py-6 text-center text-xs text-gray-400">
                No login events logged yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase">
                            <th class="py-2.5 px-4">Timestamp (UTC)</th>
                            <th class="py-2.5 px-4">IP Address</th>
                            <th class="py-2.5 px-4">Browser Client</th>
                            <th class="py-2.5 px-4 text-right">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($loginHistory as $lh): ?>
                            <tr>
                                <td class="py-2.5 px-4 font-mono text-gray-500">
                                    <?= date('Y-m-d H:i:s', strtotime($lh['created_at'])) ?>
                                </td>
                                <td class="py-2.5 px-4 font-mono font-medium text-gray-900">
                                    <?= e($lh['ip_address']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-gray-500 max-w-xs truncate">
                                    <?= e($lh['user_agent']) ?>
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    <span class="<?= $lh['status'] === 'success' ? 'text-green-600 font-bold' : 'text-red-600 font-bold' ?>">
                                        <?= strtoupper(e($lh['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
