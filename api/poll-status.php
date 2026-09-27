<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Backend Polling Endpoint for Real-Time Activation SMS Verification
 * ==============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    send_json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

$user = current_user();
$userId = (int)$user['id'];
$activationId = (int)($_GET['activation_id'] ?? 0);

if (!$activationId) {
    send_json_response(['success' => false, 'error' => 'Missing activation ID'], 400);
}

$db = Database::getConnection();

try {
    // 1. Fetch activation with order and provider details
    $stmt = $db->prepare('
        SELECT a.*, o.id AS order_id, o.status AS order_status,
               p.id AS provider_id, p.slug AS provider_slug,
               (SELECT verification_code FROM sms_messages WHERE activation_id = a.id ORDER BY id DESC LIMIT 1) AS existing_code
    FROM activations a
    JOIN orders o ON o.id = a.order_id
    JOIN providers p ON p.id = a.provider_id
    WHERE a.id = :aid AND a.user_id = :uid
    LIMIT 1
    ');
    $stmt->execute(['aid' => $activationId, 'uid' => $userId]);
    $act = $stmt->fetch();

    if (!$act) {
        send_json_response(['success' => false, 'error' => 'Activation record not found'], 404);
    }

    // 2. Terminal state check: if code already received
    if ($act['status'] === 'sms_received' || !empty($act['existing_code'])) {
        send_json_response([
            'success'  => true,
            'status'   => 'sms_received',
            'sms_code' => $act['existing_code']
        ]);
    }

    // 3. Terminal state check: if already cancelled/refunded/expired
    if (in_array($act['status'], ['cancelled', 'refunded', 'expired'], true)) {
        send_json_response([
            'success'  => true,
            'status'   => $act['status'],
            'sms_code' => null
        ]);
    }

    // 4. Timeout check: if past expiration window, trigger auto-refund
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $expires = new DateTime($act['expires_at'], new DateTimeZone('UTC'));

    if ($now >= $expires) {
        RefundEngine::process((int)$act['order_id'], 'Activation reservation window expired', 'system');
        $upd = $db->prepare('UPDATE activations SET status = "expired", updated_at = NOW() WHERE id = :id');
        $upd->execute(['id' => $activationId]);

        send_json_response([
            'success'  => true,
            'status'   => 'expired',
            'sms_code' => null
        ]);
    }

    // 5. Query active provider adapter
    $adapter = ProviderFactory::createById((int)$act['provider_id']);
    if (!$adapter) {
        send_json_response([
            'success'  => true,
            'status'   => $act['status'],
            'sms_code' => null
        ]);
    }

    $providerStatus = $adapter->checkStatus($act['provider_activation_id']);

    if ($providerStatus['success']) {
        if ($providerStatus['status'] === 'sms_received' && !empty($providerStatus['sms_code'])) {
            $code = (string)$providerStatus['sms_code'];
            $fullSms = (string)($providerStatus['full_sms'] ?? ('Your code is: ' . $code));

            $db->beginTransaction();

            // Insert SMS message
            $insSms = $db->prepare('
                INSERT INTO sms_messages (activation_id, sender, message_text, verification_code, received_at)
                VALUES (:aid, "Provider", :text, :code, NOW())
            ');
            $insSms->execute([
                'aid'  => $activationId,
                'text' => $fullSms,
                'code' => $code
            ]);

            // Update activation status
            $updAct = $db->prepare('UPDATE activations SET status = "sms_received", sms_received_at = NOW(), updated_at = NOW() WHERE id = :id');
            $updAct->execute(['id' => $activationId]);

            // Update order status to completed
            $updOrd = $db->prepare('UPDATE orders SET status = "completed", updated_at = NOW() WHERE id = :id');
            $updOrd->execute(['id' => $act['order_id']]);

            $db->commit();

            Notifications::sendUser(
                $userId,
                'sms',
                'Verification SMS Received',
                'Your code for line ' . $act['full_phone_number'] . ' is ' . $code,
                '/user/active.php?id=' . $activationId
            );

            send_json_response([
                'success'  => true,
                'status'   => 'sms_received',
                'sms_code' => $code,
                'full_sms' => $fullSms
            ]);
        } elseif ($providerStatus['status'] === 'cancelled') {
            RefundEngine::process((int)$act['order_id'], 'Provider cancelled number allocation', 'system');
            send_json_response([
                'success'  => true,
                'status'   => 'cancelled',
                'sms_code' => null
            ]);
        }
    }

    send_json_response([
        'success'  => true,
        'status'   => $act['status'],
        'sms_code' => null
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[POLL STATUS ERROR] ' . $e->getMessage());
    send_json_response(['success' => false, 'error' => 'Internal server error'], 500);
}
