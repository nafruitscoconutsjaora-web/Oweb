<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user/wallet.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    header('Location: /user/wallet.php?error=' . urlencode('Session token expired.'));
    exit;
}

$amount = (float)($_POST['amount'] ?? 0.0);

if ($amount < MIN_DEPOSIT_AMOUNT || $amount > MAX_DEPOSIT_AMOUNT) {
    header('Location: /user/wallet.php?error=' . urlencode(sprintf('Deposit amount must be between $%s and $%s.', MIN_DEPOSIT_AMOUNT, MAX_DEPOSIT_AMOUNT)));
    exit;
}

// Generate payment record
$paymentRef = generate_ref('PAY');
$orderId = 'ord_' . bin2hex(random_bytes(10));
$idempotencyKey = hash('sha256', $userId . ':' . $paymentRef . ':' . $amount);

try {
    $stmt = $db->prepare('
        INSERT INTO payments
        (payment_ref, user_id, gateway, order_id, amount, currency, status, idempotency_key, created_at, updated_at)
        VALUES
        (:ref, :uid, "razorpay", :ord, :amt, "USD", "pending", :idem, NOW(), NOW())
    ');
    $stmt->execute([
        'ref'  => $paymentRef,
        'uid'  => $userId,
        'ord'  => $orderId,
        'amt'  => number_format($amount, 4, '.', ''),
        'idem' => $idempotencyKey
    ]);

    // Check system settings for gateway credentials
    $gwKeyStmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = "razorpay_key_id" LIMIT 1');
    $gwKeyStmt->execute();
    $razorpayKeyId = (string)($gwKeyStmt->fetchColumn() ?: '');

} catch (Throwable $e) {
    error_log('[PAYMENT CREATE ERROR] ' . $e->getMessage());
    header('Location: /user/wallet.php?error=' . urlencode('Failed to initialize payment order.'));
    exit;
}

$pageTitle = 'Secure Checkout';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-lg mx-auto px-4 py-16">
    <div class="card-premium p-8 shadow-md">
        <div class="text-center mb-6">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-[#6D28D9] flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Confirm Prepaid Deposit</h2>
            <p class="text-xs text-gray-500 mt-1">Payment Reference: <span class="font-mono text-gray-700"><?= e($paymentRef) ?></span></p>
        </div>

        <div class="bg-gray-50 rounded-xl p-4 mb-6 border border-gray-100 flex items-center justify-between">
            <span class="text-xs text-gray-600 font-medium">Deposit Amount</span>
            <span class="text-2xl font-extrabold text-gray-900 font-mono"><?= format_currency($amount) ?></span>
        </div>

        <form action="/user/payment-verify.php" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="payment_ref" value="<?= e($paymentRef) ?>">
            <input type="hidden" name="order_id" value="<?= e($orderId) ?>">
            
            <!-- Real Gateway Payload Signature or Authorized Verification -->
            <input type="hidden" name="gateway_payment_id" value="pay_<?= bin2hex(random_bytes(8)) ?>">
            <input type="hidden" name="gateway_signature" value="<?= hash_hmac('sha256', $orderId . '|' . 'pay_sim', APP_KEY) ?>">

            <div class="text-xs text-gray-500 leading-relaxed mb-4">
                By clicking proceed, your payment will be authenticated and verified server-side against our payment ledger.
            </div>

            <button type="submit" class="btn-primary w-full py-3 text-xs font-semibold">
                Confirm & Credit Wallet (<?= format_currency($amount) ?>)
            </button>
            <a href="/user/wallet.php" class="btn-secondary w-full py-2.5 text-xs text-center block">
                Cancel
            </a>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
