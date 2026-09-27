<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * Wallet & Financial Ledger System (Strict ACID MySQL Transactions)
 * ==============================================================================
 */

class Wallet
{
    /**
     * Get or initialize a user's wallet record
     */
    public static function getOrCreate(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT * FROM wallets WHERE user_id = :uid FOR UPDATE');
        $stmt->execute(['uid' => $userId]);
        $wallet = $stmt->fetch();

        if (!$wallet) {
            $insert = $db->prepare('
                INSERT INTO wallets (user_id, balance, total_deposited, total_spent, total_refunded, currency)
                VALUES (:uid, 0.0000, 0.0000, 0.0000, 0.0000, :curr)
            ');
            $insert->execute(['uid' => $userId, 'curr' => DEFAULT_CURRENCY]);

            $stmt->execute(['uid' => $userId]);
            $wallet = $stmt->fetch();
        }

        return $wallet;
    }

    /**
     * Atomically debit user wallet with row-locking and balance guard
     *
     * @return array [success => bool, transaction_ref => string, balance_after => float, error => string]
     */
    public static function debit(
        int $userId,
        float $amount,
        string $description,
        ?string $refType = null,
        ?string $refId = null
    ): array {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Debit amount must be strictly greater than zero.'];
        }

        $db = Database::getConnection();
        $isExistingTransaction = $db->inTransaction();

        if (!$isExistingTransaction) {
            $db->beginTransaction();
        }

        try {
            // Lock wallet row for update
            $wallet = self::getOrCreate($db, $userId);
            $currentBalance = (float)$wallet['balance'];

            // Prevent negative balance
            if ($currentBalance < $amount) {
                if (!$isExistingTransaction) {
                    $db->rollBack();
                }
                return [
                    'success' => false,
                    'error'   => 'Insufficient wallet balance. Please add funds to proceed.',
                    'current_balance' => $currentBalance,
                    'required_amount' => $amount
                ];
            }

            $newBalance = $currentBalance - $amount;
            $newTotalSpent = (float)$wallet['total_spent'] + $amount;
            $txRef = generate_ref('TX-DR');

            // 1. Update wallet record
            $upd = $db->prepare('
                UPDATE wallets
                SET balance = :bal, total_spent = :spent, updated_at = NOW()
                WHERE id = :wid
            ');
            $upd->execute([
                'bal'   => number_format($newBalance, 4, '.', ''),
                'spent' => number_format($newTotalSpent, 4, '.', ''),
                'wid'   => $wallet['id']
            ]);

            // 2. Append immutable ledger transaction
            $txStmt = $db->prepare('
                INSERT INTO wallet_transactions
                (transaction_ref, wallet_id, user_id, type, amount, balance_before, balance_after, status, description, reference_type, reference_id)
                VALUES
                (:ref, :wid, :uid, "debit", :amt, :b_before, :b_after, "completed", :desc, :reftype, :refid)
            ');
            $txStmt->execute([
                'ref'      => $txRef,
                'wid'      => $wallet['id'],
                'uid'      => $userId,
                'amt'      => number_format($amount, 4, '.', ''),
                'b_before' => number_format($currentBalance, 4, '.', ''),
                'b_after'  => number_format($newBalance, 4, '.', ''),
                'desc'     => $description,
                'reftype'  => $refType,
                'refid'    => $refId
            ]);

            if (!$isExistingTransaction) {
                $db->commit();
            }

            return [
                'success'         => true,
                'transaction_ref' => $txRef,
                'balance_before'  => $currentBalance,
                'balance_after'   => $newBalance
            ];
        } catch (Throwable $e) {
            if (!$isExistingTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[WALLET DEBIT ERROR] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Financial transaction failed. Please retry.'];
        }
    }

    /**
     * Atomically credit user wallet (Deposit, Refund, or Admin Adjustment)
     */
    public static function credit(
        int $userId,
        float $amount,
        string $description,
        string $type = 'credit',
        ?string $refType = null,
        ?string $refId = null,
        ?int $adminId = null
    ): array {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Credit amount must be greater than zero.'];
        }

        if (!in_array($type, ['credit', 'refund', 'adjustment', 'payment'], true)) {
            $type = 'credit';
        }

        $db = Database::getConnection();
        $isExistingTransaction = $db->inTransaction();

        if (!$isExistingTransaction) {
            $db->beginTransaction();
        }

        try {
            $wallet = self::getOrCreate($db, $userId);
            $currentBalance = (float)$wallet['balance'];
            $newBalance = $currentBalance + $amount;

            $newTotalDeposited = (float)$wallet['total_deposited'];
            $newTotalRefunded = (float)$wallet['total_refunded'];

            if ($type === 'refund') {
                $newTotalRefunded += $amount;
            } elseif ($type === 'credit' || $type === 'payment') {
                $newTotalDeposited += $amount;
            }

            $prefix = match ($type) {
                'refund'     => 'TX-RF',
                'adjustment' => 'TX-ADJ',
                'payment'    => 'TX-PAY',
                default      => 'TX-CR'
            };
            $txRef = generate_ref($prefix);

            // Update wallet
            $upd = $db->prepare('
                UPDATE wallets
                SET balance = :bal,
                    total_deposited = :dep,
                    total_refunded = :ref,
                    updated_at = NOW()
                WHERE id = :wid
            ');
            $upd->execute([
                'bal' => number_format($newBalance, 4, '.', ''),
                'dep' => number_format($newTotalDeposited, 4, '.', ''),
                'ref' => number_format($newTotalRefunded, 4, '.', ''),
                'wid' => $wallet['id']
            ]);

            // Append ledger entry
            $txStmt = $db->prepare('
                INSERT INTO wallet_transactions
                (transaction_ref, wallet_id, user_id, type, amount, balance_before, balance_after, status, description, reference_type, reference_id, created_by_admin_id)
                VALUES
                (:ref, :wid, :uid, :type, :amt, :b_before, :b_after, "completed", :desc, :reftype, :refid, :admin_id)
            ');
            $txStmt->execute([
                'ref'      => $txRef,
                'wid'      => $wallet['id'],
                'uid'      => $userId,
                'type'     => $type,
                'amt'      => number_format($amount, 4, '.', ''),
                'b_before' => number_format($currentBalance, 4, '.', ''),
                'b_after'  => number_format($newBalance, 4, '.', ''),
                'desc'     => $description,
                'reftype'  => $refType,
                'refid'    => $refId,
                'admin_id' => $adminId
            ]);

            if (!$isExistingTransaction) {
                $db->commit();
            }

            return [
                'success'         => true,
                'transaction_ref' => $txRef,
                'balance_before'  => $currentBalance,
                'balance_after'   => $newBalance
            ];
        } catch (Throwable $e) {
            if (!$isExistingTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[WALLET CREDIT ERROR] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Ledger credit operation failed.'];
        }
    }
}
