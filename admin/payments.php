<?php
declare(strict_types=1);
$adminTitle = 'Payment Gateways & Ledger';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('wallet.view');

$db = Database::getConnection();

$statusFilter = trim($_GET['status'] ?? '');
$payments = [];

try {
    $sql = '
        SELECT p.*, u.name AS user_name, u.email AS user_email
        FROM payments p
        JOIN users u ON u.id = p.user_id
        WHERE 1=1
    ';
    $params = [];

    if ($statusFilter !== '') {
        $sql .= ' AND p.status = :st';
        $params['st'] = $statusFilter;
    }

    $sql .= ' ORDER BY p.created_at DESC LIMIT 50';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[PAYMENTS FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Payment Gateway Reconciliation</h2>
            <p class="text-xs text-gray-500 mt-0.5">Audit inbound deposit webhooks, gateway signatures, and transaction settlement statuses.</p>
        </div>

        <div class="flex items-center gap-1.5 text-xs">
            <a href="?status=" class="px-3 py-1.5 rounded-lg <?= $statusFilter === '' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">All</a>
            <a href="?status=completed" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'completed' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Completed</a>
            <a href="?status=pending" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'pending' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Pending</a>
        </div>
    </div>

    <div class="card-premium overflow-hidden">
        <?php if (empty($payments)): ?>
            <div class="p-12 text-center text-xs text-gray-400">
                No payment transactions recorded in gateway log.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-6">Payment Ref</th>
                            <th class="py-3 px-6">Customer</th>
                            <th class="py-3 px-6">Gateway / Order ID</th>
                            <th class="py-3 px-6 text-right">Amount</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <th class="py-3 px-6">Timestamp (UTC)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($payments as $p): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3.5 px-6 font-mono font-bold text-gray-900">
                                    <?= e($p['payment_ref']) ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-gray-900"><?= e($p['user_name']) ?></div>
                                    <div class="text-gray-400 text-[10px]"><?= e($p['user_email']) ?></div>
                                </td>
                                <td class="py-3.5 px-6 font-mono text-gray-600">
                                    <span class="badge-purple text-[10px] mr-1.5 uppercase"><?= e($p['gateway']) ?></span>
                                    <span><?= e($p['order_id']) ?></span>
                                </td>
                                <td class="py-3.5 px-6 text-right font-mono font-bold text-gray-900">
                                    <?= format_currency($p['amount']) ?>
                                </td>
                                <td class="py-3.5 px-6 text-center">
                                    <span class="capitalize text-[10px] font-semibold <?= match($p['status']) {
                                        'completed' => 'badge-success',
                                        'pending'   => 'badge-warning',
                                        default     => 'badge-danger'
                                    } ?>">
                                        <?= e($p['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-gray-400 font-mono">
                                    <?= date('Y-m-d H:i:s', strtotime($p['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
