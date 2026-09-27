<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Production Refund Engine (Strict Eligibility & Idempotency)
 * ==============================================================================
 */

class RefundEngine
{
    /**
     * Process an automatic or administrative refund for an unfulfilled activation
     *
     * @return array [success => bool, message => string]
     */
    public static function process(
        int $orderId,
        string $reason,
        string $initiatedBy = 'system',
        ?int $adminId = null
    ): array {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Lock order for inspection
            $orderStmt = $db->prepare('
                SELECT o.*, a.id AS activation_id, a.status AS act_status, a.provider_id, a.provider_activation_id,
                       (SELECT COUNT(*) FROM sms_messages WHERE activation_id = a.id) AS sms_count
                FROM orders o
                LEFT JOIN activations a ON a.order_id = o.id
                WHERE o.id = :id
                FOR UPDATE
            ');
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch();

            if (!$order) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Order not found.'];
            }

            // 2. Prevent duplicate refund
            if (in_array($order['status'], ['refunded', 'cancelled'], true)) {
                $db->rollBack();
                return ['success' => false, 'message' => 'This order has already been refunded or cancelled.'];
            }

            // 3. SMS received guard: if SMS was received, activation succeeded and cannot be refunded unless administrative override
            if ((int)$order['sms_count'] > 0 && $initiatedBy !== 'admin') {
                $db->rollBack();
                return ['success' => false, 'message' => 'Cannot refund activation because SMS verification was successfully delivered.'];
            }

            // 4. Check for existing refund record
            $checkRefund = $db->prepare('SELECT id FROM refunds WHERE order_id = :oid LIMIT 1');
            $checkRefund->execute(['oid' => $orderId]);
            if ($checkRefund->fetch()) {
                $db->rollBack();
                return ['success' => false, 'message' => 'A refund record already exists for this order.'];
            }

            $refundAmount = (float)$order['price'];
            $userId = (int)$order['user_id'];

            // 5. Credit user wallet atomically
            $creditResult = Wallet::credit(
                $userId,
                $refundAmount,
                'Refund for Order #' . $order['order_number'] . ': ' . $reason,
                'refund',
                'order',
                (string)$orderId,
                $adminId
            );

            if (!$creditResult['success']) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Failed to credit user wallet.'];
            }

            // 6. Update order status
            $updOrder = $db->prepare('UPDATE orders SET status = "refunded", updated_at = NOW() WHERE id = :id');
            $updOrder->execute(['id' => $orderId]);

            // 7. Update activation status if present
            if (!empty($order['activation_id'])) {
                $updAct = $db->prepare('UPDATE activations SET status = "refunded", updated_at = NOW() WHERE id = :aid');
                $updAct->execute(['aid' => $order['activation_id']]);
            }

            // 8. Create refund record
            $refStmt = $db->prepare('
                INSERT INTO refunds
                (order_id, activation_id, user_id, amount, reason, initiated_by, admin_id, status, wallet_transaction_id, created_at)
                VALUES
                (:oid, :aid, :uid, :amt, :reason, :by, :admin, "completed", :tx_id, NOW())
            ');
            $refStmt->execute([
                'oid'    => $orderId,
                'aid'    => $order['activation_id'],
                'uid'    => $userId,
                'amt'    => number_format($refundAmount, 4, '.', ''),
                'reason' => $reason,
                'by'     => $initiatedBy,
                'admin'  => $adminId,
                'tx_id'  => null
            ]);

            $db->commit();

            // Notify user & audit
            Notifications::sendUser(
                $userId,
                'refund',
                'Refund Processed',
                sprintf('Your order #%s has been refunded (%s). The amount was returned to your balance.', $order['order_number'], format_currency($refundAmount)),
                '/user/orders.php'
            );

            AuditLogger::log(
                'order.refund',
                'order',
                (string)$orderId,
                ['status' => $order['status']],
                ['status' => 'refunded', 'amount' => $refundAmount, 'reason' => $reason],
                $initiatedBy === 'admin' ? 'admin' : 'system'
            );

            return ['success' => true, 'message' => 'Refund processed successfully.'];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[REFUND ERROR] ' . $e->getMessage());
            return ['success' => false, 'message' => 'System error during refund processing.'];
        }
    }
}
