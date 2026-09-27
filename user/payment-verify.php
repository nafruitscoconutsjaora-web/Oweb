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
    header('Location: /user/wallet.php?error=' . urlencode('Security session expired.'));
    exit;
}

$paymentRef = trim($_POST['payment_ref'] ?? '');
$orderId = trim($_POST['order_id'] ?? '');
$gatewayPaymentId = trim($_POST['gateway_payment_id'] ?? '');
$gatewaySignature = trim($_POST['gateway_signature'] ?? '');

if (!$paymentRef) {
    header('Location: /user/wallet.php?error=' . urlencode('Invalid payment parameters.'));
    exit;
}

$db->beginTransaction();

try {
    // 1. Fetch payment record with row lock
    $stmt = $db->prepare('SELECT * FROM payments WHERE payment_ref = :ref AND user_id = :uid FOR UPDATE');
    $stmt->execute(['ref' => $paymentRef, 'uid' => $userId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        $db->rollBack();
        header('Location: /user/wallet.php?error=' . urlencode('Payment record not found.'));
        exit;
    }

    // 2. Prevent duplicate processing
    if ($payment['status'] === 'completed') {
        $db->rollBack();
        header('Location: /user/wallet.php?success=' . urlencode('Payment has already been confirmed and credited.'));
        exit;
    }

    $amount = (float)$payment['amount'];

    // 3. Atomically credit wallet
    $creditRes = Wallet::credit(
        $userId,
        $amount,
        'Prepaid Deposit: Payment #' . $paymentRef,
        'payment',
        'payment',
        $paymentRef
    );

    if (!$creditRes['success']) {
        $db->rollBack();
        header('Location: /user/wallet.php?error=' . urlencode('Failed to credit ledger: ' . $creditRes['error']));
        exit;
    }

    // 4. Update payment record status
    $upd = $db->prepare('
        UPDATE payments
        SET status = "completed",
            gateway_payment_id = :gpid,
            gateway_signature = :sig,
            updated_at = NOW()
        WHERE id = :id
    ');
    $upd->execute([
        'gpid' => $gatewayPaymentId,
        'sig'  => $gatewaySignature,
        'id'   => $payment['id']
    ]);

    $db->commit();

    // 5. Notify user
    Notifications::sendUser(
        $userId,
        'payment',
        'Deposit Confirmed',
        sprintf('Your deposit of %s was confirmed and added to your balance.', format_currency($amount)),
        '/user/wallet.php'
    );

    header('Location: /user/wallet.php?success=' . urlencode(sprintf('Successfully credited %s to your wallet balance!', format_currency($amount))));
    exit;

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[PAYMENT VERIFY ERROR] ' . $e->getMessage());
    header('Location: /user/wallet.php?error=' . urlencode('Server error during payment verification.'));
    exit;
}
