<?php
declare(strict_types=1);
$adminTitle = 'System & Governance Settings';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('settings.manage');

$db = Database::getConnection();
$message = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session validation error.';
    } else {
        $allowedKeys = [
            'site_name',
            'site_currency',
            'min_deposit',
            'max_deposit',
            'activation_timeout_minutes',
            'default_margin_percentage',
            'contact_email',
            'maintenance_mode',
            'razorpay_enabled',
            'razorpay_key_id',
            'razorpay_key_secret'
        ];

        try {
            $db->beginTransaction();
            $upd = $db->prepare('
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES (:k, :v, NOW())
                ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()
            ');

            foreach ($allowedKeys as $key) {
                if (isset($_POST[$key])) {
                    $val = trim((string)$_POST[$key]);
                    $upd->execute(['k' => $key, 'v' => $val, 'v2' => $val]);
                }
            }

            $db->commit();
            AuditLogger::log('settings.update', 'system_settings', 'global', null, null, 'admin');
            $message = 'System configuration updated successfully.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[SETTINGS UPDATE ERROR] ' . $e->getMessage());
            $error = 'Failed to save system settings.';
        }
    }
}

// Fetch all current settings
$settings = [];
try {
    $rows = $db->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Throwable $e) {
    error_log('[SETTINGS FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="max-w-4xl space-y-6">
    <div>
        <h2 class="text-xl font-extrabold text-gray-900">Platform Governance & Global Settings</h2>
        <p class="text-xs text-gray-500 mt-0.5">Control operational thresholds, timeout limits, and gateway configurations.</p>
    </div>

    <?php if ($message): ?>
        <div class="card-premium p-4 bg-green-50 border-green-200 text-green-800 text-xs font-semibold">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="card-premium p-4 bg-red-50 border-red-200 text-red-700 text-xs font-semibold">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">

        <!-- General Parameters -->
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">General Configuration</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Platform Brand Name</label>
                    <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? 'Verifex SMS Hub') ?>" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Support Contact Email</label>
                    <input type="email" name="contact_email" value="<?= e($settings['contact_email'] ?? 'support@verifex.net') ?>" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>
        </div>

        <!-- Financial & Limits -->
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">Financial & Activation Thresholds</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Base Currency</label>
                    <input type="text" name="site_currency" value="<?= e($settings['site_currency'] ?? 'USD') ?>" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl uppercase font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Min Deposit Limit ($)</label>
                    <input type="number" step="0.5" name="min_deposit" value="<?= e($settings['min_deposit'] ?? '5.00') ?>" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Max Deposit Limit ($)</label>
                    <input type="number" step="10" name="max_deposit" value="<?= e($settings['max_deposit'] ?? '1000.00') ?>" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Activation Expiry Window (Minutes)</label>
                    <input type="number" name="activation_timeout_minutes" value="<?= e($settings['activation_timeout_minutes'] ?? '20') ?>" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Default Markup Margin (%)</label>
                    <input type="number" step="1" name="default_margin_percentage" value="<?= e($settings['default_margin_percentage'] ?? '25.00') ?>" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>
        </div>

        <!-- Payment Gateway Settings -->
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">Payment Gateway Integration (Razorpay)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Gateway Key ID</label>
                    <input type="text" name="razorpay_key_id" value="<?= e($settings['razorpay_key_id'] ?? '') ?>" placeholder="rzp_live_••••••••" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Gateway Secret</label>
                    <input type="password" name="razorpay_key_secret" value="<?= e($settings['razorpay_key_secret'] ?? '') ?>" placeholder="••••••••••••••••" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl font-mono focus:outline-hidden focus:border-[#6D28D9]">
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary text-xs py-3 px-6 shadow-md">
            Save System Settings
        </button>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
