<?php
declare(strict_types=1);
$pageTitle = 'Live Activation';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$activationId = (int)($_GET['id'] ?? 0);
$db = Database::getConnection();

$message = '';
$error = '';

// Fetch activation details
$stmt = $db->prepare('
    SELECT a.*, o.order_number, o.price, o.id AS order_id, o.status AS order_status,
           s.name AS service_name, c.name AS country_name, c.flag_emoji,
           p.name AS provider_name, p.supports_cancellation
    FROM activations a
    JOIN orders o ON o.id = a.order_id
    JOIN services s ON s.id = o.service_id
    JOIN countries c ON c.id = o.country_id
    JOIN providers p ON p.id = a.provider_id
    WHERE a.id = :aid AND a.user_id = :uid
    LIMIT 1
');
$stmt->execute(['aid' => $activationId, 'uid' => $userId]);
$activation = $stmt->fetch();

if (!$activation) {
    header('Location: /user/dashboard.php');
    exit;
}

// Fetch any received SMS messages
$smsStmt = $db->prepare('SELECT * FROM sms_messages WHERE activation_id = :aid ORDER BY received_at DESC');
$smsStmt->execute(['aid' => $activationId]);
$smsList = $smsStmt->fetchAll();

// Handle User Cancellation Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please reload.';
    } elseif ($activation['status'] === 'sms_received' || !empty($smsList)) {
        $error = 'This number has already received an SMS code and cannot be cancelled.';
    } elseif (in_array($activation['status'], ['cancelled', 'refunded', 'expired'], true)) {
        $error = 'This activation has already concluded.';
    } else {
        // Attempt provider cancellation
        $adapter = ProviderFactory::createById((int)$activation['provider_id']);
        if ($adapter) {
            $adapter->cancelActivation($activation['provider_activation_id']);
        }

        // Process refund
        $refundRes = RefundEngine::process(
            (int)$activation['order_id'],
            'User requested cancellation prior to SMS receipt',
            'user'
        );

        if ($refundRes['success']) {
            $message = 'Activation cancelled. Full amount has been refunded to your wallet.';
            // Refresh record
            $stmt->execute(['aid' => $activationId, 'uid' => $userId]);
            $activation = $stmt->fetch();
        } else {
            $error = $refundRes['message'];
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="/user/dashboard.php" class="text-xs text-gray-500 hover:text-[#6D28D9] mb-1 inline-block">
                &larr; Return to Dashboard
            </a>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Live Activation Monitor</h1>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400">Order Ref:</span>
            <span class="font-mono text-xs font-bold text-gray-700">#<?= e($activation['order_number']) ?></span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="card-premium p-4 mb-6 bg-green-50 border-green-200 text-green-800 text-xs font-semibold">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="card-premium p-4 mb-6 bg-red-50 border-red-200 text-red-700 text-xs font-semibold">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Main Live Activation Card -->
    <div id="live-activation-container"
         data-activation-id="<?= (int)$activation['id'] ?>"
         data-expires-at="<?= e(date('c', strtotime($activation['expires_at']))) ?>"
         class="card-premium p-8 mb-8 border-purple-200 shadow-sm relative overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="text-3xl"><?= e($activation['flag_emoji']) ?></span>
                <div>
                    <h2 class="text-xl font-bold text-gray-900"><?= e($activation['service_name']) ?></h2>
                    <div class="text-xs text-gray-500"><?= e($activation['country_name']) ?></div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span id="activation-status-badge" class="<?= match($activation['status']) {
                    'sms_received' => 'badge-success',
                    'refunded', 'cancelled' => 'badge-warning',
                    'expired' => 'badge-danger',
                    default => 'badge-purple'
                } ?> text-xs font-semibold uppercase tracking-wider">
                    <?= e(str_replace('_', ' ', $activation['status'])) ?>
                </span>
                
                <span id="activation-timer" class="font-mono font-bold text-sm text-[#6D28D9] bg-purple-50 px-3 py-1.5 rounded-lg border border-purple-100">
                    --:--
                </span>
            </div>
        </div>

        <!-- Phone Number Highlight -->
        <div class="my-8 text-center bg-gray-50 p-6 rounded-2xl border border-gray-200">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Phone Number</div>
            <div class="text-3xl sm:text-4xl font-extrabold text-gray-900 font-mono tracking-wider">
                <?= e($activation['full_phone_number']) ?>
            </div>
            <div class="mt-4">
                <button data-copy="<?= e($activation['full_phone_number']) ?>" class="btn-primary text-xs py-2 px-5 shadow-sm">
                    Copy Phone Number
                </button>
            </div>
        </div>

        <!-- SMS Reception Area -->
        <div id="activation-sms-box" class="<?= empty($smsList) && $activation['status'] !== 'sms_received' ? 'hidden' : '' ?> mb-6">
            <div class="bg-green-50 border border-green-200 rounded-2xl p-6 text-center">
                <div class="text-xs font-bold text-green-700 uppercase tracking-wider mb-1">Verification Code Received</div>
                <div id="activation-code-display" class="text-3xl font-extrabold text-green-900 font-mono my-2 tracking-widest">
                    <?= !empty($smsList[0]['verification_code']) ? e($smsList[0]['verification_code']) : (!empty($smsList[0]['message_text']) ? e($smsList[0]['message_text']) : 'Code Received') ?>
                </div>
                <?php if (!empty($smsList[0]['verification_code'])): ?>
                    <button data-copy="<?= e($smsList[0]['verification_code']) ?>" class="btn-secondary text-xs py-1.5 px-4 mt-2">
                        Copy Code
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Instructions & Action Bar -->
        <div class="pt-6 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 gap-4">
            <div>
                Enter this phone number into the service to receive your verification code. Messages appear automatically.
            </div>

            <?php if (in_array($activation['status'], ['waiting', 'waiting_sms', 'number_assigned'], true) && empty($smsList)): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel? If cancelled before SMS arrives, your wallet is refunded instantly.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn-secondary text-xs text-red-600 hover:bg-red-50 border-red-200">
                        Cancel & Refund
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Incoming Message Log -->
    <?php if (!empty($smsList)): ?>
        <div class="card-premium p-6">
            <h3 class="font-bold text-gray-900 text-sm mb-4">Received Messages</h3>
            <div class="space-y-3">
                <?php foreach ($smsList as $msg): ?>
                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-100 text-xs">
                        <div class="flex justify-between items-center text-gray-400 mb-1">
                            <span>From: <?= e($msg['sender'] ?: 'SMS') ?></span>
                            <span><?= date('M j, Y H:i:s', strtotime($msg['received_at'])) ?> UTC</span>
                        </div>
                        <div class="font-mono text-gray-800 text-sm mt-1"><?= e($msg['message_text']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
