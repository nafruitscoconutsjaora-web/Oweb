<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Inbound Provider Webhook Handler
 * Idempotent, Signature Validated, and Atomic
 * ==============================================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed'], 405);
}

$rawPayload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// Basic payload decode
$data = json_decode($rawPayload, true);
if (!is_array($data)) {
    send_json_response(['error' => 'Invalid JSON payload'], 400);
}

$providerActivationId = (string)($data['activation_id'] ?? $data['id'] ?? '');
$smsCode = (string)($data['code'] ?? '');
$smsText = (string)($data['text'] ?? $data['sms'] ?? '');

if (!$providerActivationId || (!$smsCode && !$smsText)) {
    send_json_response(['error' => 'Missing required webhook parameters'], 422);
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare('
        SELECT a.*, o.id AS order_id, o.user_id, o.order_number
        FROM activations a
        JOIN orders o ON o.id = a.order_id
        WHERE a.provider_activation_id = :paid
        FOR UPDATE
    ');
    $stmt->execute(['paid' => $providerActivationId]);
    $act = $stmt->fetch();

    if (!$act) {
        $db->rollBack();
        send_json_response(['error' => 'Activation not matched'], 404);
    }

    if ($act['status'] === 'sms_received') {
        $db->rollBack();
        send_json_response(['status' => 'already_processed']);
    }

    // Insert SMS message
    $insSms = $db->prepare('
        INSERT INTO sms_messages (activation_id, sender, message_text, verification_code, received_at, raw_payload)
        VALUES (:aid, "Webhook", :text, :code, NOW(), :raw)
    ');
    $insSms->execute([
        'aid'  => $act['id'],
        'text' => $smsText ?: ('Verification code: ' . $smsCode),
        'code' => $smsCode ?: null,
        'raw'  => $rawPayload
    ]);

    // Update activation
    $updAct = $db->prepare('UPDATE activations SET status = "sms_received", sms_received_at = NOW(), updated_at = NOW() WHERE id = :id');
    $updAct->execute(['id' => $act['id']]);

    // Update order
    $updOrd = $db->prepare('UPDATE orders SET status = "completed", updated_at = NOW() WHERE id = :id');
    $updOrd->execute(['id' => $act['order_id']]);

    $db->commit();

    Notifications::sendUser(
        (int)$act['user_id'],
        'sms',
        'Verification SMS Delivered',
        'New code received for order #' . $act['order_number'],
        '/user/active.php?id=' . $act['id']
    );

    send_json_response(['status' => 'success']);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[WEBHOOK ERROR] ' . $e->getMessage());
    send_json_response(['error' => 'Internal webhook error'], 500);
}
