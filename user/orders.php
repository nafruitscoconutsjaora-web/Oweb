<?php
declare(strict_types=1);
$pageTitle = 'Order History';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

$statusFilter = trim($_GET['status'] ?? '');
$orders = [];

try {
    $sql = '
        SELECT o.*, s.name AS service_name, c.name AS country_name, c.flag_emoji,
               a.id AS activation_id, a.phone_number, a.full_phone_number,
               (SELECT verification_code FROM sms_messages WHERE activation_id = a.id ORDER BY id DESC LIMIT 1) AS last_code
        FROM orders o
        JOIN services s ON s.id = o.service_id
        JOIN countries c ON c.id = o.country_id
        LEFT JOIN activations a ON a.order_id = o.id
        WHERE o.user_id = :uid
    ';
    $params = ['uid' => $userId];

    if ($statusFilter !== '') {
        $sql .= ' AND o.status = :st';
        $params['st'] = $statusFilter;
    }

    $sql .= ' ORDER BY o.created_at DESC LIMIT 50';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[USER ORDERS ERROR] ' . $e->getMessage());
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Order History</h1>
            <p class="text-xs text-gray-500 mt-1">Review your historical activations, verification codes, and refund statuses.</p>
        </div>

        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
            <a href="?status=" class="px-3 py-1.5 rounded-lg <?= $statusFilter === '' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">All</a>
            <a href="?status=active" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'active' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Active</a>
            <a href="?status=completed" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'completed' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Completed</a>
            <a href="?status=refunded" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'refunded' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-white text-gray-600 border border-gray-200' ?>">Refunded</a>
        </div>
    </div>

    <div class="card-premium overflow-hidden">
        <?php if (empty($orders)): ?>
            <div class="py-16 text-center text-xs text-gray-400">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                No orders match your filter criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-6">Order ID</th>
                            <th class="py-3 px-6">Service / Region</th>
                            <th class="py-3 px-6">Phone Number</th>
                            <th class="py-3 px-6">Code</th>
                            <th class="py-3 px-6">Price</th>
                            <th class="py-3 px-6">Status</th>
                            <th class="py-3 px-6">Created (UTC)</th>
                            <th class="py-3 px-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($orders as $o): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3.5 px-6 font-mono font-bold text-gray-900">
                                    #<?= e($o['order_number']) ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <div class="font-bold text-gray-900"><?= e($o['service_name']) ?></div>
                                    <div class="text-gray-400 text-[11px]"><?= e($o['flag_emoji']) ?> <?= e($o['country_name']) ?></div>
                                </td>
                                <td class="py-3.5 px-6 font-mono text-gray-800">
                                    <?= e($o['full_phone_number'] ?: '—') ?>
                                </td>
                                <td class="py-3.5 px-6 font-mono font-bold text-[#6D28D9]">
                                    <?= e($o['last_code'] ?: '—') ?>
                                </td>
                                <td class="py-3.5 px-6 font-mono font-bold text-gray-900">
                                    <?= format_currency($o['price']) ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <span class="capitalize font-semibold text-[11px] <?= match($o['status']) {
                                        'completed' => 'badge-success',
                                        'active'    => 'badge-purple',
                                        'refunded'  => 'badge-warning',
                                        'cancelled' => 'badge-warning',
                                        default     => 'badge-danger'
                                    } ?>">
                                        <?= e($o['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-gray-400">
                                    <?= date('Y-m-d H:i', strtotime($o['created_at'])) ?>
                                </td>
                                <td class="py-3.5 px-6 text-right">
                                    <?php if (!empty($o['activation_id'])): ?>
                                        <a href="/user/active.php?id=<?= (int)$o['activation_id'] ?>" class="btn-secondary text-[11px] py-1 px-2.5">
                                            View &rarr;
                                        </a>
                                    <?php endif; ?>
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
